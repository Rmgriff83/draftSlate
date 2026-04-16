<script setup>
import { ref, computed, onMounted } from 'vue'
import { Icon } from '@iconify/vue'
import { useSlateStore } from '@/stores/slate'
import { useSlateHelpers } from '@/composables/useSlateHelpers'
import { usePlayerHeadshot } from '@/composables/usePlayerHeadshot'
import { useTeamLogo } from '@/composables/useTeamLogo'
import GameLogBarChart from '@/components/draft/GameLogBarChart.vue'
import OddsChart from '@/components/league/OddsChart.vue'
import api from '@/utils/api'

const props = defineProps({
  pick: { type: Object, required: true },
})

const emit = defineEmits(['close', 'swap'])

const slate = useSlateStore()

const {
  sportIcons,
  sportIconColors,
  typeLabels,
  typeBadgeClasses,
  outcomeBadgeClasses,
  outcomeLabel,
  formatOdds,
  oddsColor,
  formatDrift,
  driftColor,
  isLive,
  liveScore,
  formatGameTime,
} = useSlateHelpers()

const showMoveMenu = ref(false)
const activeTab = ref('gamelog')
const studyLoading = ref(true)
const studyError = ref('')
const studyData = ref(null)

const ps = computed(() => props.pick.pick_selection || {})
const { headshotUrl, loadHeadshot } = usePlayerHeadshot(ps.value)
const { homeLogoUrl, awayLogoUrl, loadTeamLogos } = useTeamLogo(ps.value)

async function fetchStudyData() {
  if (!ps.value.id) {
    studyLoading.value = false
    return
  }
  studyLoading.value = true
  studyError.value = ''
  try {
    const { data } = await api.get(`/api/v1/picks/${ps.value.id}/study`)
    studyData.value = data.study
  } catch {
    studyError.value = 'Failed to load study data'
  } finally {
    studyLoading.value = false
  }
}

onMounted(() => {
  loadHeadshot()
  loadTeamLogos()
  fetchStudyData()
})

const outcome = computed(() => ps.value.outcome || 'pending')
const isPending = computed(() => outcome.value === 'pending')
const isLocked = computed(() => props.pick.is_locked)
const isStarter = computed(() => props.pick.position === 'starter')
const gameStarted = computed(() => {
  const gt = ps.value.game_time
  if (!gt) return false
  return new Date(gt) <= new Date()
})
const canSwap = computed(() => !isLocked.value && !gameStarted.value)
const pickType = computed(() => ps.value.pick_type || props.pick.slot_type)

function pickGameStarted(pick) {
  const gt = pick?.pick_selection?.game_time
  if (!gt) return false
  return new Date(gt) <= new Date()
}

const draftedOdds = computed(() => props.pick.drafted_odds)
const displayOdds = computed(() =>
  isLocked.value ? props.pick.locked_odds : (ps.value.current_odds ?? ps.value.snapshot_odds)
)
const drift = computed(() => props.pick.odds_drift)

// Result data for completed / live games
const resultData = computed(() => ps.value.result_data || null)
const gameScore = computed(() => liveScore(props.pick))
const isCompleted = computed(() => !!resultData.value?.completed)
const isGameLive = computed(() => isLive(props.pick))

const playerStats = computed(() => resultData.value?.player_stats || null)
const currentStat = computed(() => resultData.value?.current_stat ?? null)
const statLabel = computed(() => resultData.value?.stat_label || null)

const propLine = computed(() => {
  const m = (ps.value.description || '').match(/(?:Over|Under)\s+([\d.]+)/i)
  return m ? parseFloat(m[1]) : null
})

const propSide = computed(() => {
  const m = (ps.value.description || '').match(/(Over|Under)/i)
  return m ? m[1] : null
})

