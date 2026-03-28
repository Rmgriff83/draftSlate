<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue'
import { useRouter } from 'vue-router'
import { useLeagueStore } from '@/stores/league'
import { useSlateStore } from '@/stores/slate'

const router = useRouter()
const leagueStore = useLeagueStore()
const slate = useSlateStore()

const league = computed(() => leagueStore.currentLeague)
const nextDraftAt = computed(() => league.value?.next_draft_at)
const hasActiveDraft = computed(() => league.value?.has_active_draft || false)
const isCompleted = computed(() => league.value?.state === 'completed')
const isPlayoffs = computed(() => league.value?.state === 'playoffs')

const champion = computed(() => {
  if (!isCompleted.value) return null
  return slate.standings.find(m => m.final_position === 1) || null
})

const currentMatchupContext = computed(() => {
  if (!slate.myMatchup) return null
  if (slate.myMatchup.is_playoff && slate.myMatchup.playoff_round) {
    const labels = {
      semifinal: 'Semifinal',
      wild_card: 'Wild Card',
      championship: 'Championship',
      third_place: '3rd Place',
      winners_semifinal: 'Semifinal',
      winners_championship: 'Championship',
      winners_consolation: '3rd Place Game',
      losers_round1: 'Losers R1',
      losers_round2: 'Losers R2',
      consolation_semi: 'Consolation',
      fifth_place: '5th Place',
    }
    return labels[slate.myMatchup.playoff_round] || 'Playoffs'
  }
  return null
})

// ---- Section 1: Countdown ----
const countdown = ref({ days: 0, hours: 0, minutes: 0, seconds: 0 })
const isLive = ref(false)
let timer = null

function updateCountdown() {
  if (!nextDraftAt.value) return
  const diff = new Date(nextDraftAt.value).getTime() - Date.now()
  if (diff <= 0) {
    isLive.value = true
    countdown.value = { days: 0, hours: 0, minutes: 0, seconds: 0 }
    return
  }
  isLive.value = false
  const s = Math.floor(diff / 1000)
  countdown.value = {
    days: Math.floor(s / 86400),
    hours: Math.floor((s % 86400) / 3600),
    minutes: Math.floor((s % 3600) / 60),
    seconds: s % 60,
  }
}

onMounted(() => {
  updateCountdown()
  timer = setInterval(updateCountdown, 1000)
})

onUnmounted(() => {
  if (timer) clearInterval(timer)
})

function pad(n) {
  return String(n).padStart(2, '0')
}

const draftDateLabel = computed(() => {
  if (!nextDraftAt.value) return null
  const d = new Date(nextDraftAt.value)
  return d.toLocaleDateString(undefined, { weekday: 'short', month: 'short', day: 'numeric' })
    + ' at '
    + d.toLocaleTimeString(undefined, { hour: 'numeric', minute: '2-digit' })
})

// ---- Section 2: This Week ----
const matchup = computed(() => slate.myMatchup)

const myTeam = computed(() => {
  if (!matchup.value) return null
  const m = matchup.value
  const team = m.is_home ? m.home_team : m.away_team
  return { name: team?.team_name, user: team?.user_name }
})

const oppTeam = computed(() => {
  if (!matchup.value) return null
  const m = matchup.value
  const team = m.is_home ? m.away_team : m.home_team
  return { name: team?.team_name, user: team?.user_name }
})

const pendingCount = computed(() => {
  const total = slate.starters.length
  return total - slate.lockedCount
})

const myInitial = computed(() => (myTeam.value?.name || '?')[0].toUpperCase())
const oppInitial = computed(() => (oppTeam.value?.name || '?')[0].toUpperCase())

const matchupStatus = computed(() => {
  if (!matchup.value) return null
  if (matchup.value.status === 'completed') {
    if (slate.myScore > slate.opponentScore) return { label: 'Victory', cls: 'text-ds-green bg-ds-green/10' }
    if (slate.myScore < slate.opponentScore) return { label: 'Defeat', cls: 'text-ds-red bg-ds-red/10' }
    return { label: 'Tie', cls: 'text-ds-yellow bg-ds-yellow/10' }
  }
  return null
})

// ---- Section 3: Standings ----
const sortedStandings = computed(() =>
  [...slate.standings].sort((a, b) => (b.win_percentage ?? 0) - (a.win_percentage ?? 0))
)
</script>

