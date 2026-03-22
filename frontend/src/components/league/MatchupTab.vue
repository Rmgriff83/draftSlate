<script setup>
import { computed } from 'vue'
import { Icon } from '@iconify/vue'
import { useSlateStore } from '@/stores/slate'
import { useSlateHelpers } from '@/composables/useSlateHelpers'

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
} = useSlateHelpers()

const matchup = computed(() => slate.myMatchup)
const hasMatchup = computed(() => !!matchup.value)

const myTeamName = computed(() => {
  if (!matchup.value) return ''
  return matchup.value.is_home
    ? matchup.value.home_team?.team_name
    : matchup.value.away_team?.team_name
})

const opponentTeamName = computed(() => {
  if (!matchup.value) return ''
  return matchup.value.is_home
    ? matchup.value.away_team?.team_name
    : matchup.value.home_team?.team_name
})

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
  return {
    positive: 'text-ds-green',
    negative: 'text-ds-red',
    neutral: 'text-gray-400',
  }[trend] || 'text-gray-400'
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

// Build paired rows: each starter slot matched 1:1 by slot_type + slot_number
const pairedSlots = computed(() => {
  if (!matchup.value) return []

  const myStarters = (matchup.value.my_picks || []).filter((p) => p.position === 'starter')
  const oppStarters = (matchup.value.opponent_picks || []).filter((p) => p.position === 'starter')

  // Index opponent picks by slot_type:slot_number for fast lookup
  const oppBySlot = {}
  for (const p of oppStarters) {
    const key = `${p.slot_type}:${p.slot_number}`
    oppBySlot[key] = p
  }

  // Collect all unique slot keys from both sides, preserving type grouping
  const slotKeys = new Set()
  for (const p of myStarters) slotKeys.add(`${p.slot_type}:${p.slot_number}`)
  for (const p of oppStarters) slotKeys.add(`${p.slot_type}:${p.slot_number}`)

  // Group by slot_type, sorted by slot_number
  const typeOrder = ['moneyline', 'spread', 'total', 'player_prop']
  const sorted = [...slotKeys].sort((a, b) => {
    const [typeA, numA] = a.split(':')
    const [typeB, numB] = b.split(':')
    const idxA = typeOrder.indexOf(typeA)
    const idxB = typeOrder.indexOf(typeB)
    if (idxA !== idxB) return idxA - idxB
    return Number(numA) - Number(numB)
  })

  // Index my picks
  const myBySlot = {}
  for (const p of myStarters) {
    myBySlot[`${p.slot_type}:${p.slot_number}`] = p
  }

  // Track type groups for section headers
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
      mine: myBySlot[key] || null,
      opponent: oppBySlot[key] || null,
    })
  }

  return rows
})

const isCompleted = computed(() => matchup.value?.status === 'completed')
</script>

