<script setup>
import { computed } from 'vue'
import { Icon } from '@iconify/vue'
import { useDraftStore } from '@/stores/draft'

const draft = useDraftStore()

const latest = computed(() => draft.pickFeed[0] || null)

const typeLabels = {
  moneyline: 'ML',
  spread: 'Spread',
  total: 'O/U',
  player_prop: 'Prop',
}

const typeBadgeClasses = {
  moneyline: 'bg-blue-500/20 text-blue-400',
  spread: 'bg-purple-500/20 text-purple-400',
  total: 'bg-orange-500/20 text-orange-400',
  player_prop: 'bg-green-500/20 text-green-400',
}

const sportIcons = {
  basketball_nba: 'mdi:basketball',
  americanfootball_nfl: 'mdi:football',
  baseball_mlb: 'mdi:baseball',
  icehockey_nhl: 'mdi:hockey-puck',
}

const sportIconColors = {
  basketball_nba: 'text-orange-400',
  americanfootball_nfl: 'text-amber-600',
  baseball_mlb: 'text-red-400',
  icehockey_nhl: 'text-sky-400',
}

function formatOdds(odds) {
  return odds > 0 ? `+${odds}` : `${odds}`
}
</script>

<template>
  <div v-if="latest" class="h-7 overflow-hidden relative">
    <Transition name="feed-slide" mode="out-in">
      <div
        :key="latest.id"
        class="flex items-center gap-1.5 px-1 absolute inset-0"
      >
        <Icon
          v-if="sportIcons[latest.sport]"
          :icon="sportIcons[latest.sport]"
          class="w-3.5 h-3.5 flex-shrink-0"
          :class="sportIconColors[latest.sport] || 'text-ds-text-tertiary'"
        />
        <span
          class="text-[10px] font-bold px-1.5 py-0.5 rounded flex-shrink-0"
          :class="typeBadgeClasses[latest.pick_type] || 'bg-ds-bg-hover text-ds-text-tertiary'"
        >{{ typeLabels[latest.pick_type] || latest.pick_type }}</span>
        <p class="text-xs text-ds-text-secondary truncate">
          <span class="font-semibold text-ds-text-primary">{{ latest.drafter_team }}</span>
          picked
          <span class="font-medium text-ds-text-primary">{{ latest.description }}</span>
          <span class="font-mono text-ds-text-tertiary ml-1">{{ formatOdds(latest.drafted_odds) }}</span>
        </p>
      </div>
    </Transition>
  </div>
</template>

<style scoped>
.feed-slide-enter-active {
  transition: all 0.35s cubic-bezier(0.16, 1, 0.3, 1);
}
.feed-slide-leave-active {
  transition: all 0.25s cubic-bezier(0.65, 0, 0.35, 1);
}
.feed-slide-enter-from {
  opacity: 0;
  transform: translateY(100%);
}
.feed-slide-leave-to {
  opacity: 0;
  transform: translateY(-100%);
}
</style>