<template>
  <div class="space-y-4">
    <!-- Champion Banner -->
    <div v-if="isCompleted && champion" class="ds-card p-4 bg-gradient-to-r from-yellow-400/10 to-amber-500/10 border border-yellow-400/20">
      <div class="text-center">
        <p class="text-yellow-400 text-lg font-black">Champion</p>
        <p class="text-xl font-bold text-ds-text-primary mt-1">{{ champion.team_name }}</p>
        <p class="text-xs text-ds-text-tertiary">{{ champion.user_name }}</p>
      </div>
    </div>

    <!-- Section 1: Next Draft -->
    <div class="ds-card p-4">
      <h3 class="text-xs font-semibold text-ds-text-tertiary uppercase tracking-wide mb-3">Next Draft</h3>

      <!-- Season complete -->
      <div v-if="!nextDraftAt" class="text-center py-2">
        <p class="text-sm font-medium text-ds-text-secondary">Season Complete</p>
      </div>

      <!-- Draft is live (countdown expired or active draft in progress) -->
      <div v-else-if="isLive || hasActiveDraft" class="text-center py-2 space-y-3">
        <p class="text-lg font-bold text-ds-green animate-pulse">Draft is Live!</p>
        <button
          @click="router.push(`/app/leagues/${league.id}/draft`)"
          class="px-6 py-2.5 text-sm font-semibold text-white bg-ds-primary hover:bg-ds-primary-light rounded-ds-sm transition-colors duration-ds-fast"
        >
          Enter Draft
        </button>
      </div>

      <!-- Countdown -->
      <div v-else class="text-center space-y-2">
        <div class="flex justify-center gap-3">
          <div v-for="(unit, label) in { D: countdown.days, H: countdown.hours, M: countdown.minutes, S: countdown.seconds }" :key="label" class="text-center">
            <p class="text-2xl font-bold text-ds-text-primary tabular-nums">{{ pad(unit) }}</p>
            <p class="text-[10px] text-ds-text-tertiary uppercase">{{ label }}</p>
          </div>
        </div>
        <p v-if="draftDateLabel" class="text-xs text-ds-text-tertiary">{{ draftDateLabel }}</p>
      </div>
    </div>

    <!-- Section 2: This Week -->
    <div class="ds-card p-4">
      <div class="flex items-center justify-between mb-3">
        <h3 class="text-xs font-semibold text-ds-text-tertiary uppercase tracking-wide">Current Matchup</h3>
        <span
          v-if="currentMatchupContext"
          class="text-[10px] font-bold text-ds-primary bg-ds-primary/10 px-2 py-0.5 rounded-full"
        >
          {{ currentMatchupContext }}
        </span>
      </div>

      <div v-if="!matchup" class="text-center py-2">
        <p class="text-sm text-ds-text-secondary">No matchup scheduled</p>
      </div>

      <template v-else>
        <!-- Matchup: avatars + score -->
        <div class="flex items-center justify-center gap-4 py-2">
          <!-- My side -->
          <div class="flex flex-col items-center gap-1.5 flex-1 min-w-0">
            <div class="w-12 h-12 rounded-full bg-ds-primary/20 flex items-center justify-center">
              <span class="text-lg font-bold text-ds-primary">{{ myInitial }}</span>
            </div>
            <p class="text-sm font-semibold text-ds-text-primary truncate max-w-full">{{ myTeam?.name }}</p>
            <p class="text-[10px] text-ds-text-tertiary">{{ myTeam?.user }}</p>
          </div>

          <!-- Score -->
          <div class="flex flex-col items-center gap-1 flex-shrink-0">
            <div class="flex items-center gap-2">
              <span class="text-2xl font-black text-ds-text-primary tabular-nums">{{ slate.myScore }}</span>
              <span class="text-sm text-ds-text-tertiary font-bold">&mdash;</span>
              <span class="text-2xl font-black text-ds-text-primary tabular-nums">{{ slate.opponentScore }}</span>
            </div>
            <span
              v-if="matchupStatus"
              :class="['text-[10px] font-semibold px-2 py-0.5 rounded', matchupStatus.cls]"
            >
              {{ matchupStatus.label }}
            </span>
          </div>

          <!-- Opponent side -->
          <div class="flex flex-col items-center gap-1.5 flex-1 min-w-0">
            <div class="w-12 h-12 rounded-full bg-ds-bg-hover flex items-center justify-center">
              <span class="text-lg font-bold text-ds-text-secondary">{{ oppInitial }}</span>
            </div>
            <p class="text-sm font-semibold text-ds-text-primary truncate max-w-full">{{ oppTeam?.name }}</p>
            <p class="text-[10px] text-ds-text-tertiary">{{ oppTeam?.user }}</p>
          </div>
        </div>

        <!-- Pick status summary -->
        <div class="flex items-center justify-between pt-3 border-t border-ds-border">
          <p class="text-xs text-ds-text-tertiary">{{ slate.statusLine }}</p>
          <div class="flex items-center gap-2">
            <span v-if="slate.hitCount > 0" class="text-[10px] font-medium text-ds-green bg-ds-green/10 px-1.5 py-0.5 rounded">
              {{ slate.hitCount }} hit
            </span>
            <span v-if="slate.missCount > 0" class="text-[10px] font-medium text-ds-red bg-ds-red/10 px-1.5 py-0.5 rounded">
              {{ slate.missCount }} miss
            </span>
            <span v-if="pendingCount > 0" class="text-[10px] font-medium text-ds-text-tertiary bg-ds-bg-hover px-1.5 py-0.5 rounded">
              {{ pendingCount }} pending
            </span>
          </div>
        </div>
      </template>
    </div>

    <!-- Section 3: Standings -->
    <div class="ds-card p-4">
      <h3 class="text-xs font-semibold text-ds-text-tertiary uppercase tracking-wide mb-3">Standings</h3>

      <div v-if="sortedStandings.length === 0" class="text-center py-2">
        <p class="text-sm text-ds-text-secondary">No standings yet</p>
      </div>

      <div v-else class="divide-y divide-ds-border -mx-4">
        <div
          v-for="(member, idx) in sortedStandings"
          :key="member.membership_id"
          :class="[
            'flex items-center gap-2 px-4 py-2',
            member.is_current_user ? 'bg-ds-primary/10' : '',
          ]"
        >
          <!-- Rank -->
          <span
            :class="[
              'text-xs font-bold w-5 text-center flex-shrink-0',
              idx + 1 <= 3 ? 'text-ds-primary' : 'text-ds-text-tertiary',
            ]"
          >
            {{ idx + 1 }}
          </span>

          <!-- Team name -->
          <p
            :class="[
              'flex-1 text-sm truncate',
              member.is_current_user ? 'font-semibold text-ds-text-primary' : 'text-ds-text-secondary',
            ]"
          >
            {{ member.team_name }}
          </p>

          <!-- Record -->
          <span class="text-xs font-mono text-ds-text-secondary flex-shrink-0">
            {{ member.wins }}-{{ member.losses }}<span v-if="member.ties > 0">-{{ member.ties }}</span>
          </span>
        </div>
      </div>
    </div>
  </div>
</template>
