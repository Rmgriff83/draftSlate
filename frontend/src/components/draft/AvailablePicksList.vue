<script setup>
import { ref, computed } from 'vue'
import { Icon } from '@iconify/vue'
import { useDraftStore } from '@/stores/draft'

const props = defineProps({
  picks: { type: Array, default: () => [] },
  isMyTurn: { type: Boolean, default: false },
})

const emit = defineEmits(['select'])

const draft = useDraftStore()

const search = ref('')
const searchFocused = ref(false)
const isSearchActive = computed(() => search.value.length > 0 || searchFocused.value)
const sortBy = ref('odds_high')
const filterType = ref('')
const filterSport = ref('')

const sportLabels = {
  basketball_nba: 'NBA',
  americanfootball_nfl: 'NFL',
  baseball_mlb: 'MLB',
  icehockey_nhl: 'NHL',
}

const availableSports = computed(() => {
  const sports = new Set(props.picks.map((p) => p.sport).filter(Boolean))
  return [...sports]
})

const hasUnfilledStarters = computed(() =>
  Object.keys(draft.unfilledTypes).length > 0
)

const filteredPicks = computed(() => {
  let result = [...props.picks]

  if (search.value) {
    const q = search.value.toLowerCase()
    result = result.filter(
      (p) =>
        p.description?.toLowerCase().includes(q) ||
        p.player_name?.toLowerCase().includes(q) ||
        p.game_display?.toLowerCase().includes(q)
    )
  }

  if (filterType.value) {
    result = result.filter((p) => p.pick_type === filterType.value)
  }

  if (filterSport.value) {
    result = result.filter((p) => p.sport === filterSport.value)
  }

  if (sortBy.value === 'odds_high') {
    result.sort((a, b) => b.snapshot_odds - a.snapshot_odds)
  } else if (sortBy.value === 'odds_low') {
    result.sort((a, b) => a.snapshot_odds - b.snapshot_odds)
  } else if (sortBy.value === 'game_time') {
    result.sort((a, b) => new Date(a.game_time) - new Date(b.game_time))
  } else if (sortBy.value === 'alpha') {
    result.sort((a, b) => a.description.localeCompare(b.description))
  }

  return result
})

const typeLabels = {
  moneyline: 'ML',
  spread: 'Spread',
  total: 'O/U',
  player_prop: 'Prop',
}

const typeBadgeClasses = {
  moneyline: 'bg-blue-500/20 text-blue-400',
  spread: 'bg-purple-500/20 text-purple-400',
  total: 'bg-orange-500/20 text-orange-400',
  player_prop: 'bg-green-500/20 text-green-400',
}

const sportIcons = {
  basketball_nba: 'mdi:basketball',
  americanfootball_nfl: 'mdi:football',
  baseball_mlb: 'mdi:baseball',
  icehockey_nhl: 'mdi:hockey-puck',
}

const sportIconColors = {
  basketball_nba: 'text-orange-400',
  americanfootball_nfl: 'text-amber-600',
  baseball_mlb: 'text-red-400',
  icehockey_nhl: 'text-sky-400',
}

function formatOdds(odds) {
  return odds > 0 ? `+${odds}` : `${odds}`
}

function formatGameTime(gameTime) {
  if (!gameTime) return null
  const d = new Date(gameTime)
  const now = new Date()
  const isToday = d.toDateString() === now.toDateString()
  const tomorrow = new Date(now)
  tomorrow.setDate(tomorrow.getDate() + 1)
  const isTomorrow = d.toDateString() === tomorrow.toDateString()
  const time = d.toLocaleTimeString([], { hour: 'numeric', minute: '2-digit' })
  if (isToday) return `Today ${time}`
  if (isTomorrow) return `Tmrw ${time}`
  const month = d.toLocaleDateString([], { month: 'short', day: 'numeric' })
  return `${month} ${time}`
}

function oddsColor(odds) {
  if (odds >= 100) return 'text-ds-green'
  if (odds >= -150) return 'text-ds-yellow'
  return 'text-ds-text-primary'
}

function isMatchingType(pick) {
  if (!hasUnfilledStarters.value) return true
  return !!draft.unfilledTypes[pick.pick_type]
}

function aggregateImpact(pick) {
  if (!hasUnfilledStarters.value) return null
  if (!isMatchingType(pick)) return null
  const newAvg = draft.calcNewAggregate(pick.snapshot_odds)
  return draft.probToAmerican(newAvg)
}

function wouldBust(pick) {
  if (!hasUnfilledStarters.value) return false
  if (!isMatchingType(pick)) return false
  return draft.wouldBustAggregate(pick.snapshot_odds)
}
</script>

