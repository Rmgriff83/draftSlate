<script setup>
import { ref, computed, onMounted, onUnmounted, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useLeagueStore } from '@/stores/league'
import { useSlateStore } from '@/stores/slate'
import { useAuthStore } from '@/stores/auth'
import MyPicksTab from '@/components/league/MyPicksTab.vue'
import MatchupTab from '@/components/league/MatchupTab.vue'
import LeagueTab from '@/components/league/LeagueTab.vue'
import HomeTab from '@/components/league/HomeTab.vue'
import PickDetailSheet from '@/components/league/PickDetailSheet.vue'
import UserDetailSheet from '@/components/league/UserDetailSheet.vue'
import BracketTab from '@/components/league/BracketTab.vue'
import TeamHeader from '@/components/league/TeamHeader.vue'

const route = useRoute()
const router = useRouter()
const leagueStore = useLeagueStore()
const slate = useSlateStore()
const auth = useAuthStore()

const showInviteCode = ref(false)
const copied = ref(false)
const showLeaveConfirm = ref(false)
const showCancelConfirm = ref(false)
const actionLoading = ref(false)
const actionError = ref('')

// Tabbed layout state
const activeTab = ref('home')
const selectedWeek = ref(1)
const detailPick = ref(null)
const detailUser = ref(null)
let pollInterval = null

const league = computed(() => leagueStore.currentLeague)

const isCommissioner = computed(() =>
  league.value?.is_commissioner || false
)

const isMember = computed(() =>
  league.value?.is_member || false
)

const members = computed(() =>
  league.value?.members || []
)

const isActiveState = computed(() =>
  ['active', 'playoffs', 'completed'].includes(league.value?.state)
)

const maxWeek = computed(() =>
  league.value?.current_week || league.value?.total_weeks_including_playoffs || league.value?.total_matchups || 1
)

const showBracketTab = computed(() =>
  ['playoffs', 'completed'].includes(league.value?.state)
)

function stateLabel(state) {
  const labels = { pending: 'Forming', active: 'Active', playoffs: 'Playoffs', completed: 'Completed', cancelled: 'Cancelled' }
  return labels[state] || state
}

function stateColor(state) {
  const colors = {
    pending: 'bg-ds-yellow/20 text-ds-yellow',
    active: 'bg-ds-green/20 text-ds-green',
    playoffs: 'bg-ds-primary/20 text-ds-primary',
    completed: 'bg-ds-text-tertiary/20 text-ds-text-tertiary',
    cancelled: 'bg-ds-red/20 text-ds-red',
  }
  return colors[state] || ''
}

const tzLabels = {
  'America/New_York': 'ET',
  'America/Chicago': 'CT',
  'America/Denver': 'MT',
  'America/Los_Angeles': 'PT',
}

const playoffLabels = {
  A: 'Top 4',
  B: 'Top 4 + Consolation',
  C: 'Top 6 Brackets',
  D: 'Full League Playoffs',
}

function payoutLabel(ps) {
  if (!ps) return '—'
  if (ps.first === 100 && !ps.second) return 'Winner Take All'
  if (ps.first === 60 && ps.second === 25) return 'Top 3 (60/25/15)'
  if (ps.first === 50 && ps.weekly === 20) return 'Top 3 + Weekly'
  return 'Custom'
}

function inviteUrl() {
  return `${window.location.origin}/app/leagues/join/${league.value?.invite_code}`
}

async function copyInvite() {
  try {
    await navigator.clipboard.writeText(inviteUrl())
    copied.value = true
    setTimeout(() => (copied.value = false), 2000)
  } catch {
    // fallback
  }
}

async function handleLeave() {
  actionLoading.value = true
  actionError.value = ''
  const result = await leagueStore.leaveLeague(league.value.id)
  actionLoading.value = false
  if (result.success) {
    router.push('/app/leagues')
  } else {
    actionError.value = result.message
  }
}

async function handleCancel() {
  actionLoading.value = true
  actionError.value = ''
  const result = await leagueStore.cancelLeague(league.value.id)
  actionLoading.value = false
  if (result.success) {
    router.push('/app/leagues')
  } else {
    actionError.value = result.message
  }
}

