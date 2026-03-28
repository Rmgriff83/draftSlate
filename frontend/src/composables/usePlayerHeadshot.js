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

export function usePlayerHeadshot(pick) {
  const headshotUrl = ref(null)

  async function load() {
    if (pick.pick_type !== 'player_prop' || !pick.player_name) return

    const league = sportToLeague[pick.sport]
    if (!league) return

    try {
      let mapPromise = cache.get(league)
      if (!mapPromise) {
        mapPromise = api.get(`/api/v1/headshots/${league}`).then(({ data }) => data.players || {})
        cache.set(league, mapPromise)
        mapPromise.catch(() => cache.delete(league))
      }

      const map = await mapPromise
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
