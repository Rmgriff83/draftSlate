<script setup>
import { computed } from 'vue'
import { useSlateStore } from '@/stores/slate'

defineProps({
  selectedWeek: { type: Number, required: true },
  maxWeek: { type: Number, required: true },
})

defineEmits(['prev-week', 'next-week'])

const slate = useSlateStore()

const team = computed(() =>
  slate.standings.find((s) => s.is_current_user)
)

function initials(name) {
  if (!name) return '?'
  return name
    .split(' ')
    .map((w) => w[0])
    .join('')
    .toUpperCase()
    .slice(0, 2)
}
</script>

<template>
  <div class="ds-card px-4 py-3 flex items-center gap-3">
    <!-- Left: Avatar + team info -->
    <div class="flex items-center gap-3 flex-1 min-w-0">
      <div class="w-10 h-10 rounded-full bg-ds-primary text-white flex items-center justify-center text-sm font-bold flex-shrink-0">
        {{ initials(team?.team_name) }}
      </div>
      <div class="min-w-0">
        <p class="text-sm font-bold text-ds-text-primary truncate">
          {{ team?.team_name || 'My Team' }}
        </p>
        <p class="text-xs text-ds-text-tertiary font-mono">
          {{ team?.wins ?? 0 }}-{{ team?.losses ?? 0 }}<span v-if="team?.ties > 0">-{{ team.ties }}</span>
          <span class="font-sans"> · #{{ team?.rank ?? '—' }} · this week: {{ slate.hitCount }}/{{ slate.starters.length }}</span>
        </p>
      </div>
    </div>

    <!-- Right: Week selector -->
    <div class="flex items-center gap-2 flex-shrink-0">
      <button
        @click="$emit('prev-week')"
        :disabled="selectedWeek <= 1"
        class="p-1.5 rounded-full text-ds-text-tertiary hover:text-ds-text-primary hover:bg-ds-bg-hover transition-colors disabled:opacity-30 disabled:cursor-not-allowed"
      >
        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
          <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5L8.25 12l7.5-7.5" />
        </svg>
      </button>
      <span class="text-sm font-semibold text-ds-text-primary min-w-[72px] text-center">
        Week {{ selectedWeek }}
      </span>
      <button
        @click="$emit('next-week')"
        :disabled="selectedWeek >= maxWeek"
        class="p-1.5 rounded-full text-ds-text-tertiary hover:text-ds-text-primary hover:bg-ds-bg-hover transition-colors disabled:opacity-30 disabled:cursor-not-allowed"
      >
        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
          <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 4.5l7.5 7.5-7.5 7.5" />
        </svg>
      </button>
    </div>
  </div>
</template>
