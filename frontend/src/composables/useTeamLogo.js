import { ref } from 'vue'
import api from '@/utils/api'

const sportToLeague = {
  basketball_nba: 'nba',
  baseball_mlb: 'mlb',
  icehockey_nhl: 'nhl',
  americanfootball_nfl: 'nfl',
}

// Module-level cache — stores Promises to deduplicate concurrent calls
const cache = new Map()

export function useTeamLogo(pick) {
  const homeLogoUrl = ref(null)
  const awayLogoUrl = ref(null)

  async function load() {
    if (pick.pick_type === 'player_prop') return
    if (!pick.home_team && !pick.away_team) return

    const league = sportToLeague[pick.sport]
    if (!league) return

    try {
      let mapPromise = cache.get(league)
      if (!mapPromise) {
        mapPromise = api.get(`/api/v1/logos/${league}`).then(({ data }) => data.teams || {})
        cache.set(league, mapPromise)
        mapPromise.catch(() => cache.delete(league))
      }

      const map = await mapPromise
      if (pick.home_team && map[pick.home_team]?.url) {
        homeLogoUrl.value = map[pick.home_team].url
      }
      if (pick.away_team && map[pick.away_team]?.url) {
        awayLogoUrl.value = map[pick.away_team].url
      }
    } catch {
      // Silently ignore — team logos are optional enhancement
    }
  }

  return { homeLogoUrl, awayLogoUrl, loadTeamLogos: load }
}
