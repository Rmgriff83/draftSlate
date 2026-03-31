import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import api from '@/utils/api'
import echo from '@/utils/echo'
import { useSlateHelpers } from '@/composables/useSlateHelpers'
import { useAuthStore } from '@/stores/auth'

export const useSlateStore = defineStore('slate', () => {
  const { aggregateImpliedProb } = useSlateHelpers()

  const myPicks = ref([])
  const myMatchup = ref(null)
  const standings = ref([])
  const weekMatchups = ref([])
  const currentWeek = ref(1)
  const loading = ref(false)
  const error = ref('')
  const bracketData = ref(null)
  const payoutData = ref(null)
  let channel = null

  // Callbacks for real-time updates in external components (e.g. matchup detail modal)
  const onScoresUpdated = ref(null)
  const onPickGraded = ref(null)
  const onMatchupScored = ref(null)

  const starters = computed(() =>
    myPicks.value.filter((p) => p.position === 'starter')
  )

  const bench = computed(() =>
    myPicks.value.filter((p) => p.position === 'bench')
  )

  const lockedCount = computed(() =>
    starters.value.filter((p) => p.is_locked).length
  )

  const hitCount = computed(() =>
    starters.value.filter((p) => p.pick_selection?.outcome === 'hit').length
  )

  const missCount = computed(() =>
    starters.value.filter((p) => p.pick_selection?.outcome === 'miss').length
  )

  const statusLine = computed(() => {
    const locked = lockedCount.value
    const total = starters.value.length
    const pending = total - locked
    return `${locked} of ${total} starters locked · ${pending} pending`
  })

  const myScore = computed(() => {
    if (!myMatchup.value) return 0
    const picks = (myMatchup.value.my_picks || []).filter(p => p.position === 'starter')
    let score = picks.filter(p => p.pick_selection?.outcome === 'hit').length
    score += oddsBonus.value.my
    return score
  })

  const opponentScore = computed(() => {
    if (!myMatchup.value) return 0
    const picks = (myMatchup.value.opponent_picks || []).filter(p => p.position === 'starter')
    let score = picks.filter(p => p.pick_selection?.outcome === 'hit').length
    score += oddsBonus.value.opponent
    return score
  })

  const oddsBonus = computed(() => {
    if (!myMatchup.value) return { my: 0, opponent: 0 }

    const myStarters = (myMatchup.value.my_picks || []).filter(p => p.position === 'starter')
    const oppStarters = (myMatchup.value.opponent_picks || []).filter(p => p.position === 'starter')

    // All starters on BOTH sides must be locked
    const allMyLocked = myStarters.length > 0 && myStarters.every(p => p.is_locked)
    const allOppLocked = oppStarters.length > 0 && oppStarters.every(p => p.is_locked)

    if (!allMyLocked || !allOppLocked) return { my: 0, opponent: 0 }

    const myOdds = myStarters.map(p => p.locked_odds).filter(o => o != null)
    const oppOdds = oppStarters.map(p => p.locked_odds).filter(o => o != null)

    if (myOdds.length === 0 || oppOdds.length === 0) return { my: 0, opponent: 0 }

    const myProb = aggregateImpliedProb(myOdds)
    const oppProb = aggregateImpliedProb(oppOdds)

    // Lower probability = riskier = wins the point
    if (Math.abs(myProb - oppProb) < 0.0001) return { my: 0, opponent: 0 }
    return myProb < oppProb ? { my: 1, opponent: 0 } : { my: 0, opponent: 1 }
  })

  async function fetchSummary(leagueId, week) {
    const isNewData = myPicks.value.length === 0 || currentWeek.value !== week
    if (isNewData) loading.value = true
    error.value = ''
    try {
      const res = await api.get(`/api/v1/leagues/${leagueId}/slate/${week}/summary`)
      const data = res.data.data
      myPicks.value = data.picks || []
      myMatchup.value = data.matchup || null
      standings.value = data.standings || []
      currentWeek.value = week
    } catch (err) {
      if (isNewData) {
        error.value = err.response?.data?.message || 'Failed to load league data'
        myPicks.value = []
        myMatchup.value = null
        standings.value = []
      }
    } finally {
      loading.value = false
    }
  }

  async function fetchSlate(leagueId, week) {
    try {
      const res = await api.get(`/api/v1/leagues/${leagueId}/slate/${week}`)
      myPicks.value = res.data.data.picks || []
      currentWeek.value = week
    } catch (err) {
      // silent — used for post-swap refresh
    }
  }

  async function swapPick(leagueId, pickId, targetPosition, targetSlot, targetSlotType) {
    error.value = ''
    try {
      await api.post(`/api/v1/leagues/${leagueId}/slate/swap`, {
        pick_id: pickId,
        target_position: targetPosition,
        target_slot: targetSlot,
        target_slot_type: targetSlotType || null,
      })
      await fetchSummary(leagueId, currentWeek.value)
      return { success: true }
    } catch (err) {
      error.value = err.response?.data?.message || 'Swap failed'
      return { success: false, message: error.value }
    }
  }

  async function fetchBracket(leagueId) {
    try {
      const res = await api.get(`/api/v1/leagues/${leagueId}/playoffs/bracket`)
      bracketData.value = res.data.data
    } catch {
      // silent
    }
  }

  async function fetchPayouts(leagueId) {
    try {
      const res = await api.get(`/api/v1/leagues/${leagueId}/playoffs/payouts`)
      payoutData.value = res.data.data
    } catch {
      // silent
    }
  }

  async function fetchWeekMatchups(leagueId, week) {
    try {
      const res = await api.get(`/api/v1/leagues/${leagueId}/matchups/${week}`)
      weekMatchups.value = res.data.data || []
    } catch {
      weekMatchups.value = []
    }
  }

  async function refreshOdds(leagueId) {
    try {
      await api.post(`/api/v1/leagues/${leagueId}/slate/refresh-odds`)
      await fetchSlate(leagueId, currentWeek.value)
    } catch (err) {
      // silently fail
    }
  }

  function subscribeToLeagueChannel(leagueId) {
    if (channel) {
      echo.leave(`league.${leagueId}`)
    }

    channel = echo.private(`league.${leagueId}`)
      .listen('.PickGraded', (e) => {
        // Update the pick's outcome in local state
        const pick = myPicks.value.find(
          (p) => p.pick_selection?.id === e.pick_selection_id
        )
        if (pick && pick.pick_selection) {
          pick.pick_selection.outcome = e.outcome
        }
        // Notify external listeners (e.g. matchup detail modal)
        if (onPickGraded.value) onPickGraded.value(e)
      })
      .listen('.ScoresUpdated', (e) => {
        // Merge live score data into local picks
        for (const update of e.picks || []) {
          const pick = myPicks.value.find(
            (p) => p.pick_selection?.id === update.pick_selection_id
          )
          if (pick && pick.pick_selection) {
            pick.pick_selection.result_data = update.result_data
          }
        }
        // Notify external listeners
        if (onScoresUpdated.value) onScoresUpdated.value(e)
      })
      .listen('.MatchupScored', (e) => {
        if (myMatchup.value && myMatchup.value.id === e.matchup_id) {
          myMatchup.value.home_score = e.scores.home
          myMatchup.value.away_score = e.scores.away
          myMatchup.value.winner_id = e.winner_id
          myMatchup.value.status = 'completed'
        }
        // Update weekMatchups card scores
        const wm = weekMatchups.value.find(m => m.id === e.matchup_id)
        if (wm) {
          wm.home_score = e.scores.home
          wm.away_score = e.scores.away
          wm.winner_id = e.winner_id
          wm.status = 'completed'
        }
        // Notify external listeners
        if (onMatchupScored.value) onMatchupScored.value(e)
      })
      .listen('.StandingsUpdated', (e) => {
        const auth = useAuthStore()
        standings.value = (e.standings || []).map(s => ({
          ...s,
          is_current_user: s.user_id === auth.user?.id,
        }))
      })
  }

  function unsubscribe(leagueId) {
    if (channel) {
      echo.leave(`league.${leagueId}`)
      channel = null
    }
  }

  function $reset() {
    myPicks.value = []
    myMatchup.value = null
    standings.value = []
    weekMatchups.value = []
    currentWeek.value = 1
    loading.value = false
    error.value = ''
    bracketData.value = null
    payoutData.value = null
    onScoresUpdated.value = null
    onPickGraded.value = null
    onMatchupScored.value = null
  }

  return {
    myPicks,
    myMatchup,
    standings,
    weekMatchups,
    currentWeek,
    loading,
    error,
    bracketData,
    payoutData,
    starters,
    bench,
    lockedCount,
    hitCount,
    missCount,
    statusLine,
    myScore,
    opponentScore,
    oddsBonus,
    onScoresUpdated,
    onPickGraded,
    onMatchupScored,
    fetchSummary,
    fetchSlate,
    fetchWeekMatchups,
    fetchBracket,
    fetchPayouts,
    swapPick,
    refreshOdds,
    subscribeToLeagueChannel,
    unsubscribe,
    $reset,
  }
})
