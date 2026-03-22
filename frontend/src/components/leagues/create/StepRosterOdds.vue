<script setup>
import { computed, watch } from 'vue'

const props = defineProps({
  modelValue: { type: Object, required: true },
})

const emit = defineEmits(['update:modelValue', 'valid'])

const slotTypes = [
  { key: 'moneyline', label: 'Moneyline' },
  { key: 'spread', label: 'Spread' },
  { key: 'total', label: 'Over/Under' },
  { key: 'player_prop', label: 'Player Prop' },
]

const rosterConfig = computed(() => props.modelValue.roster_config || {})

const totalStarters = computed(() =>
  Object.values(rosterConfig.value).reduce((sum, v) => sum + (v || 0), 0)
)

function updateSlotCount(type, delta) {
  const current = rosterConfig.value[type] || 0
  const next = Math.max(0, Math.min(4, current + delta))
  const newConfig = { ...rosterConfig.value, [type]: next }
  const newTotal = Object.values(newConfig).reduce((sum, v) => sum + (v || 0), 0)
  if (newTotal > 8) return
  emit('update:modelValue', { ...props.modelValue, roster_config: newConfig })
}

function updateFloor(value) {
  emit('update:modelValue', { ...props.modelValue, aggregate_odds_floor: parseInt(value) || -250 })
}

watch(
  () => totalStarters.value,
  (total) => {
    emit('valid', total >= 1 && total <= 8)
  },
  { immediate: true }
)
</script>

<template>
  <div class="space-y-4">
    <div>
      <label class="block text-sm font-medium text-ds-text-primary mb-2">Starter Slots by Type</label>
      <p class="text-xs text-ds-text-tertiary mb-3">Configure how many starter slots of each pick type (max 8 total)</p>

      <div class="space-y-2">
        <div
          v-for="type in slotTypes"
          :key="type.key"
          class="flex items-center justify-between p-2.5 rounded-ds-sm bg-ds-bg-secondary"
        >
          <span class="text-sm font-medium text-ds-text-primary">{{ type.label }}</span>
          <div class="flex items-center gap-3">
            <button
              @click="updateSlotCount(type.key, -1)"
              :disabled="(rosterConfig[type.key] || 0) <= 0"
              class="w-8 h-8 rounded-ds-sm bg-ds-bg-primary border border-ds-border text-ds-text-primary hover:bg-ds-bg-hover transition-colors disabled:opacity-30"
            >
              -
            </button>
            <span class="text-lg font-bold text-ds-text-primary w-6 text-center">{{ rosterConfig[type.key] || 0 }}</span>
            <button
              @click="updateSlotCount(type.key, 1)"
              :disabled="(rosterConfig[type.key] || 0) >= 4 || totalStarters >= 8"
              class="w-8 h-8 rounded-ds-sm bg-ds-bg-primary border border-ds-border text-ds-text-primary hover:bg-ds-bg-hover transition-colors disabled:opacity-30"
            >
              +
            </button>
          </div>
        </div>
      </div>

      <div class="mt-3 flex items-center justify-between text-xs">
        <span class="text-ds-text-secondary font-medium">Total Starters: {{ totalStarters }}</span>
        <span class="text-ds-text-tertiary">Bench: {{ totalStarters }} slots (1:1 ratio)</span>
      </div>
    </div>

    <div>
      <label class="block text-sm font-medium text-ds-text-primary mb-1">Aggregate Odds Floor</label>
      <input
        :value="modelValue.aggregate_odds_floor"
        @input="updateFloor($event.target.value)"
        type="number"
        max="-100"
        step="10"
        class="w-full px-3 py-2 bg-ds-bg-primary border border-ds-border rounded-ds-sm text-ds-text-primary focus:outline-none focus:ring-2 focus:ring-ds-primary/50"
      />
      <p class="text-xs text-ds-text-tertiary mt-1">Average odds across all starters must stay riskier than this floor</p>
    </div>
  </div>
</template>
