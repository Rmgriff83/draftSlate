import { computed } from 'vue'
import { useAuthStore } from '@/stores/auth'
import { useLeagueStore } from '@/stores/league'

export function useLeagueRules() {
  const auth = useAuthStore()
  const leagueStore = useLeagueStore()

  const maxLeagues = computed(() => auth.user?.max_leagues ?? 5)

  const activeLeagueCount = computed(() =>
    leagueStore.myLeagues.filter((l) => l.state !== 'cancelled').length
  )

  const canCreateLeague = computed(() => activeLeagueCount.value < maxLeagues.value)

  const canJoinLeague = computed(() => activeLeagueCount.value < maxLeagues.value)

  return {
    maxLeagues,
    activeLeagueCount,
    canCreateLeague,
    canJoinLeague,
  }
}
