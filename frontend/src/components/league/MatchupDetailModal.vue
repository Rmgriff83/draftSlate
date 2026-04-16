<script setup>
import { ref, computed, onMounted, onUnmounted, watch } from 'vue'
import { Icon } from '@iconify/vue'
import PickImage from '@/components/common/PickImage.vue'
import UserAvatar from '@/components/common/UserAvatar.vue'
import { useSlateStore } from '@/stores/slate'
import { useSlateHelpers } from '@/composables/useSlateHelpers'
import api from '@/utils/api'

const props = defineProps({
  leagueId: { type: [Number, String], required: true },
  matchupId: { type: [Number, String], required: true },
})

const emit = defineEmits(['close', 'open-detail'])

const slate = useSlateStore()
const {
  typeLabels,
  typeBadgeClasses,
  outcomeBadgeClasses,
  outcomeLabel,
  formatOdds,
  oddsColor,
  isLive,
  liveScore,
  pickProgress,
  pickResultText,
  formatGameTime,
  aggregateImpliedProb,
  impliedProbToAmerican,
} = useSlateHelpers()

const matchup = ref(null)
const loading = ref(true)

// Fetch full matchup with picks
async function loadMatchup() {
  loading.value = true
  try {
    const res = await api.get(`/api/v1/leagues/${props.leagueId}/matchup/${props.matchupId}`)
    matchup.value = res.data.data
  } catch {
    matchup.value = null
  } finally {
    loading.value = false
  }
}

onMounted(() => {
  loadMatchup()

  // Register real-time callbacks
  slate.onScoresUpdated = (e) => {
    if (!matchup.value) return
    const allPicks = [...(matchup.value.home_picks || []), ...(matchup.value.away_picks || [])]
    for (const update of e.picks || []) {
      const pick = allPicks.find(p => p.pick_selection?.id === update.pick_selection_id)
      if (pick && pick.pick_selection) {
        pick.pick_selection.result_data = update.result_data
      }
    }
  }

  slate.onPickGraded = (e) => {
    if (!matchup.value) return
    const allPicks = [...(matchup.value.home_picks || []), ...(matchup.value.away_picks || [])]
    const pick = allPicks.find(p => p.pick_selection?.id === e.pick_selection_id)
    if (pick && pick.pick_selection) {
      pick.pick_selection.outcome = e.outcome
    }
  }

  slate.onMatchupScored = (e) => {
    if (!matchup.value || matchup.value.id !== e.matchup_id) return
    matchup.value.home_score = e.scores.home
    matchup.value.away_score = e.scores.away
    matchup.value.winner_id = e.winner_id
    matchup.value.status = 'completed'
  }
})

onUnmounted(() => {
  slate.onScoresUpdated = null
  slate.onPickGraded = null
  slate.onMatchupScored = null
})

// --- Display logic (mirrors MatchupTab patterns) ---

const homeTeam = computed(() => matchup.value?.home_team)
const awayTeam = computed(() => matchup.value?.away_team)

function pickIsLive(pick) {
  return isLive(pick)
}

function pickLiveScore(pick) {
  return liveScore(pick)
}

function pickGetProgress(pick) {
  if (!pick || !pickIsLive(pick)) return null
  return pickProgress(pick)
}

function progressTrendColor(trend) {
  return { positive: 'text-ds-green', negative: 'text-ds-red', neutral: 'text-gray-400' }[trend] || 'text-gray-400'
}

function pickIsGraded(pick) {
  const outcome = pick?.pick_selection?.outcome
  return outcome && outcome !== 'pending'
}

function pickOutcome(pick) {
  return pick?.pick_selection?.outcome || 'pending'
}

function pickDisplayOdds(pick) {
  if (!pick) return null
  if (pick.is_locked) return pick.locked_odds
  return pick.pick_selection?.current_odds ?? pick.pick_selection?.snapshot_odds ?? pick.drafted_odds
}

// Score computeds
function countHits(picks) {
  return (picks || []).filter(p => p.position === 'starter' && p.pick_selection?.outcome === 'hit').length
}

