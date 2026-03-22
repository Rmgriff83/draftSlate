<script setup>
import { computed } from 'vue'
import { useDraftStore } from '@/stores/draft'

const draft = useDraftStore()

const minutes = computed(() => Math.floor(draft.timerSeconds / 60))
const seconds = computed(() => draft.timerSeconds % 60)
const timeDisplay = computed(() =>
  `${minutes.value}:${seconds.value.toString().padStart(2, '0')}`
)
const isUrgent = computed(() => draft.timerSeconds <= 10)
const isMyTurn = computed(() => draft.isMyTurn)
</script>

<template>
  <div
    class="ds-card p-4 sticky top-14 z-20 transition-colors duration-300"
    :class="isUrgent ? 'bg-ds-red/10 border-ds-red/30' : isMyTurn ? 'bg-ds-primary/10 border-ds-primary/30' : ''"
  >
    <div class="flex items-center justify-between">
      <div>
        <p class="text-sm font-semibold text-ds-text-primary">
          {{ draft.currentDrafter?.team_name || 'Waiting...' }}'s Pick
        </p>
        <p class="text-xs text-ds-text-tertiary">
          Round {{ draft.draftState?.current_round }}/{{ draft.draftState?.total_rounds }}
          <span v-if="isMyTurn" class="text-ds-primary font-semibold ml-1">Your turn!</span>
        </p>
      </div>
      <div
        class="text-2xl font-mono font-bold transition-all duration-300"
        :class="[
          isUrgent ? 'text-ds-red animate-pulse' : 'text-ds-text-primary',
        ]"
      >
        {{ timeDisplay }}
      </div>
    </div>
  </div>
</template>
