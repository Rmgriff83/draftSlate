<script setup>
import { computed } from 'vue'

const props = defineProps({
  leagues: { type: Array, default: () => [] },
})

const stats = computed(() => {
  const active = props.leagues.filter((l) => ['active', 'playoffs'].includes(l.state))
  // Stats will be populated when membership records include W-L-T data
  return {
    activeCount: active.length,
    totalLeagues: props.leagues.filter((l) => l.state !== 'cancelled').length,
  }
})
</script>

<template>
  <div class="grid grid-cols-2 gap-3">
    <div class="ds-card p-4 text-center">
      <p class="text-2xl font-bold text-ds-primary">{{ stats.totalLeagues }}</p>
      <p class="text-xs text-ds-text-secondary mt-1">Total Leagues</p>
    </div>
    <div class="ds-card p-4 text-center">
      <p class="text-2xl font-bold text-ds-green">{{ stats.activeCount }}</p>
      <p class="text-xs text-ds-text-secondary mt-1">Active Now</p>
    </div>
  </div>
</template>
