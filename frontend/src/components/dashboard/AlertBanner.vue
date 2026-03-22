<script setup>
import { computed } from 'vue'

const props = defineProps({
  leagues: { type: Array, default: () => [] },
})

const draftAlert = computed(() => {
  const league = props.leagues.find((l) => l.state === 'pending' && l.member_count >= l.max_teams)
  if (!league) return null
  return { name: league.name, id: league.id }
})
</script>

<template>
  <div v-if="draftAlert" class="ds-card bg-ds-primary/10 border-ds-primary/30 p-4">
    <div class="flex items-center gap-3">
      <svg class="w-5 h-5 text-ds-primary flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
        <path stroke-linecap="round" stroke-linejoin="round" d="M14.857 17.082a23.848 23.848 0 005.454-1.31A8.967 8.967 0 0118 9.75v-.7V9A6 6 0 006 9v.75a8.967 8.967 0 01-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 01-5.714 0m5.714 0a3 3 0 11-5.714 0" />
      </svg>
      <div>
        <p class="text-sm font-semibold text-ds-text-primary">Draft coming soon!</p>
        <p class="text-xs text-ds-text-secondary mt-0.5">
          <router-link :to="`/app/leagues/${draftAlert.id}`" class="text-ds-primary hover:underline">{{ draftAlert.name }}</router-link>
          is full and ready to draft.
        </p>
      </div>
    </div>
  </div>
</template>
