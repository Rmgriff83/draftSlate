import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import api from '@/utils/api'
import echo from '@/utils/echo'

export const useSlateStore = defineStore('slate', () => {
  const myPicks = ref([])
  const myMatchup = ref(null)
  const standings = ref([])
  const currentWeek = ref(1)
  const loading = ref(false)
  const error = ref('')
  let channel = null

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
    return picks.filter(p => p.pick_selection?.outcome === 'hit').length
  })

  const opponentScore = computed(() => {
    if (!myMatchup.value) return 0
    const picks = (myMatchup.value.opponent_picks || []).filter(p => p.position === 'starter')
    return picks.filter(p => p.pick_selection?.outcome === 'hit').length
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
      await fetchSlate(leagueId, currentWeek.value)
      return { success: true }
    } catch (err) {
      error.value = err.response?.data?.message || 'Swap failed'
      return { success: false, message: error.value }
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
      })
      .listen('.MatchupScored', (e) => {
        if (myMatchup.value && myMatchup.value.id === e.matchup_id) {
          myMatchup.value.home_score = e.scores.home
          myMatchup.value.away_score = e.scores.away
          myMatchup.value.winner_id = e.winner_id
          myMatchup.value.status = 'completed'
        }
      })
      .listen('.StandingsUpdated', (e) => {
        standings.value = e.standings || []
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
    currentWeek.value = 1
    loading.value = false
    error.value = ''
  }

  return {
    myPicks,
    myMatchup,
    standings,
    currentWeek,
    loading,
    error,
    starters,
    bench,
    lockedCount,
    hitCount,
    missCount,
    statusLine,
    myScore,
    opponentScore,
    fetchSummary,
    fetchSlate,
    swapPick,
    refreshOdds,
    subscribeToLeagueChannel,
    unsubscribe,
    $reset,
  }
})
