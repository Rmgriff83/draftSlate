import { ref } from 'vue'
import api from '@/utils/api'

const sportToLeague = {
  basketball_nba: 'nba',
  baseball_mlb: 'mlb',
  icehockey_nhl: 'nhl',
  americanfootball_nfl: 'nfl',
}

// Module-level cache — shared across all components, fetched once per league per page session
const cache = new Map()

export function usePlayerHeadshot(pick) {
  const headshotUrl = ref(null)

  async function load() {
    if (pick.pick_type !== 'player_prop' || !pick.player_name) return

    const league = sportToLeague[pick.sport]
    if (!league) return

    try {
      let map = cache.get(league)
      if (!map) {
        const { data } = await api.get(`/api/v1/headshots/${league}`)
        map = data.players || {}
        cache.set(league, map)
      }

      const player = map[pick.player_name]
      if (player?.url) {
        headshotUrl.value = player.url
      }
    } catch {
      // Silently ignore — headshot is optional enhancement
    }
  }

  return { headshotUrl, loadHeadshot: load }
}