function getStarterOdds(picks) {
  return (picks || []).filter(p => p.position === 'starter').map(p => p.locked_odds).filter(o => o != null)
}

function allStartersLocked(picks) {
  const starters = (picks || []).filter(p => p.position === 'starter')
  return starters.length > 0 && starters.every(p => p.is_locked)
}

const homeHits = computed(() => countHits(matchup.value?.home_picks))
const awayHits = computed(() => countHits(matchup.value?.away_picks))

const homeOdds = computed(() => getStarterOdds(matchup.value?.home_picks))
const awayOdds = computed(() => getStarterOdds(matchup.value?.away_picks))

const allHomeLocked = computed(() => allStartersLocked(matchup.value?.home_picks))
const allAwayLocked = computed(() => allStartersLocked(matchup.value?.away_picks))
const allLocked = computed(() => allHomeLocked.value && allAwayLocked.value)

const homeLockedStatus = computed(() => {
  const starters = (matchup.value?.home_picks || []).filter(p => p.position === 'starter')
  return { locked: starters.filter(p => p.is_locked).length, total: starters.length }
})

const awayLockedStatus = computed(() => {
  const starters = (matchup.value?.away_picks || []).filter(p => p.position === 'starter')
  return { locked: starters.filter(p => p.is_locked).length, total: starters.length }
})

const oddsWinner = computed(() => {
  if (!allLocked.value || homeOdds.value.length === 0 || awayOdds.value.length === 0) return null
  const homeProb = aggregateImpliedProb(homeOdds.value)
  const awayProb = aggregateImpliedProb(awayOdds.value)
  if (Math.abs(homeProb - awayProb) < 0.0001) return null
  return homeProb < awayProb ? 'home' : 'away'
})

const oddsBonus = computed(() => {
  if (!oddsWinner.value) return { home: 0, away: 0 }
  return oddsWinner.value === 'home' ? { home: 1, away: 0 } : { home: 0, away: 1 }
})

const homeScore = computed(() => homeHits.value + oddsBonus.value.home)
const awayScore = computed(() => awayHits.value + oddsBonus.value.away)

const homeAggAmerican = computed(() => {
  if (homeOdds.value.length === 0) return null
  return impliedProbToAmerican(aggregateImpliedProb(homeOdds.value))
})

const awayAggAmerican = computed(() => {
  if (awayOdds.value.length === 0) return null
  return impliedProbToAmerican(aggregateImpliedProb(awayOdds.value))
})

const isCompleted = computed(() => matchup.value?.status === 'completed')

// Build paired rows by slot_type:slot_number
const pairedSlots = computed(() => {
  if (!matchup.value) return []

  const homeStarters = (matchup.value.home_picks || []).filter(p => p.position === 'starter')
  const awayStarters = (matchup.value.away_picks || []).filter(p => p.position === 'starter')

  const awayBySlot = {}
  for (const p of awayStarters) {
    awayBySlot[`${p.slot_type}:${p.slot_number}`] = p
  }

  const slotKeys = new Set()
  for (const p of homeStarters) slotKeys.add(`${p.slot_type}:${p.slot_number}`)
  for (const p of awayStarters) slotKeys.add(`${p.slot_type}:${p.slot_number}`)

  const typeOrder = ['moneyline', 'spread', 'total', 'player_prop']
  const sorted = [...slotKeys].sort((a, b) => {
    const [typeA, numA] = a.split(':')
    const [typeB, numB] = b.split(':')
    const idxA = typeOrder.indexOf(typeA)
    const idxB = typeOrder.indexOf(typeB)
    if (idxA !== idxB) return idxA - idxB
    return Number(numA) - Number(numB)
  })

  const homeBySlot = {}
  for (const p of homeStarters) {
    homeBySlot[`${p.slot_type}:${p.slot_number}`] = p
  }

  let lastType = null
  const rows = []
  for (const key of sorted) {
    const [slotType] = key.split(':')
    if (slotType !== lastType) {
      rows.push({ isHeader: true, slotType })
      lastType = slotType
    }
    rows.push({
      isHeader: false,
      key,
      home: homeBySlot[key] || null,
      away: awayBySlot[key] || null,
    })
  }
  return rows
})
</script>

