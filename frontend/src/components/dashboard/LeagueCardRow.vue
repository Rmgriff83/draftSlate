<script setup>
import { useRouter } from 'vue-router'

defineProps({
  leagues: { type: Array, default: () => [] },
})

const router = useRouter()

function stateLabel(state) {
  const labels = { pending: 'Forming', active: 'Active', playoffs: 'Playoffs', completed: 'Done', cancelled: 'Cancelled' }
  return labels[state] || state
}

function stateColor(state) {
  const colors = {
    pending: 'bg-ds-yellow/20 text-ds-yellow',
    active: 'bg-ds-green/20 text-ds-green',
    playoffs: 'bg-ds-primary/20 text-ds-primary',
    completed: 'bg-ds-text-tertiary/20 text-ds-text-tertiary',
    cancelled: 'bg-ds-red/20 text-ds-red',
  }
  return colors[state] || ''
}
</script>

<template>
  <div class="overflow-x-auto -mx-4 px-4">
    <div class="flex gap-3 snap-x snap-mandatory" style="min-width: min-content">
      <div
        v-for="league in leagues"
        :key="league.id"
        class="ds-card-interactive p-4 min-w-[220px] max-w-[260px] snap-start flex-shrink-0 cursor-pointer"
        @click="router.push(`/app/leagues/${league.id}`)"
      >
        <div class="flex items-start justify-between mb-2">
          <h3 class="text-sm font-semibold text-ds-text-primary truncate pr-2">{{ league.name }}</h3>
          <span class="text-[10px] font-medium px-1.5 py-0.5 rounded-full flex-shrink-0" :class="stateColor(league.state)">
            {{ stateLabel(league.state) }}
          </span>
        </div>
        <p class="text-xs text-ds-text-secondary mb-1">${{ league.buy_in }} buy-in</p>
        <p class="text-xs text-ds-text-tertiary">{{ league.member_count }}/{{ league.max_teams }} teams</p>
      </div>
    </div>
  </div>
</template>
