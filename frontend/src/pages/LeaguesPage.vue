<script setup>
import { onMounted, ref, computed } from 'vue'
import { useRouter } from 'vue-router'
import { useLeagueStore } from '@/stores/league'

const router = useRouter()
const leagueStore = useLeagueStore()

const deleteTarget = ref(null)

function promptDelete(league) {
  deleteTarget.value = league
}

async function confirmDelete() {
  if (!deleteTarget.value) return
  const result = await leagueStore.cancelLeague(deleteTarget.value.id)
  if (!result.success) {
    alert(result.message)
  }
  deleteTarget.value = null
}

const activeLeagues = computed(() =>
  leagueStore.myLeagues.filter((l) => ['pending', 'active', 'playoffs'].includes(l.state))
)

const completedLeagues = computed(() =>
  leagueStore.myLeagues.filter((l) => l.state === 'completed')
)

function stateLabel(state) {
  const labels = { pending: 'Forming', active: 'Active', playoffs: 'Playoffs', completed: 'Done' }
  return labels[state] || state
}

function stateColor(state) {
  const colors = {
    pending: 'bg-ds-yellow/20 text-ds-yellow',
    active: 'bg-ds-green/20 text-ds-green',
    playoffs: 'bg-ds-primary/20 text-ds-primary',
    completed: 'bg-ds-text-tertiary/20 text-ds-text-tertiary',
  }
  return colors[state] || ''
}

onMounted(() => {
  if (!leagueStore.myLeagues.length) {
    leagueStore.fetchMyLeagues()
  }
})
</script>

<template>
  <div class="space-y-6">
    <div class="flex items-center justify-between">
      <h1 class="text-xl font-bold text-ds-text-primary">My Leagues</h1>
      <div class="flex gap-2">
        <button
          @click="router.push('/app/leagues/browse')"
          class="px-3 py-1.5 text-xs font-medium text-ds-text-secondary bg-ds-bg-hover rounded-ds-sm hover:bg-ds-border transition-colors duration-ds-fast"
        >
          Browse
        </button>
        <button
          @click="router.push('/app/leagues/create')"
          class="px-3 py-1.5 text-xs font-semibold text-white bg-ds-primary hover:bg-ds-primary-light rounded-ds-sm transition-colors duration-ds-fast"
        >
          + Create
        </button>
      </div>
    </div>

    <!-- Loading -->
    <div v-if="leagueStore.loading" class="ds-card p-6 text-center">
      <p class="text-sm text-ds-text-tertiary">Loading leagues...</p>
    </div>

    <!-- Active Leagues -->
    <template v-else-if="activeLeagues.length || completedLeagues.length">
      <div v-if="activeLeagues.length">
        <h2 class="text-sm font-semibold text-ds-text-secondary uppercase tracking-wide mb-3">Active Leagues</h2>
        <div class="space-y-3">
          <div
            v-for="league in activeLeagues"
            :key="league.id"
            class="ds-card-interactive p-4 cursor-pointer"
            @click="router.push(`/app/leagues/${league.id}`)"
          >
            <div class="flex items-center justify-between">
              <div>
                <h3 class="text-sm font-semibold text-ds-text-primary">{{ league.name }}</h3>
                <p class="text-xs text-ds-text-tertiary mt-0.5">{{ league.member_count }}/{{ league.max_teams }} teams &middot; ${{ league.buy_in }}</p>
              </div>
              <div class="flex items-center gap-2">
                <span class="text-[10px] font-medium px-1.5 py-0.5 rounded-full" :class="stateColor(league.state)">
                  {{ stateLabel(league.state) }}
                </span>
                <button
                  v-if="league.is_commissioner && league.state === 'pending'"
                  @click.stop="promptDelete(league)"
                  class="p-1 rounded text-ds-text-tertiary hover:text-ds-red hover:bg-ds-red/10 transition-colors duration-ds-fast"
                  title="Delete league"
                >
                  <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0" />
                  </svg>
                </button>
              </div>
            </div>
          </div>
        </div>
      </div>

      <div v-if="completedLeagues.length">
        <h2 class="text-sm font-semibold text-ds-text-secondary uppercase tracking-wide mb-3">Completed Leagues</h2>
        <div class="space-y-3">
          <div
            v-for="league in completedLeagues"
            :key="league.id"
            class="ds-card-interactive p-4 cursor-pointer"
            @click="router.push(`/app/leagues/${league.id}`)"
          >
            <div class="flex items-center justify-between">
              <div>
                <h3 class="text-sm font-semibold text-ds-text-primary">{{ league.name }}</h3>
                <p class="text-xs text-ds-text-tertiary mt-0.5">{{ league.member_count }} teams &middot; ${{ league.buy_in }}</p>
              </div>
              <span class="text-[10px] font-medium px-1.5 py-0.5 rounded-full" :class="stateColor(league.state)">
                {{ stateLabel(league.state) }}
              </span>
            </div>
          </div>
        </div>
      </div>
    </template>

    <!-- Empty state -->
    <div v-else class="ds-card p-6 text-center">
      <p class="text-ds-text-secondary mb-1">No leagues yet.</p>
      <p class="text-sm text-ds-text-tertiary mb-4">Browse public leagues or create your own!</p>
      <div class="flex justify-center gap-3">
        <button
          @click="router.push('/app/leagues/browse')"
          class="px-4 py-2 text-sm font-medium text-ds-text-secondary bg-ds-bg-hover rounded-ds-sm hover:bg-ds-border transition-colors duration-ds-fast"
        >
          Browse Leagues
        </button>
        <button
          @click="router.push('/app/leagues/create')"
          class="px-4 py-2 text-sm font-semibold text-white bg-ds-primary hover:bg-ds-primary-light rounded-ds-sm transition-colors duration-ds-fast"
        >
          Create League
        </button>
      </div>
    </div>

    <!-- Delete Confirmation Dialog -->
    <Teleport to="body">
      <Transition
        enter-active-class="transition ease-ds-out duration-200"
        enter-from-class="opacity-0"
        enter-to-class="opacity-100"
        leave-active-class="transition ease-ds-out duration-150"
        leave-from-class="opacity-100"
        leave-to-class="opacity-0"
      >
        <div v-if="deleteTarget" class="fixed inset-0 z-50 flex items-center justify-center px-4">
          <div class="absolute inset-0 bg-black/60" @click="deleteTarget = null"></div>
          <div class="relative bg-ds-bg-secondary border border-ds-border rounded-ds shadow-ds-lg p-5 w-full max-w-xs">
            <h3 class="text-sm font-bold text-ds-text-primary mb-2">Delete League?</h3>
            <p class="text-xs text-ds-text-secondary mb-4">
              Are you sure you want to delete <span class="font-semibold text-ds-text-primary">{{ deleteTarget.name }}</span>? This cannot be undone.
            </p>
            <div class="flex gap-2 justify-end">
              <button
                @click="deleteTarget = null"
                class="px-3 py-1.5 text-xs font-medium text-ds-text-secondary bg-ds-bg-hover rounded-ds-sm hover:bg-ds-border transition-colors duration-ds-fast"
              >
                Cancel
              </button>
              <button
                @click="confirmDelete"
                class="px-3 py-1.5 text-xs font-semibold text-white bg-ds-red hover:bg-ds-red/80 rounded-ds-sm transition-colors duration-ds-fast"
              >
                Delete
              </button>
            </div>
          </div>
        </div>
      </Transition>
    </Teleport>
  </div>
</template>