<template>
  <Teleport to="body">
    <div class="fixed inset-0 z-50 bg-ds-bg-primary overflow-y-auto">
      <!-- Header bar -->
      <div class="sticky top-0 z-10 bg-ds-bg-primary border-b border-ds-border px-4 py-3 flex items-center gap-3">
        <button @click="emit('close')" class="text-ds-text-tertiary hover:text-ds-text-primary transition-colors">
          <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5L8.25 12l7.5-7.5" />
          </svg>
        </button>
        <h2 class="text-sm font-semibold text-ds-text-primary">
          Week {{ matchup?.week }} Matchup
        </h2>
      </div>

      <!-- Loading -->
      <div v-if="loading" class="p-6 text-center">
        <p class="text-sm text-ds-text-tertiary">Loading matchup...</p>
      </div>

      <!-- Content -->
      <div v-else-if="matchup" class="p-4 space-y-4">
        <!-- Score header -->
        <div class="ds-card p-4">
          <div class="flex items-center justify-center gap-6">
            <div class="text-center flex-1 flex flex-col items-center">
              <UserAvatar :avatar-url="homeTeam?.avatar_url" :name="homeTeam?.user_name || homeTeam?.team_name" size="md" />
              <p class="text-xs text-gray-400 mt-1 truncate max-w-full">{{ homeTeam?.team_name }}</p>
              <p class="text-3xl font-black text-ds-text-primary animate-score-pop">{{ homeScore }}</p>
            </div>
            <span class="text-lg text-gray-500 font-bold">&mdash;</span>
            <div class="text-center flex-1 flex flex-col items-center">
              <UserAvatar :avatar-url="awayTeam?.avatar_url" :name="awayTeam?.user_name || awayTeam?.team_name" size="md" />
              <p class="text-xs text-gray-400 mt-1 truncate max-w-full">{{ awayTeam?.team_name }}</p>
              <p class="text-3xl font-black text-ds-text-primary animate-score-pop">{{ awayScore }}</p>
            </div>
          </div>

          <p v-if="isCompleted" class="text-center mt-2">
            <span v-if="matchup.winner_id === homeTeam?.id" class="text-xs font-semibold text-green-400 bg-green-500/10 px-2 py-0.5 rounded">
              {{ homeTeam?.team_name }} Wins
            </span>
            <span v-else-if="matchup.winner_id === awayTeam?.id" class="text-xs font-semibold text-green-400 bg-green-500/10 px-2 py-0.5 rounded">
              {{ awayTeam?.team_name }} Wins
            </span>
            <span v-else class="text-xs font-semibold text-yellow-400 bg-yellow-500/10 px-2 py-0.5 rounded">
              Tie
            </span>
          </p>
        </div>

        <!-- Side-by-side pick comparison -->
        <div class="space-y-1.5">
          <!-- Column headers -->
          <div class="grid grid-cols-2 gap-2 px-1">
            <div class="flex items-center gap-1.5">
              <UserAvatar :avatar-url="homeTeam?.avatar_url" :name="homeTeam?.user_name || homeTeam?.team_name" size="xs" />
              <p class="text-xs font-semibold text-gray-400 truncate">{{ homeTeam?.team_name }}</p>
            </div>
            <div class="flex items-center gap-1.5 justify-end">
              <p class="text-xs font-semibold text-gray-400 truncate">{{ awayTeam?.team_name }}</p>
              <UserAvatar :avatar-url="awayTeam?.avatar_url" :name="awayTeam?.user_name || awayTeam?.team_name" size="xs" />
            </div>
          </div>

          <!-- Overall Odds category -->
          <template v-if="homeOdds.length > 0 || awayOdds.length > 0">
            <div class="pt-2 pb-0.5 px-1">
              <span class="text-[10px] font-bold px-1.5 py-0.5 rounded bg-teal-500/20 text-teal-400">
                Overall Odds
              </span>
            </div>
            <div class="grid grid-cols-2 gap-2">
              <!-- Home side -->
              <div :class="[
                'ds-card p-2.5 text-left',
                allLocked && oddsWinner === 'home' ? 'border-l-2 border-green-500' : '',
                allLocked && oddsWinner && oddsWinner !== 'home' ? 'border-l-2 border-red-500' : '',
              ]">
                <div class="flex items-center gap-1 mb-1">
                  <span v-if="allLocked && oddsWinner === 'home'" class="text-[9px] font-bold px-1 py-0.5 rounded bg-green-500/20 text-green-400">HIT</span>
                  <span v-else-if="allLocked && oddsWinner" class="text-[9px] font-bold px-1 py-0.5 rounded bg-red-500/20 text-red-400">MISS</span>
                  <span v-else-if="allLocked" class="text-[9px] font-bold px-1 py-0.5 rounded bg-yellow-500/20 text-yellow-400">PUSH</span>
                  <span v-else class="text-[9px] font-bold px-1 py-0.5 rounded bg-gray-600/20 text-gray-500">PENDING</span>
                </div>
                <div class="flex items-center gap-2">
                  <Icon icon="mdi:scale-balance" class="w-5 h-5 text-teal-400 shrink-0" />
                  <div class="flex-1 min-w-0">
                    <p class="text-xs text-ds-text-primary">Line</p>
                    <p class="text-[10px] text-gray-500 mt-0.5">{{ homeLockedStatus.locked }}/{{ homeLockedStatus.total }} starters locked</p>
                  </div>
                </div>
                <template v-if="allLocked">
                  <p :class="['text-[10px] font-mono font-bold mt-1', oddsWinner === 'home' ? 'text-green-400' : oddsWinner ? 'text-red-400' : 'text-yellow-400']">
                    {{ homeAggAmerican != null ? formatOdds(homeAggAmerican) : '--' }}
                  </p>
                  <div class="mt-1">
                    <Icon v-if="oddsWinner === 'home'" icon="mdi:check-circle" class="w-6 h-6 text-green-400" />
                    <Icon v-else-if="oddsWinner" icon="mdi:close-circle" class="w-6 h-6 text-red-400" />
                    <Icon v-else icon="mdi:minus-circle" class="w-6 h-6 text-yellow-400" />
                  </div>
                </template>
                <p v-else class="text-[10px] font-mono font-bold mt-1 text-ds-text-primary">
                  {{ homeAggAmerican != null ? formatOdds(homeAggAmerican) : '--' }}
                </p>
              </div>
              <!-- Away side -->
              <div :class="[
                'ds-card p-2.5 text-right',
                allLocked && oddsWinner === 'away' ? 'border-r-2 border-green-500' : '',
                allLocked && oddsWinner && oddsWinner !== 'away' ? 'border-r-2 border-red-500' : '',
              ]">
                <div class="flex items-center gap-1 mb-1 justify-end">
                  <span v-if="allLocked && oddsWinner === 'away'" class="text-[9px] font-bold px-1 py-0.5 rounded bg-green-500/20 text-green-400">HIT</span>
                  <span v-else-if="allLocked && oddsWinner" class="text-[9px] font-bold px-1 py-0.5 rounded bg-red-500/20 text-red-400">MISS</span>
                  <span v-else-if="allLocked" class="text-[9px] font-bold px-1 py-0.5 rounded bg-yellow-500/20 text-yellow-400">PUSH</span>
                  <span v-else class="text-[9px] font-bold px-1 py-0.5 rounded bg-gray-600/20 text-gray-500">PENDING</span>
                </div>
                <div class="flex items-center gap-2 flex-row-reverse">
                  <Icon icon="mdi:scale-balance" class="w-5 h-5 text-teal-400 shrink-0" />
                  <div class="flex-1 min-w-0">
                    <p class="text-xs text-ds-text-primary">Line</p>
                    <p class="text-[10px] text-gray-500 mt-0.5">{{ awayLockedStatus.locked }}/{{ awayLockedStatus.total }} starters locked</p>
                  </div>
                </div>
                <template v-if="allLocked">
                  <p :class="['text-[10px] font-mono font-bold mt-1', oddsWinner === 'away' ? 'text-green-400' : oddsWinner ? 'text-red-400' : 'text-yellow-400']">
                    {{ awayAggAmerican != null ? formatOdds(awayAggAmerican) : '--' }}
                  </p>
                  <div class="mt-1 flex justify-end">
                    <Icon v-if="oddsWinner === 'away'" icon="mdi:check-circle" class="w-6 h-6 text-green-400" />
                    <Icon v-else-if="oddsWinner" icon="mdi:close-circle" class="w-6 h-6 text-red-400" />
                    <Icon v-else icon="mdi:minus-circle" class="w-6 h-6 text-yellow-400" />
                  </div>
                </template>
                <p v-else class="text-[10px] font-mono font-bold mt-1 text-ds-text-primary">
                  {{ awayAggAmerican != null ? formatOdds(awayAggAmerican) : '--' }}
                </p>
              </div>
            </div>
          </template>

          <template v-for="row in pairedSlots" :key="row.isHeader ? row.slotType : row.key">
            <!-- Type section header -->
            <div v-if="row.isHeader" class="pt-2 pb-0.5 px-1">
              <span :class="['text-[10px] font-bold px-1.5 py-0.5 rounded', typeBadgeClasses[row.slotType] || 'bg-gray-600 text-ds-text-secondary']">
                {{ typeLabels[row.slotType] || row.slotType }}
              </span>
            </div>

            <!-- Paired pick row -->
            <div v-else class="grid grid-cols-2 gap-2">
              <!-- Home pick (left) -->
              <div
                :class="[
                  'ds-card p-2.5 text-left relative cursor-pointer',
                  pickIsLive(row.home) ? 'animate-live-glow border border-ds-green/40' : '',
                  !pickIsLive(row.home) && row.home?.pick_selection?.outcome === 'hit' ? 'border-l-2 border-green-500' : '',
                  !pickIsLive(row.home) && row.home?.pick_selection?.outcome === 'miss' ? 'border-l-2 border-red-500' : '',
                ]"
                @click="row.home && emit('open-detail', row.home)"
              >
                <template v-if="row.home">
                  <div class="flex items-center gap-1 mb-1">
                    <span v-if="pickIsLive(row.home)" class="text-[9px] font-bold px-1 py-0.5 rounded bg-ds-green/20 text-ds-green flex items-center gap-0.5">
                      <span class="w-1 h-1 rounded-full bg-ds-green animate-pulse"></span>
                      LIVE
                    </span>
                    <span
                      v-else-if="row.home.pick_selection?.outcome && row.home.pick_selection.outcome !== 'pending'"
                      :class="['text-[9px] font-bold px-1 py-0.5 rounded', outcomeBadgeClasses[row.home.pick_selection.outcome]]"
                    >
                      {{ outcomeLabel(row.home.pick_selection.outcome) }}
                    </span>
                  </div>
                  <div class="flex items-start gap-2">
                    <PickImage :pick="row.home.pick_selection" size="sm" />
                    <div class="flex-1 min-w-0">
                      <p class="text-xs text-ds-text-primary truncate">{{ row.home.pick_selection?.description }}</p>
                      <p class="text-[10px] text-gray-500 truncate mt-0.5">
                        {{ row.home.pick_selection?.game_display }}
                        <span v-if="formatGameTime(row.home.pick_selection?.game_time)"> · {{ formatGameTime(row.home.pick_selection?.game_time) }}</span>
                      </p>
                    </div>
                  </div>
                  <p v-if="pickIsGraded(row.home) && !pickIsLive(row.home) && pickResultText(row.home)" class="text-[10px] font-mono text-ds-text-secondary mt-0.5">
                    {{ pickResultText(row.home) }}
                  </p>
                  <div v-if="pickIsLive(row.home) && pickLiveScore(row.home)" class="mt-1">
                    <span class="text-[10px] font-bold text-ds-green">
                      {{ pickLiveScore(row.home).away }} - {{ pickLiveScore(row.home).home }}
                    </span>
                    <span v-if="pickLiveScore(row.home).period" class="text-[9px] text-gray-500 ml-1">{{ pickLiveScore(row.home).period }}</span>
                  </div>
                  <div v-if="pickIsGraded(row.home) && !pickIsLive(row.home)" class="mt-1">
                    <Icon v-if="pickOutcome(row.home) === 'hit'" icon="mdi:check-circle" class="w-6 h-6 text-green-400" />
                    <Icon v-else-if="pickOutcome(row.home) === 'miss'" icon="mdi:close-circle" class="w-6 h-6 text-red-400" />
                    <Icon v-else-if="pickOutcome(row.home) === 'push'" icon="mdi:minus-circle" class="w-6 h-6 text-yellow-400" />
                    <Icon v-else icon="mdi:cancel" class="w-6 h-6 text-gray-500" />
                  </div>
                  <template v-else>
                    <p :class="['text-[10px] font-mono font-bold mt-0.5', pickIsLive(row.home) ? 'text-ds-green' : oddsColor(pickDisplayOdds(row.home))]">
                      {{ formatOdds(pickDisplayOdds(row.home)) }}
                      <span v-if="pickIsLive(row.home) && row.home.pick_selection?.current_odds != null" class="font-normal text-gray-500 ml-0.5">live</span>
                    </p>
                  </template>
                  <div v-if="pickGetProgress(row.home)" class="mt-1 pt-1 border-t border-ds-green/10">
                    <div class="flex items-center gap-1">
                      <span :class="['text-[10px] font-bold', progressTrendColor(pickGetProgress(row.home).trend)]">
                        {{ pickGetProgress(row.home).label }}
                      </span>
                      <span v-if="pickGetProgress(row.home).detail" class="text-[8px] text-gray-500 truncate">
                        {{ pickGetProgress(row.home).detail }}
                      </span>
                    </div>
                    <div v-if="pickGetProgress(row.home).progress != null" class="mt-1 h-1 bg-gray-700 rounded-full overflow-hidden">
                      <div
                        :class="['h-full rounded-full transition-all duration-500', pickGetProgress(row.home).trend === 'positive' ? 'bg-ds-green' : pickGetProgress(row.home).trend === 'negative' ? 'bg-ds-red' : 'bg-ds-primary']"
                        :style="{ width: `${Math.min(pickGetProgress(row.home).progress * 100, 100)}%` }"
                      ></div>
                    </div>
                  </div>
                </template>
                <template v-else>
                  <p class="text-xs text-gray-600 py-2">--</p>
                </template>
              </div>

              <!-- Away pick (right) -->
              <div
                :class="[
                  'ds-card p-2.5 text-right relative cursor-pointer',
                  pickIsLive(row.away) ? 'animate-live-glow border border-ds-green/40' : '',
                  !pickIsLive(row.away) && row.away?.pick_selection?.outcome === 'hit' ? 'border-r-2 border-green-500' : '',
                  !pickIsLive(row.away) && row.away?.pick_selection?.outcome === 'miss' ? 'border-r-2 border-red-500' : '',
                ]"
                @click="row.away && emit('open-detail', row.away)"
              >
                <template v-if="row.away">
                  <div class="flex items-center gap-1 mb-1 justify-end">
                    <span v-if="pickIsLive(row.away)" class="text-[9px] font-bold px-1 py-0.5 rounded bg-ds-green/20 text-ds-green flex items-center gap-0.5">
                      <span class="w-1 h-1 rounded-full bg-ds-green animate-pulse"></span>
                      LIVE
                    </span>
                    <span
                      v-else-if="row.away.pick_selection?.outcome && row.away.pick_selection.outcome !== 'pending'"
                      :class="['text-[9px] font-bold px-1 py-0.5 rounded', outcomeBadgeClasses[row.away.pick_selection.outcome]]"
                    >
                      {{ outcomeLabel(row.away.pick_selection.outcome) }}
                    </span>
                  </div>
                  <div class="flex items-start gap-2 flex-row-reverse">
                    <PickImage :pick="row.away.pick_selection" size="sm" />
                    <div class="flex-1 min-w-0">
                      <p class="text-xs text-ds-text-primary truncate">{{ row.away.pick_selection?.description }}</p>
                      <p class="text-[10px] text-gray-500 truncate mt-0.5">
                        {{ row.away.pick_selection?.game_display }}
                        <span v-if="formatGameTime(row.away.pick_selection?.game_time)"> · {{ formatGameTime(row.away.pick_selection?.game_time) }}</span>
                      </p>
                    </div>
                  </div>
                  <p v-if="pickIsGraded(row.away) && !pickIsLive(row.away) && pickResultText(row.away)" class="text-[10px] font-mono text-ds-text-secondary mt-0.5">
                    {{ pickResultText(row.away) }}
                  </p>
                  <div v-if="pickIsLive(row.away) && pickLiveScore(row.away)" class="mt-1">
                    <span v-if="pickLiveScore(row.away).period" class="text-[9px] text-gray-500 mr-1">{{ pickLiveScore(row.away).period }}</span>
                    <span class="text-[10px] font-bold text-ds-green">
                      {{ pickLiveScore(row.away).away }} - {{ pickLiveScore(row.away).home }}
                    </span>
                  </div>
                  <div v-if="pickIsGraded(row.away) && !pickIsLive(row.away)" class="mt-1 flex justify-end">
                    <Icon v-if="pickOutcome(row.away) === 'hit'" icon="mdi:check-circle" class="w-6 h-6 text-green-400" />
                    <Icon v-else-if="pickOutcome(row.away) === 'miss'" icon="mdi:close-circle" class="w-6 h-6 text-red-400" />
                    <Icon v-else-if="pickOutcome(row.away) === 'push'" icon="mdi:minus-circle" class="w-6 h-6 text-yellow-400" />
                    <Icon v-else icon="mdi:cancel" class="w-6 h-6 text-gray-500" />
                  </div>
                  <template v-else>
                    <p :class="['text-[10px] font-mono font-bold mt-0.5', pickIsLive(row.away) ? 'text-ds-green' : oddsColor(pickDisplayOdds(row.away))]">
                      <span v-if="pickIsLive(row.away) && row.away.pick_selection?.current_odds != null" class="font-normal text-gray-500 mr-0.5">live</span>
                      {{ formatOdds(pickDisplayOdds(row.away)) }}
                    </p>
                  </template>
                  <div v-if="pickGetProgress(row.away)" class="mt-1 pt-1 border-t border-ds-green/10">
                    <div class="flex items-center gap-1 justify-end">
                      <span v-if="pickGetProgress(row.away).detail" class="text-[8px] text-gray-500 truncate">
                        {{ pickGetProgress(row.away).detail }}
                      </span>
                      <span :class="['text-[10px] font-bold', progressTrendColor(pickGetProgress(row.away).trend)]">
                        {{ pickGetProgress(row.away).label }}
                      </span>
                    </div>
                    <div v-if="pickGetProgress(row.away).progress != null" class="mt-1 h-1 bg-gray-700 rounded-full overflow-hidden">
                      <div
                        :class="['h-full rounded-full transition-all duration-500', pickGetProgress(row.away).trend === 'positive' ? 'bg-ds-green' : pickGetProgress(row.away).trend === 'negative' ? 'bg-ds-red' : 'bg-ds-primary']"
                        :style="{ width: `${Math.min(pickGetProgress(row.away).progress * 100, 100)}%` }"
                      ></div>
                    </div>
                  </div>
                </template>
                <template v-else>
                  <p class="text-xs text-gray-600 py-2">--</p>
                </template>
              </div>
            </div>
          </template>
        </div>
      </div>

      <!-- Error state -->
      <div v-else class="p-6 text-center">
        <p class="text-sm text-ds-text-tertiary">Could not load matchup.</p>
      </div>
    </div>
  </Teleport>
</template>

<style scoped>
@keyframes score-pop {
  0% { transform: scale(1); }
  50% { transform: scale(1.15); }
  100% { transform: scale(1); }
}
.animate-score-pop {
  animation: score-pop 0.3s ease-out;
}
</style>