function prevWeek() {
  if (selectedWeek.value > 1) {
    selectedWeek.value--
  }
}

function nextWeek() {
  if (selectedWeek.value < maxWeek.value) {
    selectedWeek.value++
  }
}

function openPickDetail(pick) {
  detailPick.value = pick
}

function openUserDetail(member) {
  detailUser.value = member
}

async function handleSwap({ pickId, targetPosition, targetSlot, targetSlotType }) {
  const result = await slate.swapPick(league.value.id, pickId, targetPosition, targetSlot, targetSlotType)
  if (result.success) {
    detailPick.value = null
  }
}

async function loadWeekData() {
  if (!league.value || !isActiveState.value) return
  await slate.fetchSummary(league.value.id, selectedWeek.value)
}

watch(selectedWeek, () => {
  loadWeekData()
})

function startPolling() {
  if (pollInterval) return
  pollInterval = setInterval(() => {
    loadWeekData()
  }, 60_000)
}

watch(
  () => league.value?.state,
  async (state, oldState) => {
    // Only react to genuine transitions (e.g. pending → active during draft)
    if (oldState && state !== oldState && state && ['active', 'playoffs', 'completed'].includes(state)) {
      selectedWeek.value = league.value.current_week || 1
      await loadWeekData()
      slate.subscribeToLeagueChannel(league.value.id)
      startPolling()
    }
  },
)

onMounted(async () => {
  slate.$reset()
  await leagueStore.fetchLeague(route.params.id)

  // After fetch, initialize slate if league is in an active state
  if (league.value && isActiveState.value) {
    selectedWeek.value = league.value.current_week || 1
    await loadWeekData()
    slate.subscribeToLeagueChannel(league.value.id)
    startPolling()
  }
})

// Refresh when tab becomes visible again
function handleVisibility() {
  if (document.visibilityState === 'visible' && league.value && isActiveState.value) {
    loadWeekData()
  }
}
document.addEventListener('visibilitychange', handleVisibility)

onUnmounted(() => {
  document.removeEventListener('visibilitychange', handleVisibility)
  if (pollInterval) {
    clearInterval(pollInterval)
    pollInterval = null
  }
  if (league.value) {
    slate.unsubscribe(league.value.id)
  }
})
</script>

