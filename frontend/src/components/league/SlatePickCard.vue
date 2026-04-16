<script setup>
import { computed } from 'vue'
import { Icon } from '@iconify/vue'
import PickImage from '@/components/common/PickImage.vue'
import { useSlateHelpers } from '@/composables/useSlateHelpers'

const props = defineProps({
  pick: { type: Object, required: true },
  isBench: { type: Boolean, default: false },
})

const emit = defineEmits(['tap'])

const {
  sportIcons,
  sportIconColors,
  typeLabels,
  typeBadgeClasses,
  outcomeClasses,
  outcomeBadgeClasses,
  outcomeLabel,
  formatOdds,
  oddsColor,
  formatDrift,
  driftColor,
  isLive,
  liveScore,
  pickProgress,
  pickResultText,
  formatGameTime,
} = useSlateHelpers()

const ps = computed(() => props.pick.pick_selection || {})
const outcome = computed(() => ps.value.outcome || 'pending')
const isLocked = computed(() => props.pick.is_locked)
const isPending = computed(() => outcome.value === 'pending')
const isHit = computed(() => outcome.value === 'hit')
const isMiss = computed(() => outcome.value === 'miss')
const isGraded = computed(() => !isPending.value)
const live = computed(() => isLive(props.pick))
const score = computed(() => liveScore(props.pick))
const progress = computed(() => live.value ? pickProgress(props.pick) : null)

const displayOdds = computed(() =>
  isLocked.value ? props.pick.locked_odds : (ps.value.current_odds ?? ps.value.snapshot_odds)
)

const drift = computed(() => props.pick.odds_drift)

const gameTimeCountdown = computed(() => {
  if (!ps.value.game_time) return null
  const diff = new Date(ps.value.game_time) - new Date()
  if (diff <= 0) return null
  const hours = Math.floor(diff / 3600000)
  const mins = Math.floor((diff % 3600000) / 60000)
  if (hours > 24) {
    const days = Math.floor(hours / 24)
    return `${days}d`
  }
  return hours > 0 ? `${hours}h ${mins}m` : `${mins}m`
})

const resultText = computed(() => {
  if (!isGraded.value || live.value) return null
  return pickResultText(props.pick)
})

const trendColor = computed(() => {
  if (!progress.value) return ''
  return {
    positive: 'text-ds-green',
    negative: 'text-ds-red',
    neutral: 'text-gray-400',
  }[progress.value.trend] || 'text-gray-400'
})

const cardClasses = computed(() => {
  const base = 'ds-card p-3 cursor-pointer transition-all relative'
  if (live.value) return [base, 'animate-live-glow border border-ds-green/40', props.isBench ? 'opacity-80' : ''].filter(Boolean).join(' ')
  const outcomeClass = outcomeClasses[outcome.value] || ''
  const mutedClass = props.isBench ? 'opacity-70' : ''
  const animClass = isHit.value ? 'animate-hit-flash' : (isMiss.value ? 'animate-miss-flash' : '')
  return [base, outcomeClass, mutedClass, animClass].filter(Boolean).join(' ')
})
</script>

