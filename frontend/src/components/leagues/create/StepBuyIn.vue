<script setup>
import { watch } from 'vue'

const props = defineProps({
  modelValue: { type: Object, required: true },
})

const emit = defineEmits(['update:modelValue', 'valid'])

function update(field, value) {
  emit('update:modelValue', { ...props.modelValue, [field]: value })
}

function selectPayout(preset) {
  const structures = {
    wta: { first: 100 },
    top3: { first: 60, second: 25, third: 15 },
    top3weekly: { first: 50, second: 20, third: 10, weekly: 20 },
  }
  update('payout_structure', structures[preset])
}

function currentPreset() {
  const ps = props.modelValue.payout_structure
  if (ps?.first === 100 && !ps?.second) return 'wta'
  if (ps?.first === 60 && ps?.second === 25 && ps?.third === 15 && !ps?.weekly) return 'top3'
  if (ps?.first === 50 && ps?.second === 20 && ps?.third === 10 && ps?.weekly === 20) return 'top3weekly'
  return 'custom'
}

watch(
  () => [props.modelValue.buy_in, props.modelValue.payout_structure],
  ([buyIn, payout]) => {
    emit('valid', buyIn >= 5 && payout && Object.keys(payout).length > 0)
  },
  { immediate: true }
)
</script>

<template>
  <div class="space-y-4">
    <div>
      <label class="block text-sm font-medium text-ds-text-primary mb-1">Buy-in Amount</label>
      <div class="relative">
        <span class="absolute left-3 top-1/2 -translate-y-1/2 text-ds-text-tertiary">$</span>
        <input
          :value="modelValue.buy_in"
          @input="update('buy_in', parseFloat($event.target.value) || 0)"
          type="number"
          min="5"
          step="5"
          class="w-full pl-7 pr-4 py-2 bg-ds-bg-primary border border-ds-border rounded-ds-sm text-ds-text-primary focus:outline-none focus:ring-2 focus:ring-ds-primary/50"
        />
      </div>
      <p v-if="modelValue.buy_in < 5" class="text-xs text-ds-red mt-1">Minimum buy-in is $5</p>
    </div>

    <div>
      <label class="block text-sm font-medium text-ds-text-primary mb-2">Payout Structure</label>
      <div class="space-y-2">
        <button
          @click="selectPayout('wta')"
          class="w-full ds-card p-3 text-left transition-all duration-ds-fast"
          :class="currentPreset() === 'wta' ? 'ring-2 ring-ds-primary' : ''"
        >
          <p class="text-sm font-semibold text-ds-text-primary">Winner Take All</p>
          <p class="text-xs text-ds-text-tertiary">1st place gets 100%</p>
        </button>
        <button
          @click="selectPayout('top3')"
          class="w-full ds-card p-3 text-left transition-all duration-ds-fast"
          :class="currentPreset() === 'top3' ? 'ring-2 ring-ds-primary' : ''"
        >
          <p class="text-sm font-semibold text-ds-text-primary">Top 3</p>
          <p class="text-xs text-ds-text-tertiary">60% / 25% / 15%</p>
        </button>
        <button
          @click="selectPayout('top3weekly')"
          class="w-full ds-card p-3 text-left transition-all duration-ds-fast"
          :class="currentPreset() === 'top3weekly' ? 'ring-2 ring-ds-primary' : ''"
        >
          <p class="text-sm font-semibold text-ds-text-primary">Top 3 + Weekly Prize</p>
          <p class="text-xs text-ds-text-tertiary">50% / 20% / 10% + 20% weekly</p>
        </button>
      </div>
    </div>

    <div class="ds-card p-3 bg-ds-bg-hover">
      <p class="text-xs text-ds-text-secondary">
        <strong>Total pot:</strong> ${{ (modelValue.buy_in * modelValue.max_teams).toFixed(2) }}
        ({{ modelValue.max_teams }} teams &times; ${{ modelValue.buy_in }})
      </p>
    </div>
  </div>
</template>
