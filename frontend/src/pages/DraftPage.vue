<script setup>
import { onMounted, onUnmounted, ref, computed } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useDraftStore } from '@/stores/draft'
import DraftPickQueue from '@/components/draft/DraftPickQueue.vue'
import DraftPickFeed from '@/components/draft/DraftPickFeed.vue'
import DraftLobby from '@/components/draft/DraftLobby.vue'
import AvailablePicksList from '@/components/draft/AvailablePicksList.vue'
import DraftRosterPanel from '@/components/draft/DraftRosterPanel.vue'
import DraftPickStudy from '@/components/draft/DraftPickStudy.vue'
import DraftOrderDisplay from '@/components/draft/DraftOrderDisplay.vue'
import AutoPickNotice from '@/components/draft/AutoPickNotice.vue'

const route = useRoute()
const router = useRouter()
const draft = useDraftStore()

const leagueId = route.params.id
const selectedPick = ref(null)
const showStudy = ref(false)
const showOrder = ref(false)
const showSlate = ref(true)
const autoPickToast = ref(null)
const pickError = ref('')

const slateDateRange = computed(() => {
  const start = draft.draftState?.started_at ? new Date(draft.draftState.started_at) : new Date()
  const end = new Date(start)
  end.setDate(end.getDate() + 7)
  const fmt = (d) => `${d.getMonth() + 1}/${String(d.getDate()).padStart(2, '0')}`
  return `${fmt(start)} - ${fmt(end)}`
})

const totalSlots = computed(() => draft.starterCount + draft.benchSlots)

function formatOdds(odds) {
  return odds > 0 ? `+${odds}` : `${odds}`
}

const aggregateIsPositive = computed(() => draft.aggregateAmerican >= 100)

function selectPick(pick) {
  if (draft.draftState?.status !== 'active') return
  selectedPick.value = pick
  showStudy.value = true
  pickError.value = ''
}

async function draftFromStudy() {
  if (!selectedPick.value) return
  pickError.value = ''
  const result = await draft.submitPick(leagueId, selectedPick.value.id)
  if (result.success) {
    showStudy.value = false
    selectedPick.value = null
  } else {
    pickError.value = result.message
  }
}

onMounted(async () => {
  await draft.loadDraft(leagueId)
  if (draft.draftState?.status === 'active') {
    await draft.loadPool(leagueId)
  }
  draft.subscribeToDraftChannel(leagueId)
})

onUnmounted(() => {
  draft.unsubscribe(leagueId)
})
</script>

<template>
  <div class="space-y-4 mt-2">
    <!-- Loading -->
    <div v-if="draft.loading && !draft.draftState" class="ds-card p-6 text-center">
      <p class="text-sm text-ds-text-tertiary">Loading draft...</p>
    </div>

    <!-- Error -->
    <div v-else-if="draft.error && !draft.draftState" class="ds-card p-6 text-center">
      <p class="text-sm text-ds-red">{{ draft.error }}</p>
    </div>

    <!-- Lobby (preparing/not started) -->
    <DraftLobby
      v-else-if="!draft.draftState || ['lobby', 'preparing'].includes(draft.draftState.status)"
      :league-id="leagueId"
    />

    <!-- Active Draft -->
    <template v-else-if="draft.draftState?.status === 'active'">
      <DraftPickQueue />
      <DraftPickFeed />

      <AvailablePicksList
        :picks="draft.availablePicks"
        :is-my-turn="draft.isMyTurn"
        @select="selectPick"
      />

      <!-- Spacer for fixed bottom drawer handle -->
      <div class="h-20"></div>
    </template>

    <!-- Completed -->
    <template v-else-if="draft.draftState?.status === 'completed'">
      <div class="ds-card p-6 text-center">
        <svg class="w-12 h-12 text-ds-green mx-auto mb-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
          <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
        </svg>
        <h2 class="text-lg font-bold text-ds-text-primary mb-1">Draft Complete!</h2>
        <p class="text-sm text-ds-text-secondary">All rosters have been filled.</p>
        <button
          @click="router.push(`/app/leagues/${leagueId}`)"
          class="mt-4 px-6 py-2.5 text-sm font-semibold text-white bg-ds-primary hover:bg-ds-primary-light rounded-ds-sm transition-colors"
        >
          View Your Slate
        </button>
      </div>
      <DraftRosterPanel />
    </template>

    <!-- Pick Study Drawer -->
    <DraftPickStudy
      v-if="showStudy && selectedPick"
      :pick="selectedPick"
      :is-my-turn="draft.isMyTurn"
      @draft="draftFromStudy"
      @close="showStudy = false"
    />

    <!-- Draft Order Modal -->
    <DraftOrderDisplay
      v-if="showOrder"
      @close="showOrder = false"
    />

    <!-- Auto-pick toast -->
    <AutoPickNotice :toast="autoPickToast" />

    <!-- Slate Drawer (fixed bottom) -->
    <div
      v-if="draft.draftState?.status === 'active'"
      class="fixed bottom-0 left-0 right-0 z-50 drawer-shadow"
    >
      <!-- Handle -->
      <button
        @click="showSlate = !showSlate"
        class="w-full bg-ds-bg-secondary border-t border-ds-border rounded-t-ds-lg px-4 pt-2.5 pb-3 text-left active:bg-ds-bg-hover transition-colors"
      >
        <div class="w-10 h-1 bg-ds-text-tertiary/30 rounded-full mx-auto mb-2.5"></div>
        <div class="flex items-center justify-between gap-3">
          <div class="flex-1 min-w-0">
            <p class="text-sm font-semibold text-ds-text-primary leading-tight">
              My Slate for {{ slateDateRange }}
            </p>
            <p class="text-[10px] text-ds-text-tertiary mt-0.5">
              {{ draft.myPicks.length }}/{{ totalSlots }} picks
            </p>
          </div>
          <div class="flex items-center gap-2.5 flex-shrink-0">
            <div class="text-right">
              <span
                class="text-lg font-bold font-mono leading-none"
                :class="aggregateIsPositive ? 'text-ds-green' : 'text-ds-text-primary'"
              >{{ draft.myStarters.length ? formatOdds(draft.aggregateAmerican) : '--' }}</span>
              <p class="text-[10px] text-ds-text-tertiary mt-0.5">
                Floor: {{ formatOdds(draft.aggregateOddsFloor) }}
              </p>
            </div>
            <svg
              class="w-4 h-4 text-ds-text-tertiary transition-transform duration-300"
              :class="showSlate ? 'rotate-180' : ''"
              fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"
            >
              <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 15.75l7.5-7.5 7.5 7.5" />
            </svg>
          </div>
        </div>
      </button>

      <!-- Body -->
      <Transition name="drawer-slide">
        <div
          v-if="showSlate"
          class="bg-ds-bg-primary border-t border-ds-border/50 overflow-y-auto"
          style="max-height: 25vh;"
        >
          <div class="px-4 py-3">
            <DraftRosterPanel />
          </div>
        </div>
      </Transition>
    </div>
  </div>
</template>

<style scoped>
.drawer-shadow {
  box-shadow: 0 -4px 20px rgba(0, 0, 0, 0.3);
}
.drawer-slide-enter-active {
  transition: max-height 0.35s cubic-bezier(0.16, 1, 0.3, 1), opacity 0.3s ease;
}
.drawer-slide-leave-active {
  transition: max-height 0.25s cubic-bezier(0.65, 0, 0.35, 1), opacity 0.2s ease;
}
.drawer-slide-enter-from,
.drawer-slide-leave-to {
  max-height: 0 !important;
  opacity: 0;
  overflow: hidden;
}
</style>