<template>
  <div :class="cardClasses" @click="emit('tap', pick)">
    <div class="flex items-center gap-3">
      <!-- Pick image (headshot / team logo / sport icon fallback) -->
      <PickImage :pick="ps" size="md" />

      <!-- Main content -->
      <div class="flex-1 min-w-0">
        <div class="flex items-center gap-2 mb-0.5">
          <!-- Type badge -->
          <span
            :class="['text-[10px] font-bold px-1.5 py-0.5 rounded', typeBadgeClasses[ps.pick_type] || 'bg-gray-600 text-gray-300']"
          >
            {{ typeLabels[ps.pick_type] || ps.pick_type }}
          </span>

          <!-- LIVE badge -->
          <span v-if="live" class="text-[10px] font-bold px-1.5 py-0.5 rounded bg-ds-green/20 text-ds-green flex items-center gap-1">
            <span class="w-1.5 h-1.5 rounded-full bg-ds-green animate-pulse"></span>
            LIVE
          </span>

          <!-- Outcome badge (if graded) -->
          <span
            v-else-if="!isPending"
            :class="['text-[10px] font-bold px-1.5 py-0.5 rounded', outcomeBadgeClasses[outcome]]"
          >
            {{ outcomeLabel(outcome) }}
          </span>

          <!-- Lock icon -->
          <Icon
            v-if="isLocked && isPending && !live"
            icon="mdi:lock"
            class="w-3.5 h-3.5 text-yellow-500"
          />
        </div>

        <p class="text-sm text-ds-text-primary font-medium truncate">{{ ps.description }}</p>

        <!-- Game display + game status / result -->
        <div class="flex items-center gap-2">
          <p class="text-xs text-gray-400 truncate">
            {{ ps.game_display }}
            <span v-if="formatGameTime(ps.game_time)" class="text-gray-500"> · {{ formatGameTime(ps.game_time) }}</span>
          </p>
          <template v-if="live && score">
            <span class="text-[10px] text-gray-500">·</span>
            <span class="text-xs font-bold text-ds-green">
              {{ score.away }} - {{ score.home }}
            </span>
            <span v-if="score.gameStatus" class="text-[10px] text-gray-500">{{ score.gameStatus }}</span>
          </template>
          <template v-else-if="isGraded && resultText">
            <span class="text-[10px] text-gray-500">·</span>
            <span class="text-xs font-mono text-ds-text-secondary">{{ resultText }}</span>
          </template>
        </div>
      </div>

      <!-- Right side: outcome icon OR odds + timing -->
      <div class="flex-shrink-0 text-right flex items-center justify-end">
        <!-- Graded: large outcome icon -->
        <template v-if="isGraded && !live">
          <Icon
            v-if="isHit"
            icon="mdi:check-circle"
            class="w-8 h-8 text-green-400"
          />
          <Icon
            v-else-if="isMiss"
            icon="mdi:close-circle"
            class="w-8 h-8 text-red-400"
          />
          <Icon
            v-else-if="outcome === 'push'"
            icon="mdi:minus-circle"
            class="w-8 h-8 text-yellow-400"
          />
          <Icon
            v-else
            icon="mdi:cancel"
            class="w-8 h-8 text-gray-500"
          />
        </template>

        <!-- Pending / Live: odds + timing -->
        <div v-else>
          <p :class="['text-sm font-bold', live ? 'text-ds-green' : oddsColor(displayOdds)]">
            {{ formatOdds(displayOdds) }}
          </p>

          <!-- Drift indicator -->
          <p
            v-if="isLocked && drift && !live"
            :class="['text-[10px]', driftColor(drift)]"
          >
            {{ formatDrift(drift) }}
          </p>

          <!-- Countdown (unlocked pending only, not yet live) -->
          <p
            v-else-if="isPending && !isLocked && gameTimeCountdown"
            class="text-[10px] text-gray-500"
          >
            {{ gameTimeCountdown }}
          </p>
        </div>
      </div>
    </div>

    <!-- Progress tracker (live picks only) -->
    <div v-if="live && progress" class="mt-2 pt-2 border-t border-ds-green/10">
      <div class="flex items-center justify-between">
        <span :class="['text-sm font-bold', trendColor]">
          {{ progress.label }}
        </span>
        <span v-if="progress.detail" class="text-[10px] text-gray-500">
          {{ progress.detail }}
        </span>
      </div>
      <!-- Progress bar for player props -->
      <div v-if="progress.progress != null" class="mt-1.5 h-1.5 bg-gray-700 rounded-full overflow-hidden">
        <div
          :class="[
            'h-full rounded-full transition-all duration-500',
            progress.trend === 'positive' ? 'bg-ds-green' : progress.trend === 'negative' ? 'bg-ds-red' : 'bg-ds-primary',
          ]"
          :style="{ width: `${Math.min(progress.progress * 100, 100)}%` }"
        ></div>
      </div>
    </div>
  </div>
</template>
