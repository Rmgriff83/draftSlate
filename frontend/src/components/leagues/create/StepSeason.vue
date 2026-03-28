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
  { value: 'A', label: 'Top 4', desc: 'Top 4 seeds, single elimination (2 playoff weeks)' },
  { value: 'B', label: 'Top 4 + Consolation', desc: 'Top 4 with 3rd place game (2 playoff weeks)' },
  { value: 'C', label: 'Top 6 Brackets', desc: 'Top 6 seeds with byes for 1-2 (3 playoff weeks)' },
  { value: 'D', label: 'Full League Playoffs', desc: 'All teams compete in playoffs (3 playoff weeks)' },
]

watch(
  () => [props.modelValue.total_matchups, props.modelValue.playoff_format],
  ([matchups]) => {
    emit('valid', matchups >= 5 && matchups <= 52)
  },
  { immediate: true }
)
</script>

<template>
  <div class="space-y-4">
    <div>
      <label class="block text-sm font-medium text-ds-text-primary mb-1">Total Matchups</label>
      <div class="flex items-center gap-3">
        <button
          @click="update('total_matchups', Math.max(5, modelValue.total_matchups - 1))"
          class="w-10 h-10 rounded-ds-sm bg-ds-bg-secondary border border-ds-border text-ds-text-primary hover:bg-ds-bg-hover transition-colors"
        >
          -
        </button>
        <span class="text-lg font-bold text-ds-text-primary w-8 text-center">{{ modelValue.total_matchups }}</span>
        <button
          @click="update('total_matchups', Math.min(52, modelValue.total_matchups + 1))"
          class="w-10 h-10 rounded-ds-sm bg-ds-bg-secondary border border-ds-border text-ds-text-primary hover:bg-ds-bg-hover transition-colors"
        >
          +
        </button>
        <span class="text-xs text-ds-text-tertiary">matchups</span>
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
