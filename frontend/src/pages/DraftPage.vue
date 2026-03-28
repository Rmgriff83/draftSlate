<script setup>
import { onMounted, onUnmounted, ref, computed, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useDraftStore } from '@/stores/draft'
import { useLeagueStore } from '@/stores/league'
import DraftPickQueue from '@/components/draft/DraftPickQueue.vue'
import DraftPickFeed from '@/components/draft/DraftPickFeed.vue'
import DraftLobby from '@/components/draft/DraftLobby.vue'
import AvailablePicksList from '@/components/draft/AvailablePicksList.vue'
import DraftRosterPanel from '@/components/draft/DraftRosterPanel.vue'
import DraftPickStudy from '@/components/draft/DraftPickStudy.vue'
import DraftOrderDisplay from '@/components/draft/DraftOrderDisplay.vue'
import AutoPickNotice from '@/components/draft/AutoPickNotice.vue'
import AutoDraftBanner from '@/components/draft/AutoDraftBanner.vue'

const route = useRoute()
const router = useRouter()
const draft = useDraftStore()
const leagueStore = useLeagueStore()

const leagueId = route.params.id
const selectedPick = ref(null)
const showStudy = ref(false)
const showOrder = ref(false)
const showSlate = ref(false)
const autoPickToast = ref(null)
const pickError = ref('')

const slateDateLabel = computed(() => {
  const days = draft.draftState?.matchup_duration_days ?? 7
  const start = draft.draftState?.started_at ? new Date(draft.draftState.started_at) : new Date()
  const fmt = (d) => `${d.getMonth() + 1}/${String(d.getDate()).padStart(2, '0')}`

  if (days === 1) {
    const now = new Date()
    if (start.toDateString() === now.toDateString()) return 'Today'
    const tomorrow = new Date(now)
    tomorrow.setDate(tomorrow.getDate() + 1)
    if (start.toDateString() === tomorrow.toDateString()) return 'Tomorrow'
    return fmt(start)
  }

  const end = new Date(start)
  end.setDate(end.getDate() + days)
  return `${fmt(start)} - ${fmt(end)}`
})

const typeLabels = {
  moneyline: 'ML',
  spread: 'Spread',
  total: 'O/U',
  player_prop: 'Prop',
}

const pickBreakdown = computed(() => {
  const config = draft.rosterConfig || {}
  const byType = draft.myStartersByType || {}
  const parts = []
  for (const [type, count] of Object.entries(config)) {
    const filled = byType[type]?.filled?.length || 0
    const label = typeLabels[type] || type
    parts.push(`${filled}/${count} ${label}`)
  }
  return parts.join('  ·  ')
})

function formatOdds(odds) {
  return odds > 0 ? `+${odds}` : `${odds}`
}

const aggregateIsPositive = computed(() => draft.aggregateAmerican >= 100)

// Countdown helpers
const countdownMinutes = computed(() => Math.floor(draft.preDraftSeconds / 60))
const countdownSecs = computed(() => draft.preDraftSeconds % 60)
function pad(n) {
  return String(n).padStart(2, '0')
}

function initials(name) {
  if (!name) return '?'
  return name.split(' ').map((w) => w[0]).join('').toUpperCase().slice(0, 2)
}

const members = computed(() => draft.draftState?.members || [])

function isPresent(member) {
  return draft.presentMembers.some((u) => u.id === member.id)
}

function selectPick(pick) {
  const status = draft.draftState?.status
  if (status !== 'active') return
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

// Load pool when draft transitions to active (covers both WebSocket and polling paths)
watch(
  () => draft.draftState?.status,
  (status) => {
    if (status === 'active' && !draft.availablePicks.length) {
      draft.loadPool(leagueId)
    }
  }
)

onMounted(async () => {
  if (!leagueStore.currentLeague || leagueStore.currentLeague.id !== Number(leagueId)) {
    await leagueStore.fetchLeague(leagueId)
  }
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

    <!-- Lobby (not started) -->
    <DraftLobby
      v-else-if="!draft.draftState || draft.draftState.status === 'lobby'"
      :league-id="leagueId"
    />

    <!-- Active Draft (includes countdown study period) -->
    <template v-else-if="draft.draftState?.status === 'active'">
      <!-- 12-minute countdown banner during study period -->
      <div v-if="draft.isInCountdown" class="ds-card p-6 text-center space-y-4">
        <p class="text-xs text-ds-text-tertiary uppercase tracking-wide">Picks begin in</p>
        <p class="text-4xl font-black text-ds-primary tabular-nums">
          {{ pad(countdownMinutes) }}:{{ pad(countdownSecs) }}
        </p>

        <!-- Members in draft room -->
        <div v-if="members.length" class="flex items-center justify-center gap-3 flex-wrap">
          <div
            v-for="member in members"
            :key="member.id"
            class="flex flex-col items-center gap-1 transition-opacity duration-300"
            :class="isPresent(member) ? 'opacity-100' : 'opacity-30'"
          >
            <div class="relative">
              <div
                class="w-10 h-10 rounded-full flex items-center justify-center text-xs font-bold border-2 overflow-hidden flex-shrink-0"
                :class="member.id === draft.myMembershipId
                  ? 'border-ds-primary bg-ds-primary/20 text-ds-primary'
                  : isPresent(member)
                    ? 'border-ds-green bg-ds-bg-hover text-ds-text-secondary'
                    : 'border-ds-border bg-ds-bg-hover text-ds-text-secondary'"
              >
                <img
                  v-if="member.avatar_url"
                  :src="member.avatar_url"
                  :alt="member.team_name"
                  class="w-full h-full object-cover"
                />
                <span v-else>{{ initials(member.team_name) }}</span>
              </div>
              <span
                v-if="isPresent(member)"
                class="absolute -bottom-0.5 -right-0.5 w-3 h-3 bg-ds-green rounded-full border-2 border-ds-bg-secondary"
              ></span>
            </div>
            <span
              class="text-[10px] font-medium max-w-[60px] truncate"
              :class="member.id === draft.myMembershipId ? 'text-ds-primary' : 'text-ds-text-tertiary'"
            >{{ member.team_name }}</span>
          </div>
        </div>

      </div>

      <!-- Only show after countdown -->
      <template v-if="!draft.isInCountdown">
        <AutoDraftBanner />
        <DraftPickQueue />
        <DraftPickFeed />
      </template>

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
              My Slate for {{ slateDateLabel }}
            </p>
            <p class="text-[10px] text-ds-text-tertiary mt-0.5">
              {{ pickBreakdown }}
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
      <div
        class="bg-ds-bg-primary border-t border-ds-border/50 overflow-y-auto transition-[max-height] duration-300 ease-in-out"
        :style="{ maxHeight: showSlate ? '55vh' : '25vh' }"
      >
        <div class="px-4 py-3">
          <DraftRosterPanel />
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.drawer-shadow {
  box-shadow: 0 -4px 20px rgba(0, 0, 0, 0.3);
}
</style>
