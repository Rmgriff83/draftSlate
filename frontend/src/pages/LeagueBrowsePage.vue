<script setup>
import { ref, onMounted } from 'vue'
import { useLeagueStore } from '@/stores/league'
import { useLeagueRules } from '@/composables/useLeagueRules'
import JoinLeagueModal from '@/components/leagues/JoinLeagueModal.vue'

const leagueStore = useLeagueStore()
const { canJoinLeague } = useLeagueRules()

const search = ref('')
const sport = ref('')
const buyInRange = ref('')
const hasSpots = ref(false)

const showJoinModal = ref(false)
const selectedLeague = ref(null)
const joinError = ref('')

function buildFilters() {
  const filters = {}
  if (search.value) filters.search = search.value
  if (sport.value) filters.sport = sport.value
  if (buyInRange.value === 'low') { filters.buy_in_min = 5; filters.buy_in_max = 25 }
  else if (buyInRange.value === 'mid') { filters.buy_in_min = 25; filters.buy_in_max = 100 }
  else if (buyInRange.value === 'high') { filters.buy_in_min = 100 }
  if (hasSpots.value) filters.has_spots = true
  return filters
}

function applyFilters() {
  leagueStore.fetchBrowseLeagues(buildFilters())
}

function loadMore() {
  const page = (leagueStore.pagination?.current_page || 1) + 1
  leagueStore.fetchBrowseLeagues({ ...buildFilters(), page })
}

function openJoinModal(league) {
  if (!canJoinLeague.value) {
    joinError.value = 'You have reached your maximum number of leagues.'
    return
  }
  selectedLeague.value = league
  showJoinModal.value = true
  joinError.value = ''
}

async function handleJoin(teamName) {
  const result = await leagueStore.joinLeague(selectedLeague.value.id, teamName)
  if (result.success) {
    showJoinModal.value = false
    selectedLeague.value = null
    applyFilters()
  } else {
    joinError.value = result.message
  }
}

onMounted(() => {
  applyFilters()
})
</script>

<template>
  <div class="space-y-4">
    <h1 class="text-xl font-bold text-ds-text-primary">Browse Leagues</h1>

    <!-- Search -->
    <div class="relative">
      <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-ds-text-tertiary" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
        <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z" />
      </svg>
      <input
        v-model="search"
        type="text"
        placeholder="Search leagues..."
        class="w-full pl-10 pr-4 py-2 bg-ds-bg-secondary border border-ds-border rounded-ds-sm text-sm text-ds-text-primary placeholder-ds-text-tertiary focus:outline-none focus:ring-2 focus:ring-ds-primary/50"
        @keyup.enter="applyFilters"
      />
    </div>

    <!-- Filter chips -->
    <div class="flex gap-2 flex-wrap">
      <select
        v-model="sport"
        @change="applyFilters"
        class="px-3 py-1.5 text-xs bg-ds-bg-secondary border border-ds-border rounded-full text-ds-text-secondary focus:outline-none"
      >
        <option value="">All Sports</option>
        <option value="nfl">NFL</option>
        <option value="nba">NBA</option>
        <option value="mlb">MLB</option>
      </select>

      <select
        v-model="buyInRange"
        @change="applyFilters"
        class="px-3 py-1.5 text-xs bg-ds-bg-secondary border border-ds-border rounded-full text-ds-text-secondary focus:outline-none"
      >
        <option value="">Any Buy-in</option>
        <option value="low">$5 – $25</option>
        <option value="mid">$25 – $100</option>
        <option value="high">$100+</option>
      </select>

      <button
        @click="hasSpots = !hasSpots; applyFilters()"
        class="px-3 py-1.5 text-xs rounded-full border transition-colors duration-ds-fast"
        :class="hasSpots ? 'bg-ds-primary/20 border-ds-primary text-ds-primary' : 'bg-ds-bg-secondary border-ds-border text-ds-text-secondary'"
      >
        Open Spots
      </button>
    </div>

    <!-- Error -->
    <p v-if="joinError" class="text-sm text-ds-red">{{ joinError }}</p>

    <!-- Loading -->
    <div v-if="leagueStore.loading && !leagueStore.browseLeagues.length" class="ds-card p-6 text-center">
      <p class="text-sm text-ds-text-tertiary">Loading leagues...</p>
    </div>

    <!-- Results -->
    <div v-else-if="leagueStore.browseLeagues.length" class="space-y-3">
      <div
        v-for="league in leagueStore.browseLeagues"
        :key="league.id"
        class="ds-card p-4"
      >
        <div class="flex items-start justify-between">
          <div class="flex-1 min-w-0">
            <h3 class="text-sm font-semibold text-ds-text-primary truncate">{{ league.name }}</h3>
            <p class="text-xs text-ds-text-tertiary mt-0.5">
              by {{ league.commissioner?.display_name }} &middot;
              ${{ league.buy_in }} buy-in &middot;
              {{ league.max_teams - league.member_count }} spots left
            </p>
          </div>
          <button
            v-if="!league.is_member"
            @click="openJoinModal(league)"
            class="ml-3 px-3 py-1.5 text-xs font-semibold text-white bg-ds-primary hover:bg-ds-primary-light rounded-ds-sm transition-colors duration-ds-fast flex-shrink-0"
          >
            Join
          </button>
          <span v-else class="ml-3 px-3 py-1.5 text-xs font-medium text-ds-green flex-shrink-0">
            Joined
          </span>
        </div>
      </div>

      <!-- Load more -->
      <button
        v-if="leagueStore.pagination?.current_page < leagueStore.pagination?.last_page"
        @click="loadMore"
        :disabled="leagueStore.loading"
        class="w-full py-2 text-sm font-medium text-ds-primary hover:text-ds-primary-light transition-colors duration-ds-fast"
      >
        {{ leagueStore.loading ? 'Loading...' : 'Load more' }}
      </button>
    </div>

    <!-- Empty -->
    <div v-else class="ds-card p-6 text-center">
      <p class="text-sm text-ds-text-tertiary">No leagues found. Try adjusting your filters.</p>
    </div>

    <!-- Join Modal -->
    <JoinLeagueModal
      v-if="showJoinModal && selectedLeague"
      :league="selectedLeague"
      :loading="leagueStore.loading"
      @confirm="handleJoin"
      @close="showJoinModal = false"
    />
  </div>
</template>