// Build the list of valid move targets based on the pick's type
const moveTargets = computed(() => {
  const targets = []
  const allPicks = slate.myPicks
  const type = pickType.value
  const currentPickId = props.pick.id

  if (isStarter.value) {
    // Starter can swap with same-type starters or move to bench

    // Other same-type starters (swap positions)
    const sameTypeStarters = allPicks.filter(
      (p) => p.position === 'starter' && p.slot_type === type && p.id !== currentPickId && !p.is_locked && !pickGameStarted(p)
    )
    for (const p of sameTypeStarters) {
      targets.push({
        label: `Swap with ${typeLabels[type]} Starter ${p.slot_number}`,
        sublabel: p.pick_selection?.description,
        position: 'starter',
        slotNumber: p.slot_number,
        slotType: type,
        icon: 'mdi:swap-horizontal',
      })
    }

    // Bench — find an open bench slot, or swap with same-type bench picks
    const sameTypeBench = allPicks.filter(
      (p) => p.position === 'bench' && p.slot_type === type && !p.is_locked && !pickGameStarted(p)
    )

    for (const p of sameTypeBench) {
      targets.push({
        label: `Swap with Bench ${typeLabels[p.slot_type]} ${p.slot_number}`,
        sublabel: p.pick_selection?.description,
        position: 'bench',
        slotNumber: p.slot_number,
        slotType: type,
        icon: 'mdi:swap-horizontal',
      })
    }

    // Move to new bench slot (next available number)
    const benchSlotsOfType = allPicks
      .filter((p) => p.position === 'bench' && p.slot_type === type)
      .map((p) => p.slot_number)
    const nextBenchSlot = benchSlotsOfType.length > 0 ? Math.max(...benchSlotsOfType) + 1 : 1

    targets.push({
      label: 'Move to Bench',
      sublabel: `Open ${typeLabels[type]} bench slot`,
      position: 'bench',
      slotNumber: nextBenchSlot,
      slotType: type,
      icon: 'mdi:arrow-down',
    })
  } else {
    // Bench pick can promote to starter (swap with same-type starter) or swap with other bench

    // Same-type starter slots to swap into
    const sameTypeStarters = allPicks.filter(
      (p) => p.position === 'starter' && p.slot_type === type && !p.is_locked && !pickGameStarted(p)
    )

    for (const p of sameTypeStarters) {
      targets.push({
        label: `Promote to ${typeLabels[type]} Starter ${p.slot_number}`,
        sublabel: `Swap with: ${p.pick_selection?.description}`,
        position: 'starter',
        slotNumber: p.slot_number,
        slotType: type,
        icon: 'mdi:arrow-up',
        primary: true,
      })
    }

    // Other same-type bench picks to swap with
    const sameTypeBench = allPicks.filter(
      (p) => p.position === 'bench' && p.slot_type === type && p.id !== currentPickId && !p.is_locked && !pickGameStarted(p)
    )

    for (const p of sameTypeBench) {
      targets.push({
        label: `Swap with Bench ${typeLabels[p.slot_type]} ${p.slot_number}`,
        sublabel: p.pick_selection?.description,
        position: 'bench',
        slotNumber: p.slot_number,
        slotType: type,
        icon: 'mdi:swap-horizontal',
      })
    }
  }

  return targets
})

function handleMove(target) {
  emit('swap', {
    pickId: props.pick.id,
    targetPosition: target.position,
    targetSlot: target.slotNumber,
    targetSlotType: target.slotType,
  })
}
</script>

