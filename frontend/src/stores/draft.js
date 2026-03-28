import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import api from '@/utils/api'
import echo from '@/utils/echo'
import { playTick, playYourTurn } from '@/utils/draftSounds'

export const useDraftStore = defineStore('draft', () => {
  const draftState = ref(null)
  const availablePicks = ref([])
  const loading = ref(false)
  const error = ref('')
  const timerSeconds = ref(0)
  const pickFeed = ref([])
  const autoDraftMembers = ref([])
  const preDraftSeconds = ref(0)
  const presentMembers = ref([])
  let timerInterval = null
  let preDraftInterval = null
  let countdownPollInterval = null
  let countdownPollLeagueId = null
  let channel = null

  const isInCountdown = computed(() =>
    draftState.value?.status === 'active' && !draftState.value?.picks_started
  )

  const isMyTurn = computed(() => {
    if (isInCountdown.value) return false
    return draftState.value?.is_my_turn || false
  })

  const myMembershipId = computed(() =>
    draftState.value?.my_membership_id || null
  )

  const myPicks = computed(() =>
    (draftState.value?.picks || []).filter(
      (p) => p.drafter_id === myMembershipId.value
    )
  )

  const myStarters = computed(() =>
    myPicks.value.filter((p) => p.position === 'starter').sort((a, b) => a.slot_number - b.slot_number)
  )

  const myBench = computed(() =>
    myPicks.value.filter((p) => p.position === 'bench').sort((a, b) => a.slot_number - b.slot_number)
  )

  const rosterConfig = computed(() =>
    draftState.value?.roster_config || {}
  )

  const aggregateOddsFloor = computed(() =>
    draftState.value?.aggregate_odds_floor ?? -250
  )

  const starterCount = computed(() =>
    Object.values(rosterConfig.value).reduce((sum, v) => sum + (v || 0), 0)
  )

  const benchSlots = computed(() => draftState.value?.bench_slots ?? 2)

  const myStartersByType = computed(() => {
    const config = rosterConfig.value || {}
    const result = {}
    for (const [type, count] of Object.entries(config)) {
      const filled = myStarters.value.filter((p) => p.slot_type === type)
      result[type] = { needed: count, filled }
    }
    return result
  })

  const unfilledTypes = computed(() => {
    const result = {}
    for (const [type, data] of Object.entries(myStartersByType.value)) {
      const remaining = data.needed - data.filled.length
      if (remaining > 0) {
        result[type] = remaining
      }
    }
    return result
  })

  const aggregateImpliedProbability = computed(() => {
    const starters = myStarters.value
    if (!starters.length) return 0
    let total = 0
    for (const pick of starters) {
      const odds = pick.drafted_odds
      if (odds < 0) {
        total += Math.abs(odds) / (Math.abs(odds) + 100)
      } else {
        total += 100 / (odds + 100)
      }
    }
    return total / starters.length
  })

  const aggregateAmerican = computed(() => {
    const prob = aggregateImpliedProbability.value
    if (prob <= 0 || prob >= 1) return 0
    if (prob >= 0.5) return Math.round(-100 * prob / (1 - prob))
    return Math.round(100 * (1 - prob) / prob)
  })

  const amInAutoDraft = computed(() =>
    autoDraftMembers.value.includes(myMembershipId.value)
  )

  const currentDrafter = computed(() => {
    if (!draftState.value) return null
    return draftState.value.members?.find(
      (m) => m.id === draftState.value.current_drafter_id
    )
  })

  const upcomingDrafters = computed(() => {
    const state = draftState.value
    if (!state?.draft_order || !state.members) return []

    const order = state.draft_order
    const totalRounds = state.total_rounds || 1
    const members = state.members

    // Build snake sequence
    const snake = []
    for (let r = 0; r < totalRounds; r++) {
      if (r % 2 === 0) {
        snake.push(...order)
      } else {
        snake.push(...[...order].reverse())
      }
    }

    const currentIdx = state.current_pick_index ?? 0
    const upcoming = []
    for (let i = currentIdx; i < snake.length && upcoming.length < 8; i++) {
      const memberId = snake[i]
      const member = members.find((m) => m.id === memberId)
      if (member) {
        upcoming.push({
          ...member,
          pickIndex: i,
          round: Math.floor(i / order.length) + 1,
          isCurrent: i === currentIdx,
        })
      }
    }
    return upcoming
  })

  function oddsToImpliedProb(odds) {
    if (odds < 0) return Math.abs(odds) / (Math.abs(odds) + 100)
    return 100 / (odds + 100)
  }

  function calcNewAggregate(newOdds) {
    const starters = myStarters.value
    const currentOdds = starters.map((p) => p.drafted_odds)
    const allOdds = [...currentOdds, newOdds]
    let total = 0
    for (const o of allOdds) {
      total += oddsToImpliedProb(o)
    }
    return total / allOdds.length
  }

  function wouldBustAggregate(newOdds) {
    const floorOdds = aggregateOddsFloor.value
    const floorProb = oddsToImpliedProb(floorOdds)
    const newAvg = calcNewAggregate(newOdds)
    return newAvg > floorProb
  }

  function probToAmerican(prob) {
    if (prob <= 0 || prob >= 1) return 0
    if (prob >= 0.5) return Math.round(-100 * prob / (1 - prob))
    return Math.round(100 * (1 - prob) / prob)
  }

  async function loadDraft(leagueId) {
    loading.value = true
    error.value = ''
    try {
      const { data } = await api.get(`/api/v1/leagues/${leagueId}/draft`)
      draftState.value = data.data
      autoDraftMembers.value = data.data.auto_draft_members || []

      // Seed pick feed from existing picks (most recent first)
      const existingPicks = data.data.picks || []
      if (existingPicks.length && !pickFeed.value.length) {
        pickFeed.value = [...existingPicks]
          .sort((a, b) => b.pick_number - a.pick_number)
          .slice(0, 20)
          .map((p) => ({
            id: p.id,
            drafter_team: p.drafter_team,
            description: p.description,
            pick_type: p.pick_type,
            sport: p.sport,
            drafted_odds: p.drafted_odds,
            pick_number: p.pick_number,
            round: p.round,
            is_auto_pick: false,
          }))
      }

      // Active draft in countdown: start pre-draft timer and load pool
      if (data.data.status === 'active' && !data.data.picks_started && data.data.draft_starts_at) {
        startPreDraftTimer()
      }
      startTimer()
      return { success: true }
    } catch (e) {
      error.value = e.response?.data?.message || 'Failed to load draft'
      return { success: false, message: error.value }
    } finally {
      loading.value = false
    }
  }

  async function loadPool(leagueId) {
    try {
      const { data } = await api.get(`/api/v1/leagues/${leagueId}/draft/pool`)
      availablePicks.value = data.data
    } catch {
      availablePicks.value = []
    }
  }

  async function submitPick(leagueId, pickSelectionId, slotNumber = null) {
    loading.value = true
    try {
      const { data } = await api.post(`/api/v1/leagues/${leagueId}/draft/pick`, {
        pick_selection_id: pickSelectionId,
        slot_number: slotNumber,
      })
      // Reload state immediately so the picker sees their own update.
      // WebSocket events still handle updates for other users.
      await Promise.all([loadDraft(leagueId), loadPool(leagueId)])
      return { success: true, data: data.data }
    } catch (e) {
      return {
        success: false,
        message: e.response?.data?.message || 'Failed to submit pick',
      }
    } finally {
      loading.value = false
    }
  }

  async function disableAutoDraft(leagueId) {
    try {
      await api.post(`/api/v1/leagues/${leagueId}/draft/autodraft/disable`)
      autoDraftMembers.value = autoDraftMembers.value.filter(
        (id) => id !== myMembershipId.value
      )
      return { success: true }
    } catch (e) {
      return {
        success: false,
        message: e.response?.data?.message || 'Failed to disable autodraft',
      }
    }
  }

  function subscribeToDraftChannel(leagueId) {
    if (channel) {
      echo.leave(`draft.${leagueId}`)
    }

    countdownPollLeagueId = leagueId

    channel = echo.join(`draft.${leagueId}`)
      .here((users) => {
        presentMembers.value = users
      })
      .joining((user) => {
        if (!presentMembers.value.find((u) => u.id === user.id)) {
          presentMembers.value = [...presentMembers.value, user]
        }
      })
      .leaving((user) => {
        presentMembers.value = presentMembers.value.filter((u) => u.id !== user.id)
      })
      .listen('.DraftStarted', (e) => {
        if (draftState.value) {
          Object.assign(draftState.value, {
            id: e.draft_id,
            status: 'active',
            draft_order: e.draft_order,
            draft_order_weights: e.draft_order_weights,
            draft_starts_at: e.draft_starts_at,
            current_drafter_id: e.current_drafter_id,
            current_round: e.current_round,
            total_rounds: e.total_rounds,
            pick_timer_seconds: e.pick_timer_seconds,
            current_pick_started_at: e.timer_started_at,
            picks_started: e.timer_started_at != null,
            is_my_turn: e.timer_started_at != null && e.current_drafter_id === myMembershipId.value,
            members: e.members,
            picks: [],
          })

          // If timer_started_at is null, we're in countdown — start pre-draft timer
          if (!e.timer_started_at && e.draft_starts_at) {
            startPreDraftTimer()
          } else {
            startTimer()
          }

          // Load the pick pool for all users
          loadPool(leagueId)
        }
      })
      .listen('.DraftPicksBegin', (e) => {
        if (draftState.value) {
          stopPreDraftTimer()
          preDraftSeconds.value = 0
          draftState.value.picks_started = true
          draftState.value.current_pick_started_at = e.current_pick_started_at
          draftState.value.current_drafter_id = e.current_drafter_id
          draftState.value.pick_timer_seconds = e.pick_timer_seconds
          draftState.value.is_my_turn = e.current_drafter_id === myMembershipId.value
          if (draftState.value.is_my_turn) playYourTurn()
          startTimer()
        }
      })
      .listen('.DraftPickMade', (e) => {
        if (draftState.value) {
          // Skip if this pick was already added (e.g. by loadDraft after submitPick)
          const existing = (draftState.value.picks || []).find((p) => p.id === e.pick.id)
          if (!existing) {
            draftState.value.picks = [...(draftState.value.picks || []), {
              id: e.pick.id,
              drafter_id: e.drafter_id,
              drafter_team: e.drafter_team,
              description: e.pick.description,
              pick_type: e.pick.pick_type,
              sport: e.pick.sport,
              player_name: e.pick.player_name,
              home_team: e.pick.home_team,
              away_team: e.pick.away_team,
              game_display: e.pick.game_display,
              snapshot_odds: e.pick.snapshot_odds,
              drafted_odds: e.pick.drafted_odds,
              position: e.pick.position,
              slot_number: e.pick.slot_number,
              slot_type: e.pick.slot_type,
              round: e.round,
              pick_number: e.pick_number,
            }]
          }

          // Push to pick feed (keep last 20)
          pickFeed.value = [
            {
              id: Date.now(),
              drafter_team: e.drafter_team,
              description: e.pick.description,
              pick_type: e.pick.pick_type,
              sport: e.pick.sport,
              drafted_odds: e.pick.drafted_odds,
              pick_number: e.pick_number,
              round: e.round,
              is_auto_pick: e.is_auto_pick || false,
            },
            ...pickFeed.value,
          ].slice(0, 20)

          // Remove from available picks
          availablePicks.value = availablePicks.value.filter(
            (p) => p.id !== e.pick.pick_selection_id
          )
        }
      })
      .listen('.DraftAdvanced', (e) => {
        if (draftState.value) {
          draftState.value.current_drafter_id = e.current_drafter_id
          draftState.value.current_round = e.current_round
          draftState.value.current_pick_index = e.current_pick_index
          draftState.value.current_pick_started_at = e.timer_started_at
          draftState.value.is_my_turn = e.current_drafter_id === myMembershipId.value
          if (draftState.value.is_my_turn) playYourTurn()
          if (e.pick_timer_seconds != null) {
            draftState.value.pick_timer_seconds = e.pick_timer_seconds
          }
          if (e.auto_draft_members != null) {
            autoDraftMembers.value = e.auto_draft_members
          }
          startTimer()
        }
      })
      .listen('.DraftCompleted', (e) => {
        if (draftState.value) {
          draftState.value.status = 'completed'
          draftState.value.completed_at = e.completed_at
          stopTimer()
        }
      })
  }

  function unsubscribe(leagueId) {
    if (channel) {
      echo.leave(`draft.${leagueId}`)
      channel = null
    }
    stopTimer()
    stopPreDraftTimer()
  }

  function startTimer() {
    stopTimer()
    if (!draftState.value?.current_pick_started_at || draftState.value?.status !== 'active') return

    const pickTimerSeconds = draftState.value.pick_timer_seconds || 60
    const startedAt = new Date(draftState.value.current_pick_started_at).getTime()

    function tick() {
      const elapsed = Math.floor((Date.now() - startedAt) / 1000)
      timerSeconds.value = Math.max(0, pickTimerSeconds - elapsed)
      if (timerSeconds.value <= 5 && timerSeconds.value > 0 && isMyTurn.value) {
        playTick(timerSeconds.value)
      }
    }

    tick()
    timerInterval = setInterval(tick, 1000)
  }

  function stopTimer() {
    if (timerInterval) {
      clearInterval(timerInterval)
      timerInterval = null
    }
  }

  function startPreDraftTimer() {
    stopPreDraftTimer()
    const draftStartsAt = draftState.value?.draft_starts_at
    if (!draftStartsAt) return

    function tick() {
      const remaining = Math.floor((new Date(draftStartsAt).getTime() - Date.now()) / 1000)
      preDraftSeconds.value = Math.max(0, remaining)

      // Countdown expired but picks haven't started — poll for the transition
      if (remaining <= 0 && !draftState.value?.picks_started) {
        startCountdownPoll()
      }
    }

    tick()
    preDraftInterval = setInterval(tick, 1000)
  }

  function startCountdownPoll() {
    if (countdownPollInterval || !countdownPollLeagueId) return
    countdownPollInterval = setInterval(async () => {
      await loadDraft(countdownPollLeagueId)
      if (draftState.value?.picks_started) {
        stopCountdownPoll()
        startTimer()
        await loadPool(countdownPollLeagueId)
      }
    }, 3000)
  }

  function stopCountdownPoll() {
    if (countdownPollInterval) {
      clearInterval(countdownPollInterval)
      countdownPollInterval = null
    }
  }

  function stopPreDraftTimer() {
    if (preDraftInterval) {
      clearInterval(preDraftInterval)
      preDraftInterval = null
    }
    stopCountdownPoll()
  }

  function $reset() {
    draftState.value = null
    availablePicks.value = []
    loading.value = false
    error.value = ''
    timerSeconds.value = 0
    preDraftSeconds.value = 0
    pickFeed.value = []
    autoDraftMembers.value = []
    presentMembers.value = []
    stopTimer()
    stopPreDraftTimer()
  }

  return {
    draftState,
    availablePicks,
    loading,
    error,
    timerSeconds,
    isInCountdown,
    isMyTurn,
    myMembershipId,
    myPicks,
    myStarters,
    myBench,
    rosterConfig,
    aggregateOddsFloor,
    starterCount,
    benchSlots,
    myStartersByType,
    unfilledTypes,
    aggregateImpliedProbability,
    aggregateAmerican,
    currentDrafter,
    upcomingDrafters,
    pickFeed,
    autoDraftMembers,
    amInAutoDraft,
    presentMembers,
    preDraftSeconds,
    wouldBustAggregate,
    calcNewAggregate,
    probToAmerican,
    loadDraft,
    loadPool,
    submitPick,
    disableAutoDraft,
    subscribeToDraftChannel,
    unsubscribe,
    $reset,
  }
})
