<script setup>
import { watch } from 'vue'

const props = defineProps({
  modelValue: { type: Object, required: true },
})

const emit = defineEmits(['update:modelValue', 'valid'])

function update(field, value) {
  emit('update:modelValue', { ...props.modelValue, [field]: value })
}

const playoffFormats = [
  { value: 'A', label: 'Single Elimination', desc: 'Top seeds, single elimination bracket' },
  { value: 'B', label: 'Double Elimination', desc: 'Winners and losers brackets' },
  { value: 'C', label: 'Round Robin Finals', desc: 'Top 4 play each other once' },
  { value: 'D', label: 'No Playoffs', desc: 'Best regular season record wins' },
]

watch(
  () => [props.modelValue.regular_season_weeks, props.modelValue.playoff_format],
  ([weeks]) => {
    emit('valid', weeks >= 8 && weeks <= 18)
  },
  { immediate: true }
)
</script>

<template>
  <div class="space-y-4">
    <div>
      <label class="block text-sm font-medium text-ds-text-primary mb-1">Regular Season Weeks</label>
      <div class="flex items-center gap-3">
        <button
          @click="update('regular_season_weeks', Math.max(8, modelValue.regular_season_weeks - 1))"
          class="w-10 h-10 rounded-ds-sm bg-ds-bg-secondary border border-ds-border text-ds-text-primary hover:bg-ds-bg-hover transition-colors"
        >
          -
        </button>
        <span class="text-lg font-bold text-ds-text-primary w-8 text-center">{{ modelValue.regular_season_weeks }}</span>
        <button
          @click="update('regular_season_weeks', Math.min(18, modelValue.regular_season_weeks + 1))"
          class="w-10 h-10 rounded-ds-sm bg-ds-bg-secondary border border-ds-border text-ds-text-primary hover:bg-ds-bg-hover transition-colors"
        >
          +
        </button>
        <span class="text-xs text-ds-text-tertiary">weeks</span>
      </div>
    </div>

    <div>
      <label class="block text-sm font-medium text-ds-text-primary mb-2">Playoff Format</label>
      <div class="space-y-2">
        <button
          v-for="format in playoffFormats"
          :key="format.value"
          @click="update('playoff_format', format.value)"
          class="w-full ds-card p-3 text-left transition-all duration-ds-fast"
          :class="modelValue.playoff_format === format.value ? 'ring-2 ring-ds-primary' : ''"
        >
          <div class="flex items-center gap-2">
            <span class="w-6 h-6 rounded-full flex items-center justify-center text-xs font-bold flex-shrink-0"
              :class="modelValue.playoff_format === format.value ? 'bg-ds-primary text-white' : 'bg-ds-bg-hover text-ds-text-tertiary'">
              {{ format.value }}
            </span>
            <div>
              <p class="text-sm font-semibold text-ds-text-primary">{{ format.label }}</p>
              <p class="text-xs text-ds-text-tertiary">{{ format.desc }}</p>
            </div>
          </div>
        </button>
      </div>
    </div>
  </div>
</template>