<template>
  <div class="space-y-3">
    <div class="flex items-stretch justify-between gap-1">
      <input
        v-model="search"
        @focus="searchFocused = true"
        @blur="searchFocused = false"
        type="text"
        :placeholder="`Search ${filteredPicks.length} picks...`"
        class="flex-1 pl-2.5 pr-4 text-xs bg-ds-bg-secondary border border-ds-border rounded text-ds-text-primary placeholder-ds-text-tertiary focus:outline-none focus:ring-1 focus:ring-ds-primary/50"
      />
      <div v-if="!isSearchActive" class="flex items-center gap-1">
        <select
          v-if="availableSports.length > 1"
          v-model="filterSport"
          class="px-2 py-1 text-[10px] bg-ds-bg-secondary border border-ds-border rounded text-ds-text-secondary focus:outline-none"
        >
          <option value="">All Sports</option>
          <option v-for="s in availableSports" :key="s" :value="s">{{ sportLabels[s] || s }}</option>
        </select>
        <select
          v-model="filterType"
          class="px-2 py-1 text-[10px] bg-ds-bg-secondary border border-ds-border rounded text-ds-text-secondary focus:outline-none"
        >
          <option value="">All Types</option>
          <option value="player_prop">Props</option>
          <option value="moneyline">ML</option>
          <option value="spread">Spread</option>
          <option value="total">Total</option>
        </select>
        <select
          v-model="sortBy"
          class="px-2 py-1 text-[10px] bg-ds-bg-secondary border border-ds-border rounded text-ds-text-secondary focus:outline-none"
        >
          <option value="odds_high">Odds High-Low</option>
          <option value="odds_low">Odds Low-High</option>
          <option value="game_time">By Game</option>
          <option value="alpha">A-Z</option>
        </select>
      </div>
    </div>

    <!-- Picks list -->
    <div class="relative">
      <div class="space-y-1.5 max-h-[40vh] overflow-y-auto">
        <button
          v-for="pick in filteredPicks"
          :key="pick.id"
          @click="$emit('select', pick)"
          :disabled="wouldBust(pick)"
          class="w-full ds-card p-3 my-1 text-left transition-all duration-ds-fast"
          :class="[
            wouldBust(pick) ? 'opacity-40 cursor-not-allowed' :
            !isMyTurn ? 'opacity-80 cursor-pointer hover:ring-1 hover:ring-ds-border' :
            'hover:ring-2 hover:ring-ds-primary/50 cursor-pointer',
            hasUnfilledStarters && !isMatchingType(pick) ? 'opacity-50' : ''
          ]"
        >
          <div class="flex items-start justify-between gap-2">
            <div class="flex-1 min-w-0">
              <p class="text-sm font-medium text-ds-text-primary truncate">{{ pick.description }}</p>
              <div class="flex items-center gap-1.5 mt-0.5">
                <Icon
                  v-if="sportIcons[pick.sport]"
                  :icon="sportIcons[pick.sport]"
                  class="w-3.5 h-3.5 flex-shrink-0"
                  :class="sportIconColors[pick.sport] || 'text-ds-text-tertiary'"
                />
                <span
                  class="text-[10px] font-bold px-1.5 py-0.5 rounded"
                  :class="typeBadgeClasses[pick.pick_type] || 'bg-ds-bg-hover text-ds-text-tertiary'"
                >{{ typeLabels[pick.pick_type] || pick.pick_type }}</span>
                <p class="text-xs text-ds-text-tertiary truncate">
                  {{ pick.game_display }}
                  <span v-if="formatGameTime(pick.game_time)" class="text-ds-text-tertiary/70"> · {{ formatGameTime(pick.game_time) }}</span>
                </p>
                <span
                  v-if="hasUnfilledStarters && !isMatchingType(pick)"
                  class="text-[10px] font-bold px-1.5 py-0.5 rounded bg-ds-bg-hover text-ds-text-tertiary"
                >bench</span>
              </div>
              <!-- Aggregate impact -->
              <p
                v-if="isMyTurn && hasUnfilledStarters && isMatchingType(pick) && aggregateImpact(pick) !== null"
                class="text-[10px] mt-0.5"
                :class="wouldBust(pick) ? 'text-red-400' : 'text-ds-text-tertiary'"
              >
                Avg after: {{ formatOdds(aggregateImpact(pick)) }}
                <span v-if="wouldBust(pick)"> (exceeds floor)</span>
              </p>
            </div>
            <span class="text-sm font-mono font-bold flex-shrink-0" :class="oddsColor(pick.snapshot_odds)">
              {{ formatOdds(pick.snapshot_odds) }}
            </span>
          </div>
        </button>

        <p v-if="!filteredPicks.length" class="text-center text-xs text-ds-text-tertiary py-4">
          No picks match your filters.
        </p>
      </div>
      <!-- Bottom fade gradient -->
      <div
        v-if="filteredPicks.length > 3"
        class="pointer-events-none absolute bottom-0 left-0 right-0 h-16 bg-gradient-to-t from-ds-bg-primary to-transparent"
      ></div>
    </div>
  </div>
</template>

<style scoped>
.search-expand {
  transition: flex 0.3s cubic-bezier(0.16, 1, 0.3, 1), width 0.3s cubic-bezier(0.16, 1, 0.3, 1);
}
</style>