<template>
  <div class="space-y-4">
    <!-- Empty state -->
    <div v-if="!hasMatchup" class="ds-card p-6 text-center">
      <Icon icon="mdi:account-group" class="w-10 h-10 text-gray-600 mx-auto mb-3" />
      <p class="text-gray-400">No matchup scheduled for this week</p>
    </div>

    <template v-else>
      <!-- Score header -->
      <div class="ds-card p-4">
        <div class="flex items-center justify-center gap-6">
          <div class="text-center flex-1">
            <p class="text-xs text-gray-400 mb-1 truncate">{{ myTeamName }}</p>
            <p class="text-3xl font-black text-white animate-score-pop">{{ slate.myScore }}</p>
          </div>
          <span class="text-lg text-gray-500 font-bold">&mdash;</span>
          <div class="text-center flex-1">
            <p class="text-xs text-gray-400 mb-1 truncate">{{ opponentTeamName }}</p>
            <p class="text-3xl font-black text-white animate-score-pop">{{ slate.opponentScore }}</p>
          </div>
        </div>

        <p v-if="isCompleted" class="text-center mt-2">
          <span
            v-if="slate.myScore > slate.opponentScore"
            class="text-xs font-semibold text-green-400 bg-green-500/10 px-2 py-0.5 rounded"
          >
            Victory
          </span>
          <span
            v-else-if="slate.myScore < slate.opponentScore"
            class="text-xs font-semibold text-red-400 bg-red-500/10 px-2 py-0.5 rounded"
          >
            Defeat
          </span>
          <span
            v-else
            class="text-xs font-semibold text-yellow-400 bg-yellow-500/10 px-2 py-0.5 rounded"
          >
            Tie
          </span>
        </p>
      </div>

      <!-- Side-by-side pick comparison -->
      <div class="space-y-1.5">
        <!-- Column headers -->
        <div class="grid grid-cols-2 gap-2 px-1">
          <p class="text-xs font-semibold text-gray-400 uppercase tracking-wider">You</p>
          <p class="text-xs font-semibold text-gray-400 uppercase tracking-wider text-right">Opponent</p>
        </div>

        <template v-for="row in pairedSlots" :key="row.isHeader ? row.slotType : row.key">
          <!-- Type section header -->
          <div v-if="row.isHeader" class="pt-2 pb-0.5 px-1">
            <span
              :class="['text-[10px] font-bold px-1.5 py-0.5 rounded', typeBadgeClasses[row.slotType] || 'bg-gray-600 text-gray-300']"
            >
              {{ typeLabels[row.slotType] || row.slotType }}
            </span>
          </div>

          <!-- Paired pick row -->
          <div v-else class="grid grid-cols-2 gap-2">
            <!-- My pick -->
            <div
              :class="[
                'ds-card p-2.5 text-left relative',
                pickIsLive(row.mine) ? 'animate-live-glow border border-ds-green/40' : '',
                !pickIsLive(row.mine) && row.mine?.pick_selection?.outcome === 'hit' ? 'border-l-2 border-green-500' : '',
                !pickIsLive(row.mine) && row.mine?.pick_selection?.outcome === 'miss' ? 'border-l-2 border-red-500' : '',
              ]"
            >
              <template v-if="row.mine">
                <div class="flex items-center gap-1 mb-1">
                  <!-- LIVE badge -->
                  <span v-if="pickIsLive(row.mine)" class="text-[9px] font-bold px-1 py-0.5 rounded bg-ds-green/20 text-ds-green flex items-center gap-0.5">
                    <span class="w-1 h-1 rounded-full bg-ds-green animate-pulse"></span>
                    LIVE
                  </span>
                  <!-- Outcome badge -->
                  <span
                    v-else-if="row.mine.pick_selection?.outcome && row.mine.pick_selection.outcome !== 'pending'"
                    :class="['text-[9px] font-bold px-1 py-0.5 rounded', outcomeBadgeClasses[row.mine.pick_selection.outcome]]"
                  >
                    {{ outcomeLabel(row.mine.pick_selection.outcome) }}
                  </span>
                </div>
                <p class="text-xs text-white truncate">{{ row.mine.pick_selection?.description }}</p>
                <p class="text-[10px] text-gray-500 truncate mt-0.5">{{ row.mine.pick_selection?.game_display }}</p>
                <!-- Result summary for graded picks -->
                <p v-if="pickIsGraded(row.mine) && !pickIsLive(row.mine) && pickResultText(row.mine)" class="text-[10px] font-mono text-gray-300 mt-0.5">
                  {{ pickResultText(row.mine) }}
                </p>
                <!-- Live score -->
                <div v-if="pickIsLive(row.mine) && pickLiveScore(row.mine)" class="mt-1">
                  <span class="text-[10px] font-bold text-ds-green">
                    {{ pickLiveScore(row.mine).away }} - {{ pickLiveScore(row.mine).home }}
                  </span>
                  <span v-if="pickLiveScore(row.mine).period" class="text-[9px] text-gray-500 ml-1">{{ pickLiveScore(row.mine).period }}</span>
                </div>
                <!-- Graded: outcome icon -->
                <div v-if="pickIsGraded(row.mine) && !pickIsLive(row.mine)" class="mt-1">
                  <Icon
                    v-if="pickOutcome(row.mine) === 'hit'"
                    icon="mdi:check-circle"
                    class="w-6 h-6 text-green-400"
                  />
                  <Icon
                    v-else-if="pickOutcome(row.mine) === 'miss'"
                    icon="mdi:close-circle"
                    class="w-6 h-6 text-red-400"
                  />
                  <Icon
                    v-else-if="pickOutcome(row.mine) === 'push'"
                    icon="mdi:minus-circle"
                    class="w-6 h-6 text-yellow-400"
                  />
                  <Icon
                    v-else
                    icon="mdi:cancel"
                    class="w-6 h-6 text-gray-500"
                  />
                </div>
                <!-- Pending / Live: odds -->
                <template v-else>
                  <p :class="['text-[10px] font-mono font-bold mt-0.5', pickIsLive(row.mine) ? 'text-ds-green' : oddsColor(pickDisplayOdds(row.mine))]">
                    {{ formatOdds(pickDisplayOdds(row.mine)) }}
                    <span v-if="pickIsLive(row.mine) && row.mine.pick_selection?.current_odds != null" class="font-normal text-gray-500 ml-0.5">live</span>
                  </p>
                </template>
                <!-- Progress tracker -->
                <div v-if="pickGetProgress(row.mine)" class="mt-1 pt-1 border-t border-ds-green/10">
                  <div class="flex items-center gap-1">
                    <span :class="['text-[10px] font-bold', progressTrendColor(pickGetProgress(row.mine).trend)]">
                      {{ pickGetProgress(row.mine).label }}
                    </span>
                    <span v-if="pickGetProgress(row.mine).detail" class="text-[8px] text-gray-500 truncate">
                      {{ pickGetProgress(row.mine).detail }}
                    </span>
                  </div>
                  <div v-if="pickGetProgress(row.mine).progress != null" class="mt-1 h-1 bg-gray-700 rounded-full overflow-hidden">
                    <div
                      :class="[
                        'h-full rounded-full transition-all duration-500',
                        pickGetProgress(row.mine).trend === 'positive' ? 'bg-ds-green' : pickGetProgress(row.mine).trend === 'negative' ? 'bg-ds-red' : 'bg-ds-primary',
                      ]"
                      :style="{ width: `${Math.min(pickGetProgress(row.mine).progress * 100, 100)}%` }"
                    ></div>
                  </div>
                </div>
              </template>
              <template v-else>
                <p class="text-xs text-gray-600 py-2">--</p>
              </template>
            </div>

            <!-- Opponent pick -->
            <div
              :class="[
                'ds-card p-2.5 text-right relative',
                pickIsLive(row.opponent) ? 'animate-live-glow border border-ds-green/40' : '',
                !pickIsLive(row.opponent) && row.opponent?.pick_selection?.outcome === 'hit' ? 'border-r-2 border-green-500' : '',
                !pickIsLive(row.opponent) && row.opponent?.pick_selection?.outcome === 'miss' ? 'border-r-2 border-red-500' : '',
              ]"
            >
              <template v-if="row.opponent">
                <div class="flex items-center gap-1 mb-1 justify-end">
                  <!-- LIVE badge -->
                  <span v-if="pickIsLive(row.opponent)" class="text-[9px] font-bold px-1 py-0.5 rounded bg-ds-green/20 text-ds-green flex items-center gap-0.5">
                    <span class="w-1 h-1 rounded-full bg-ds-green animate-pulse"></span>
                    LIVE
                  </span>
                  <!-- Outcome badge -->
                  <span
                    v-else-if="row.opponent.pick_selection?.outcome && row.opponent.pick_selection.outcome !== 'pending'"
                    :class="['text-[9px] font-bold px-1 py-0.5 rounded', outcomeBadgeClasses[row.opponent.pick_selection.outcome]]"
                  >
                    {{ outcomeLabel(row.opponent.pick_selection.outcome) }}
                  </span>
                </div>
                <p class="text-xs text-white truncate">{{ row.opponent.pick_selection?.description }}</p>
                <p class="text-[10px] text-gray-500 truncate mt-0.5">{{ row.opponent.pick_selection?.game_display }}</p>
                <!-- Result summary for graded picks -->
                <p v-if="pickIsGraded(row.opponent) && !pickIsLive(row.opponent) && pickResultText(row.opponent)" class="text-[10px] font-mono text-gray-300 mt-0.5">
                  {{ pickResultText(row.opponent) }}
                </p>
                <!-- Live score -->
                <div v-if="pickIsLive(row.opponent) && pickLiveScore(row.opponent)" class="mt-1">
                  <span v-if="pickLiveScore(row.opponent).period" class="text-[9px] text-gray-500 mr-1">{{ pickLiveScore(row.opponent).period }}</span>
                  <span class="text-[10px] font-bold text-ds-green">
                    {{ pickLiveScore(row.opponent).away }} - {{ pickLiveScore(row.opponent).home }}
                  </span>
                </div>
                <!-- Graded: outcome icon -->
                <div v-if="pickIsGraded(row.opponent) && !pickIsLive(row.opponent)" class="mt-1 flex justify-end">
                  <Icon
                    v-if="pickOutcome(row.opponent) === 'hit'"
                    icon="mdi:check-circle"
                    class="w-6 h-6 text-green-400"
                  />
                  <Icon
                    v-else-if="pickOutcome(row.opponent) === 'miss'"
                    icon="mdi:close-circle"
                    class="w-6 h-6 text-red-400"
                  />
                  <Icon
                    v-else-if="pickOutcome(row.opponent) === 'push'"
                    icon="mdi:minus-circle"
                    class="w-6 h-6 text-yellow-400"
                  />
                  <Icon
                    v-else
                    icon="mdi:cancel"
                    class="w-6 h-6 text-gray-500"
                  />
                </div>
                <!-- Pending / Live: odds -->
                <template v-else>
                  <p :class="['text-[10px] font-mono font-bold mt-0.5', pickIsLive(row.opponent) ? 'text-ds-green' : oddsColor(pickDisplayOdds(row.opponent))]">
                    <span v-if="pickIsLive(row.opponent) && row.opponent.pick_selection?.current_odds != null" class="font-normal text-gray-500 mr-0.5">live</span>
                    {{ formatOdds(pickDisplayOdds(row.opponent)) }}
                  </p>
                </template>
                <!-- Progress tracker -->
                <div v-if="pickGetProgress(row.opponent)" class="mt-1 pt-1 border-t border-ds-green/10">
                  <div class="flex items-center gap-1 justify-end">
                    <span v-if="pickGetProgress(row.opponent).detail" class="text-[8px] text-gray-500 truncate">
                      {{ pickGetProgress(row.opponent).detail }}
                    </span>
                    <span :class="['text-[10px] font-bold', progressTrendColor(pickGetProgress(row.opponent).trend)]">
                      {{ pickGetProgress(row.opponent).label }}
                    </span>
                  </div>
                  <div v-if="pickGetProgress(row.opponent).progress != null" class="mt-1 h-1 bg-gray-700 rounded-full overflow-hidden">
                    <div
                      :class="[
                        'h-full rounded-full transition-all duration-500',
                        pickGetProgress(row.opponent).trend === 'positive' ? 'bg-ds-green' : pickGetProgress(row.opponent).trend === 'negative' ? 'bg-ds-red' : 'bg-ds-primary',
                      ]"
                      :style="{ width: `${Math.min(pickGetProgress(row.opponent).progress * 100, 100)}%` }"
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
    </template>
  </div>
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