<template>
  <div class="space-y-5">
    <!-- Header -->
    <div class="flex items-center gap-3">
      <button @click="router.push('/app/leagues')" class="text-ds-text-tertiary hover:text-ds-text-primary transition-colors">
        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
          <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5L8.25 12l7.5-7.5" />
        </svg>
      </button>
      <div class="flex-1 min-w-0">
        <h1 class="text-xl font-bold text-ds-text-primary truncate">
          {{ league?.name || 'League' }}
        </h1>
      </div>
      <span v-if="league" class="text-xs font-medium px-2 py-0.5 rounded-full" :class="stateColor(league.state)">
        {{ stateLabel(league.state) }}
      </span>
    </div>

    <!-- Loading -->
    <div v-if="leagueStore.loading && !league" class="ds-card p-6 text-center">
      <p class="text-sm text-ds-text-tertiary">Loading league...</p>
    </div>

    <template v-else-if="league">
      <!-- ==================== PENDING STATE ==================== -->
      <template v-if="league.state === 'pending'">
        <!-- League Info Card -->
        <div class="ds-card p-4 space-y-3">
          <div class="flex items-center gap-3">
            <div class="w-10 h-10 rounded-full bg-ds-primary/20 flex items-center justify-center">
              <span class="text-sm font-bold text-ds-primary">{{ league.sport?.toUpperCase().slice(0, 3) }}</span>
            </div>
            <div class="flex-1">
              <p class="text-sm font-semibold text-ds-text-primary">{{ league.commissioner?.display_name }}</p>
              <p class="text-xs text-ds-text-tertiary">Commissioner</p>
            </div>
            <div class="text-right">
              <p class="text-sm font-bold text-ds-primary">${{ league.buy_in }}</p>
              <p class="text-xs text-ds-text-tertiary">Buy-in</p>
            </div>
          </div>

          <div class="grid grid-cols-3 gap-3 pt-2 border-t border-ds-border">
            <div class="text-center">
              <p class="text-lg font-bold text-ds-text-primary">{{ league.member_count }}/{{ league.max_teams }}</p>
              <p class="text-[10px] text-ds-text-tertiary uppercase">Teams</p>
            </div>
            <div class="text-center">
              <p class="text-lg font-bold text-ds-text-primary">{{ league.starter_count }}+{{ league.bench_slots }}</p>
              <p class="text-[10px] text-ds-text-tertiary uppercase">Roster</p>
            </div>
            <div class="text-center">
              <p class="text-lg font-bold text-ds-text-primary">${{ (league.buy_in * league.max_teams).toFixed(0) }}</p>
              <p class="text-[10px] text-ds-text-tertiary uppercase">Total Pot</p>
            </div>
          </div>
        </div>

        <!-- Draft CTA -->
        <div v-if="isMember" class="space-y-2">
          <button
            @click="router.push(`/app/leagues/${league.id}/draft`)"
            class="w-full py-3 text-sm font-semibold text-white bg-ds-primary hover:bg-ds-primary-light rounded-ds-sm transition-colors duration-ds-fast flex items-center justify-center gap-2"
          >
            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
              <path stroke-linecap="round" stroke-linejoin="round" d="M5.25 5.653c0-.856.917-1.398 1.667-.986l11.54 6.347a1.125 1.125 0 010 1.972l-11.54 6.347a1.125 1.125 0 01-1.667-.986V5.653z" />
            </svg>
            Enter Draft Lobby
          </button>
        </div>

        <!-- Settings Summary -->
        <div class="ds-card divide-y divide-ds-border">
          <div class="p-3 flex justify-between">
            <span class="text-xs text-ds-text-tertiary">Draft</span>
            <span class="text-xs text-ds-text-primary">Every {{ league.matchup_duration_days }}d at {{ league.draft_time?.slice(0, 5) }} {{ tzLabels[league.draft_timezone] || league.draft_timezone }}</span>
          </div>
          <div class="p-3 flex justify-between">
            <span class="text-xs text-ds-text-tertiary">Pick Timer</span>
            <span class="text-xs text-ds-text-primary">{{ league.pick_timer_seconds }}s</span>
          </div>
          <div class="p-3 flex justify-between">
            <span class="text-xs text-ds-text-tertiary">Event Cutoff</span>
            <span class="text-xs text-ds-text-primary">{{ league.min_hours_before_game }}h before game</span>
          </div>
          <div class="p-3 flex justify-between">
            <span class="text-xs text-ds-text-tertiary">Season</span>
            <span class="text-xs text-ds-text-primary">{{ league.total_matchups }} matchups</span>
          </div>
          <div class="p-3 flex justify-between">
            <span class="text-xs text-ds-text-tertiary">Playoffs</span>
            <span class="text-xs text-ds-text-primary">{{ playoffLabels[league.playoff_format] || league.playoff_format }}</span>
          </div>
          <div class="p-3 flex justify-between">
            <span class="text-xs text-ds-text-tertiary">Payout</span>
            <span class="text-xs text-ds-text-primary">{{ payoutLabel(league.payout_structure) }}</span>
          </div>
          <div class="p-3 flex justify-between">
            <span class="text-xs text-ds-text-tertiary">Odds Mode</span>
            <span class="text-xs text-ds-text-primary">{{ league.odds_mode === 'global_floor' ? `Floor ${league.global_odds_floor}` : 'Slot Bands' }}</span>
          </div>
        </div>

        <!-- Invite Code (Commissioner + Private) -->
        <div v-if="isCommissioner && league.invite_code" class="ds-card p-4">
          <div class="flex items-center justify-between mb-2">
            <p class="text-sm font-semibold text-ds-text-primary">Invite Link</p>
            <button @click="showInviteCode = !showInviteCode" class="text-xs text-ds-primary hover:underline">
              {{ showInviteCode ? 'Hide' : 'Show' }}
            </button>
          </div>
          <div v-if="showInviteCode" class="space-y-2">
            <div class="flex items-center gap-2">
              <input
                :value="inviteUrl()"
                readonly
                class="flex-1 px-3 py-1.5 text-xs bg-ds-bg-primary border border-ds-border rounded-ds-sm text-ds-text-secondary"
              />
              <button
                @click="copyInvite"
                class="px-3 py-1.5 text-xs font-medium text-white bg-ds-primary rounded-ds-sm hover:bg-ds-primary-light transition-colors duration-ds-fast"
              >
                {{ copied ? 'Copied!' : 'Copy' }}
              </button>
            </div>
            <p class="text-[10px] text-ds-text-tertiary">Share this link to invite members to your private league.</p>
          </div>
        </div>

        <!-- Members -->
        <div>
          <h2 class="text-sm font-semibold text-ds-text-secondary uppercase tracking-wide mb-3">
            Members ({{ league.member_count }})
          </h2>
          <div v-if="members.length" class="ds-card divide-y divide-ds-border">
            <div
              v-for="member in members"
              :key="member.id"
              class="p-3 flex items-center gap-3"
            >
              <div class="w-8 h-8 rounded-full bg-ds-primary/20 flex items-center justify-center flex-shrink-0">
                <span class="text-xs font-bold text-ds-primary">{{ member.user?.display_name?.charAt(0)?.toUpperCase() || '?' }}</span>
              </div>
              <div class="flex-1 min-w-0">
                <p class="text-sm font-medium text-ds-text-primary truncate">{{ member.team_name }}</p>
                <p class="text-xs text-ds-text-tertiary truncate">{{ member.user?.display_name }}</p>
              </div>
              <div class="text-right flex-shrink-0">
                <p class="text-xs font-medium text-ds-text-primary">{{ member.wins }}-{{ member.losses }}-{{ member.ties }}</p>
                <p v-if="league.commissioner?.id === member.user?.id" class="text-[10px] text-ds-primary">Commish</p>
              </div>
            </div>
          </div>
          <div v-else class="ds-card p-4 text-center">
            <p class="text-xs text-ds-text-tertiary">Member details visible to league members.</p>
          </div>
        </div>

        <!-- Action Error -->
        <p v-if="actionError" class="text-sm text-ds-red text-center">{{ actionError }}</p>

        <!-- Actions -->
        <div v-if="isMember" class="space-y-2">
          <button
            v-if="isCommissioner"
            @click="showCancelConfirm = true"
            class="w-full py-2.5 text-sm font-medium text-ds-red bg-ds-red/10 rounded-ds-sm hover:bg-ds-red/20 transition-colors duration-ds-fast"
          >
            Cancel League
          </button>
          <button
            v-else
            @click="showLeaveConfirm = true"
            class="w-full py-2.5 text-sm font-medium text-ds-red bg-ds-red/10 rounded-ds-sm hover:bg-ds-red/20 transition-colors duration-ds-fast"
          >
            Leave League
          </button>
        </div>
      </template>

      <!-- ==================== ACTIVE/PLAYOFFS/COMPLETED STATE ==================== -->
      <template v-if="isActiveState">
        <!-- Team header with week selector -->
        <TeamHeader
          :selected-week="selectedWeek"
          :max-week="maxWeek"
          @prev-week="prevWeek"
          @next-week="nextWeek"
        />

        <!-- Tab bar (sticky) -->
        <div class="sticky top-0 z-40 bg-ds-bg-primary -mx-4 px-4 pt-1 pb-0">
          <div class="flex border-b border-ds-border">
            <button
              v-for="tab in [
                { key: 'home', label: 'Home' },
                { key: 'picks', label: 'My Slate' },
                { key: 'matchup', label: 'Matchup' },
                { key: 'league', label: 'League' },
                ...(showBracketTab ? [{ key: 'bracket', label: 'Bracket' }] : []),
              ]"
              :key="tab.key"
              @click="activeTab = tab.key"
              :class="[
                'flex-1 py-2.5 text-sm font-medium text-center transition-colors border-b-2 -mb-px',
                activeTab === tab.key
                  ? 'text-ds-primary border-ds-primary'
                  : 'text-ds-text-tertiary border-transparent hover:text-ds-text-secondary',
              ]"
            >
              {{ tab.label }}
            </button>
          </div>
        </div>

        <!-- Tab content -->
        <div class="min-h-[200px]">
          <div v-if="slate.loading" class="ds-card p-6 text-center">
            <p class="text-sm text-ds-text-tertiary">Loading...</p>
          </div>
          <template v-else>
            <HomeTab v-if="activeTab === 'home'" />
            <MyPicksTab v-else-if="activeTab === 'picks'" @open-detail="openPickDetail" />
            <MatchupTab v-else-if="activeTab === 'matchup'" @open-detail="openPickDetail" />
            <LeagueTab v-else-if="activeTab === 'league'" @open-user-detail="openUserDetail" @open-detail="openPickDetail" />
            <BracketTab v-else-if="activeTab === 'bracket'" />
          </template>
        </div>
      </template>
    </template>

    <!-- Pick Detail Sheet -->
    <PickDetailSheet
      v-if="detailPick"
      :pick="detailPick"
      @close="detailPick = null"
      @swap="handleSwap"
    />

    <!-- User Detail Sheet -->
    <UserDetailSheet
      v-if="detailUser"
      :member="detailUser"
      @close="detailUser = null"
    />

    <!-- Leave Confirmation Modal -->
    <Teleport to="body">
      <div v-if="showLeaveConfirm" class="fixed inset-0 z-50 flex items-center justify-center p-4">
        <div class="fixed inset-0 bg-black/50" @click="showLeaveConfirm = false"></div>
        <div class="ds-card p-6 w-full max-w-sm relative z-10">
          <h2 class="text-lg font-bold text-ds-text-primary mb-2">Leave League?</h2>
          <p class="text-sm text-ds-text-secondary mb-4">
            You will be removed from <strong>{{ league?.name }}</strong> and your ${{ league?.buy_in }} buy-in will be refunded.
          </p>
          <div class="flex gap-3">
            <button
              @click="showLeaveConfirm = false"
              class="flex-1 px-4 py-2 text-sm font-medium text-ds-text-secondary bg-ds-bg-hover rounded-ds-sm hover:bg-ds-border transition-colors"
            >
              Cancel
            </button>
            <button
              @click="handleLeave"
              :disabled="actionLoading"
              class="flex-1 px-4 py-2 text-sm font-semibold text-white bg-ds-red rounded-ds-sm hover:bg-ds-red/90 transition-colors disabled:opacity-50"
            >
              {{ actionLoading ? 'Leaving...' : 'Leave' }}
            </button>
          </div>
        </div>
      </div>
    </Teleport>

    <!-- Cancel Confirmation Modal -->
    <Teleport to="body">
      <div v-if="showCancelConfirm" class="fixed inset-0 z-50 flex items-center justify-center p-4">
        <div class="fixed inset-0 bg-black/50" @click="showCancelConfirm = false"></div>
        <div class="ds-card p-6 w-full max-w-sm relative z-10">
          <h2 class="text-lg font-bold text-ds-text-primary mb-2">Cancel League?</h2>
          <p class="text-sm text-ds-text-secondary mb-4">
            This will permanently cancel <strong>{{ league?.name }}</strong>. All members will be removed.
          </p>
          <div class="flex gap-3">
            <button
              @click="showCancelConfirm = false"
              class="flex-1 px-4 py-2 text-sm font-medium text-ds-text-secondary bg-ds-bg-hover rounded-ds-sm hover:bg-ds-border transition-colors"
            >
              Go Back
            </button>
            <button
              @click="handleCancel"
              :disabled="actionLoading"
              class="flex-1 px-4 py-2 text-sm font-semibold text-white bg-ds-red rounded-ds-sm hover:bg-ds-red/90 transition-colors disabled:opacity-50"
            >
              {{ actionLoading ? 'Cancelling...' : 'Cancel League' }}
            </button>
          </div>
        </div>
      </div>
    </Teleport>
  </div>
</template>
