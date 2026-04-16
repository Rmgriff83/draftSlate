<script setup>
import { computed } from 'vue'
import { useSlateStore } from '@/stores/slate'
import { useSlateHelpers } from '@/composables/useSlateHelpers'
import SlatePickCard from './SlatePickCard.vue'

const emit = defineEmits(['open-detail'])

const slate = useSlateStore()
const { typeLabels } = useSlateHelpers()

const startersByType = computed(() => {
  const grouped = {}
  for (const pick of slate.starters) {
    const type = pick.pick_selection?.pick_type || 'unknown'
    if (!grouped[type]) grouped[type] = []
    grouped[type].push(pick)
  }
  return grouped
})
</script>

<template>
  <div class="space-y-4">
    <!-- Status line -->
    <div class="ds-card p-3">
      <p class="text-sm text-ds-text-secondary text-center">
        {{ slate.statusLine }}
      </p>
    </div>

    <!-- Starters by type -->
    <div v-for="(picks, type) in startersByType" :key="type" class="space-y-2">
      <h3 class="text-xs font-semibold text-gray-400 uppercase tracking-wider px-1">
        {{ typeLabels[type] || type }} Starters
      </h3>
      <SlatePickCard
        v-for="pick in picks"
        :key="pick.id"
        :pick="pick"
        @tap="emit('open-detail', $event)"
      />
    </div>

    <!-- Empty starters state -->
    <div v-if="slate.starters.length === 0 && !slate.loading" class="ds-card p-6 text-center">
      <p class="text-gray-400">No picks yet. Complete a draft to fill your slate.</p>
    </div>

    <!-- Bench section -->
    <div v-if="slate.bench.length > 0" class="space-y-2">
      <div class="border-t border-gray-700 my-4"></div>
      <h3 class="text-xs font-semibold text-gray-500 uppercase tracking-wider px-1">
        Bench
      </h3>
      <SlatePickCard
        v-for="pick in slate.bench"
        :key="pick.id"
        :pick="pick"
        :is-bench="true"
        @tap="emit('open-detail', $event)"
      />
    </div>
  </div>
</template>