<template>
  <Teleport to="body">
    <div class="fixed inset-0 z-50 flex items-end justify-center">
      <div class="fixed inset-0 bg-black/50" @click="emit('close')"></div>
      <div class="ds-card w-full max-w-lg rounded-b-none p-5 relative z-10 animate-slide-up max-h-[80vh] overflow-y-auto">
        <!-- Drag handle -->
        <div class="w-10 h-1 bg-ds-border rounded-full mx-auto mb-4"></div>

        <!-- Header -->
        <div class="flex items-center gap-2 mb-3">
          <Icon
            :icon="sportIcons[ps.sport] || 'mdi:trophy'"
            :class="['w-6 h-6', sportIconColors[ps.sport] || 'text-gray-400']"
          />
          <h3 class="text-lg font-bold text-ds-text-primary flex-1">Pick Details</h3>
          <button @click="emit('close')" class="text-gray-400 hover:text-ds-text-primary">
            <Icon icon="mdi:close" class="w-5 h-5" />
          </button>
        </div>

        <!-- Pick info card -->
        <div class="ds-card bg-ds-bg-hover p-4 mb-4">
          <div class="flex gap-3">
            <!-- Player headshot -->
            <div v-if="ps.pick_type === 'player_prop'" class="flex-shrink-0">
              <img
                v-if="headshotUrl"
                :src="headshotUrl"
                :alt="ps.player_name"
                class="w-12 h-12 rounded-full object-cover bg-ds-bg-primary"
                @error="headshotUrl = null"
              />
              <div v-else class="w-12 h-12 rounded-full bg-ds-bg-primary flex items-center justify-center">
                <Icon icon="mdi:account" class="w-7 h-7 text-gray-600" />
              </div>
            </div>

            <!-- Team logos for game-level picks -->
            <div v-else class="flex-shrink-0 flex items-center gap-1">
              <img
                v-if="awayLogoUrl"
                :src="awayLogoUrl"
                :alt="ps.away_team"
                class="w-8 h-8 object-contain"
                @error="awayLogoUrl = null"
              />
              <div v-else class="w-8 h-8 rounded bg-ds-bg-primary flex items-center justify-center">
                <Icon icon="mdi:shield-outline" class="w-5 h-5 text-gray-600" />
              </div>
              <span class="text-[10px] text-gray-500 font-bold">vs</span>
              <img
                v-if="homeLogoUrl"
                :src="homeLogoUrl"
                :alt="ps.home_team"
                class="w-8 h-8 object-contain"
                @error="homeLogoUrl = null"
              />
              <div v-else class="w-8 h-8 rounded bg-ds-bg-primary flex items-center justify-center">
                <Icon icon="mdi:shield-outline" class="w-5 h-5 text-gray-600" />
              </div>
            </div>

            <div class="flex-1 min-w-0">
              <div class="flex items-center gap-2 mb-2">
                <span
                  :class="['text-xs font-bold px-2 py-0.5 rounded', typeBadgeClasses[ps.pick_type] || 'bg-gray-600 text-gray-300']"
                >
                  {{ typeLabels[ps.pick_type] || ps.pick_type }}
                </span>
                <span v-if="ps.category" class="text-xs text-gray-400">{{ ps.category }}</span>
                <span class="text-[10px] text-gray-500 ml-auto">
                  {{ isStarter ? `Starter ${pick.slot_number}` : `Bench ${pick.slot_number}` }}
                </span>
              </div>

              <p class="text-base font-semibold text-ds-text-primary mb-1">{{ ps.description }}</p>
              <p class="text-sm text-gray-400">{{ ps.game_display }}</p>
              <p v-if="formatGameTime(ps.game_time)" class="text-xs text-gray-500 mt-0.5">{{ formatGameTime(ps.game_time) }}</p>

              <div v-if="ps.player_name" class="mt-2">
                <span class="text-xs text-gray-500">Player:</span>
                <span class="text-sm text-ds-text-secondary ml-1">{{ ps.player_name }}</span>
              </div>
            </div>
          </div>
        </div>

        <!-- Odds section -->
        <div class="ds-card bg-ds-bg-hover p-4 mb-4">
          <h4 class="text-xs font-semibold text-gray-400 uppercase tracking-wider mb-3">Odds</h4>
          <div class="grid grid-cols-2 gap-4">
            <div>
              <p class="text-xs text-gray-500 mb-0.5">Drafted</p>
              <p :class="['text-lg font-bold', oddsColor(draftedOdds)]">
                {{ formatOdds(draftedOdds) }}
              </p>
            </div>
            <div>
              <p class="text-xs text-gray-500 mb-0.5">
                {{ isLocked ? 'Locked' : 'Current' }}
              </p>
              <p :class="['text-lg font-bold', oddsColor(displayOdds)]">
                {{ formatOdds(displayOdds) }}
              </p>
            </div>
          </div>

          <!-- Drift indicator -->
          <div v-if="isLocked && drift" class="mt-2 pt-2 border-t border-gray-700">
            <span class="text-xs text-gray-500">Drift:</span>
            <span :class="['text-sm font-semibold ml-1', driftColor(drift)]">
              {{ formatDrift(drift) }}
            </span>
          </div>
        </div>

        <!-- Sub-tab bar -->
        <div class="flex border-b border-ds-border mb-4">
          <button
            @click="activeTab = 'gamelog'"
            :class="[
              'flex-1 text-sm font-semibold py-2.5 text-center transition-colors border-b-2',
              activeTab === 'gamelog'
                ? 'border-ds-primary text-ds-primary'
                : 'border-transparent text-ds-text-tertiary hover:text-ds-text-secondary'
            ]"
          >
            Game Log
          </button>
          <button
            @click="activeTab = 'movement'"
            :class="[
              'flex-1 text-sm font-semibold py-2.5 text-center transition-colors border-b-2',
              activeTab === 'movement'
                ? 'border-ds-primary text-ds-primary'
                : 'border-transparent text-ds-text-tertiary hover:text-ds-text-secondary'
            ]"
          >
            Line Movement
          </button>
        </div>

        <!-- Tab content -->
        <div class="min-h-[200px] mb-4">
          <!-- Game Log Tab -->
          <template v-if="activeTab === 'gamelog'">
            <div v-if="studyLoading" class="flex items-center justify-center h-[200px]">
              <div class="w-5 h-5 border-2 border-ds-primary border-t-transparent rounded-full animate-spin"></div>
            </div>

            <div v-else-if="studyError" class="flex items-center justify-center h-[200px]">
              <p class="text-xs text-ds-red">{{ studyError }}</p>
            </div>

            <template v-else-if="studyData?.stats_available">
              <!-- Hit rate banner -->
              <div class="ds-card bg-ds-bg-hover p-3 mb-3">
                <div class="flex items-center justify-between">
                  <div>
                    <span class="text-2xl font-black text-ds-text-primary">{{ studyData.hit_count }}/{{ studyData.games_count }}</span>
                    <span class="text-sm text-gray-400 ml-2">
                      {{ studyData.side?.toLowerCase() }} {{ studyData.threshold }}
                    </span>
                  </div>
                  <div class="text-right">
                    <p class="text-lg font-bold text-ds-text-primary">{{ studyData.average }}</p>
                    <p class="text-[10px] text-gray-500 uppercase">avg {{ studyData.stat_label }}</p>
                  </div>
                </div>

                <div class="mt-2 h-1.5 bg-gray-700 rounded-full overflow-hidden">
                  <div
                    class="h-full rounded-full transition-all"
                    :class="studyData.hit_count / studyData.games_count >= 0.6 ? 'bg-green-500' : studyData.hit_count / studyData.games_count >= 0.4 ? 'bg-yellow-500' : 'bg-red-500'"
                    :style="{ width: `${(studyData.hit_count / studyData.games_count) * 100}%` }"
                  ></div>
                </div>
              </div>

              <!-- Bar chart -->
              <GameLogBarChart
                :games="studyData.games"
                :threshold="studyData.threshold"
                :stat-label="studyData.stat_label"
                class="mb-3"
              />

              <!-- Game-by-game table -->
              <div class="ds-card bg-ds-bg-hover overflow-hidden">
                <table class="w-full text-xs">
                  <thead>
                    <tr class="border-b border-ds-border">
                      <th class="text-left text-gray-500 font-medium px-3 py-2">Date</th>
                      <th class="text-left text-gray-500 font-medium px-3 py-2">OPP</th>
                      <th class="text-right text-gray-500 font-medium px-3 py-2">{{ studyData.stat_label }}</th>
                      <th class="text-right text-gray-500 font-medium px-3 py-2">W/L</th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr
                      v-for="(game, i) in studyData.games"
                      :key="i"
                      class="border-b border-ds-border/30 last:border-0"
                    >
                      <td class="px-3 py-2 text-gray-400">{{ game.date }}</td>
                      <td class="px-3 py-2 text-ds-text-secondary font-medium">{{ game.opponent }}</td>
                      <td
                        class="px-3 py-2 text-right font-bold font-mono"
                        :class="game.hit ? 'text-green-400' : 'text-red-400'"
                      >
                        {{ game.stat_value }}
                      </td>
                      <td class="px-3 py-2 text-right text-gray-500">{{ game.result }}</td>
                    </tr>
                  </tbody>
                </table>
              </div>
            </template>

            <div v-else class="flex flex-col items-center justify-center h-[200px] text-center">
              <Icon icon="mdi:chart-bar-stacked" class="w-10 h-10 text-gray-600 mb-2" />
              <p class="text-sm text-gray-400">Game log stats not available</p>
              <p class="text-xs text-gray-500 mt-1">for this pick type</p>
            </div>
          </template>

          <!-- Line Movement Tab -->
          <template v-else-if="activeTab === 'movement'">
            <OddsChart v-if="ps.id" :pick-selection-id="ps.id" />
          </template>
        </div>

        <!-- Result section (completed or live games with score data) -->
        <div v-if="gameScore && (isCompleted || isGameLive)" class="ds-card bg-ds-bg-hover p-4 mb-4">
          <h4 class="text-xs font-semibold text-gray-400 uppercase tracking-wider mb-3">
            {{ isCompleted ? 'Final Score' : 'Live Score' }}
          </h4>

          <!-- Game score -->
          <div class="flex items-center justify-center gap-4 mb-3">
            <div class="text-center flex-1">
              <p class="text-[10px] text-gray-500 mb-0.5 truncate">{{ gameScore.awayTeam }}</p>
              <p class="text-2xl font-black text-ds-text-primary">{{ gameScore.away }}</p>
            </div>
            <div class="text-center">
              <span class="text-xs text-gray-600 font-bold">&mdash;</span>
              <p v-if="gameScore.gameStatus" class="text-[9px] text-gray-500 mt-0.5">{{ gameScore.gameStatus }}</p>
              <p v-else-if="gameScore.period" class="text-[9px] text-gray-500 mt-0.5">Q{{ gameScore.period }}</p>
            </div>
            <div class="text-center flex-1">
              <p class="text-[10px] text-gray-500 mb-0.5 truncate">{{ gameScore.homeTeam }}</p>
              <p class="text-2xl font-black text-ds-text-primary">{{ gameScore.home }}</p>
            </div>
          </div>

          <!-- Player stat (for player props) -->
          <template v-if="ps.pick_type === 'player_prop' && currentStat != null">
            <div class="border-t border-gray-700 pt-3">
              <!-- Key stat vs line -->
              <div class="flex items-center justify-between mb-2">
                <span class="text-xs text-gray-400">{{ ps.player_name }}</span>
                <span class="text-sm font-bold text-ds-text-primary">
                  {{ currentStat }}
                  <span v-if="propLine != null" class="text-gray-500 font-normal">/ {{ propLine }}</span>
                  <span class="text-xs text-gray-500 font-normal ml-1">{{ statLabel }}</span>
                </span>
              </div>

              <!-- Stat result bar -->
              <div v-if="propLine != null" class="mb-2">
                <div class="h-1.5 bg-gray-700 rounded-full overflow-hidden">
                  <div
                    :class="[
                      'h-full rounded-full transition-all',
                      propSide === 'Under'
                        ? (currentStat < propLine ? 'bg-green-500' : 'bg-red-500')
                        : (currentStat > propLine ? 'bg-green-500' : 'bg-red-500'),
                    ]"
                    :style="{ width: `${Math.min((currentStat / propLine) * 100, 100)}%` }"
                  ></div>
                </div>
                <div class="flex justify-between mt-0.5">
                  <span class="text-[9px] text-gray-600">0</span>
                  <span class="text-[9px] text-gray-600">{{ propLine }}</span>
                </div>
              </div>

              <!-- Full stat line (NBA) -->
              <div v-if="playerStats" class="grid grid-cols-5 gap-1 text-center">
                <div>
                  <p class="text-[10px] text-gray-500">PTS</p>
                  <p :class="['text-xs font-semibold', statLabel === 'points' ? 'text-ds-text-primary' : 'text-gray-400']">{{ playerStats.points }}</p>
                </div>
                <div>
                  <p class="text-[10px] text-gray-500">REB</p>
                  <p :class="['text-xs font-semibold', statLabel === 'rebounds' ? 'text-ds-text-primary' : 'text-gray-400']">{{ playerStats.rebounds }}</p>
                </div>
                <div>
                  <p class="text-[10px] text-gray-500">AST</p>
                  <p :class="['text-xs font-semibold', statLabel === 'assists' ? 'text-ds-text-primary' : 'text-gray-400']">{{ playerStats.assists }}</p>
                </div>
                <div>
                  <p class="text-[10px] text-gray-500">3PT</p>
                  <p :class="['text-xs font-semibold', statLabel === 'threes' ? 'text-ds-text-primary' : 'text-gray-400']">{{ playerStats.threes }}</p>
                </div>
                <div>
                  <p class="text-[10px] text-gray-500">MIN</p>
                  <p class="text-xs font-semibold text-gray-400">{{ playerStats.minutes?.replace?.('PT', '')?.replace?.('M', '') || '--' }}</p>
                </div>
              </div>
            </div>
          </template>

          <!-- Total line summary (for totals) -->
          <template v-else-if="ps.pick_type === 'total' && gameScore">
            <div class="border-t border-gray-700 pt-2 text-center">
              <span class="text-xs text-gray-400">Total: </span>
              <span class="text-sm font-bold text-ds-text-primary">{{ gameScore.away + gameScore.home }}</span>
              <span v-if="propLine != null" class="text-xs text-gray-500 ml-1">/ {{ propLine }}</span>
            </div>
          </template>

          <!-- Spread margin summary -->
          <template v-else-if="ps.pick_type === 'spread' && gameScore">
            <div class="border-t border-gray-700 pt-2 text-center">
              <span class="text-xs text-gray-400">Margin: </span>
              <span class="text-sm font-bold text-ds-text-primary">
                {{ Math.abs(gameScore.home - gameScore.away) }}
                <span class="text-xs text-gray-500 font-normal">
                  ({{ gameScore.home > gameScore.away ? gameScore.homeTeam : gameScore.awayTeam }})
                </span>
              </span>
            </div>
          </template>
        </div>

        <!-- Outcome badge (if graded) -->
        <div v-if="!isPending" class="mb-4">
          <div
            :class="[
              'py-4 rounded-lg text-center',
              outcome === 'hit' ? 'bg-green-500/10 border border-green-500/30' : '',
              outcome === 'miss' ? 'bg-red-500/10 border border-red-500/30' : '',
              outcome === 'void' ? 'bg-gray-500/10 border border-gray-500/30' : '',
              outcome === 'push' ? 'bg-yellow-500/10 border border-yellow-500/30' : '',
            ]"
          >
            <span :class="['text-2xl font-black tracking-wider', outcomeBadgeClasses[outcome]]">
              {{ outcomeLabel(outcome) }}
            </span>
          </div>
        </div>

        <!-- Lock indicator -->
        <div v-if="(isLocked || gameStarted) && isPending" class="ds-card bg-ds-bg-hover p-3 mb-4 flex items-center gap-2">
          <Icon icon="mdi:lock" class="w-4 h-4 text-yellow-500" />
          <span class="text-sm text-yellow-400">
            {{ gameStarted && !isLocked ? 'Game in progress — pick locked' : 'Pick locked at game start' }}
          </span>
          <span v-if="pick.locked_at" class="text-xs text-gray-500 ml-auto">
            {{ new Date(pick.locked_at).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }) }}
          </span>
        </div>

        <!-- Move button / menu (only if not locked) -->
        <template v-if="canSwap">
          <!-- Toggle button -->
          <button
            v-if="!showMoveMenu"
            @click="showMoveMenu = true"
            class="w-full px-4 py-2.5 text-sm font-semibold text-white bg-ds-primary hover:bg-ds-primary-light rounded-ds-sm transition-colors flex items-center justify-center gap-2"
          >
            <Icon icon="mdi:swap-vertical" class="w-4 h-4" />
            Move Pick
          </button>

          <!-- Contextual move targets -->
          <div v-else class="space-y-2">
            <div class="flex items-center justify-between mb-1">
              <h4 class="text-xs font-semibold text-gray-400 uppercase tracking-wider">Move to</h4>
              <button @click="showMoveMenu = false" class="text-xs text-ds-primary hover:underline">
                Cancel
              </button>
            </div>

            <button
              v-for="(target, i) in moveTargets"
              :key="i"
              @click="handleMove(target)"
              :class="[
                'w-full flex items-center gap-3 p-3 rounded-lg text-left transition-colors',
                target.primary
                  ? 'bg-ds-primary/10 border border-ds-primary/30 hover:bg-ds-primary/20'
                  : 'bg-ds-bg-hover hover:bg-ds-border',
              ]"
            >
              <Icon
                :icon="target.icon"
                :class="['w-5 h-5 flex-shrink-0', target.primary ? 'text-ds-primary' : 'text-gray-400']"
              />
              <div class="flex-1 min-w-0">
                <p :class="['text-sm font-medium', target.primary ? 'text-ds-primary' : 'text-ds-text-primary']">
                  {{ target.label }}
                </p>
                <p v-if="target.sublabel" class="text-xs text-gray-500 truncate">{{ target.sublabel }}</p>
              </div>
              <Icon icon="mdi:chevron-right" class="w-4 h-4 text-gray-600 flex-shrink-0" />
            </button>

            <p v-if="moveTargets.length === 0" class="text-sm text-gray-500 text-center py-2">
              No valid move targets for this pick type.
            </p>
          </div>
        </template>
      </div>
    </div>
  </Teleport>
</template>

<style scoped>
@keyframes slide-up {
  from { transform: translateY(100%); }
  to { transform: translateY(0); }
}
.animate-slide-up {
  animation: slide-up 0.25s ease-out;
}
</style>
