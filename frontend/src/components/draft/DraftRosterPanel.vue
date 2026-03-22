<script setup>
import { computed } from 'vue'
import { Icon } from '@iconify/vue'
import { useDraftStore } from '@/stores/draft'

const draft = useDraftStore()

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

const starterTypeEntries = computed(() => {
  const config = draft.rosterConfig || {}
  const entries = []
  for (const [type, count] of Object.entries(config)) {
    if (count > 0) {
      const filled = draft.myStarters.filter((p) => p.slot_type === type)
      for (let i = 0; i < count; i++) {
        entries.push({
          type,
          label: typeLabels[type] || type,
          pick: filled[i] || null,
          slotIndex: i + 1,
        })
      }
    }
  }
  return entries
})

function formatOdds(odds) {
  return odds > 0 ? `+${odds}` : `${odds}`
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

function getBench(slot) {
  return draft.myBench.find((p) => p.slot_number === slot)
}
</script>

<template>
  <div class="space-y-1">
      <!-- Typed Starters -->
      <div
        v-for="(entry, idx) in starterTypeEntries"
        :key="`s-${entry.type}-${entry.slotIndex}`"
        class="flex items-center gap-2 p-2 rounded-ds-sm"
        :class="entry.pick ? 'bg-ds-primary/5' : 'bg-ds-bg-hover'"
      >
        <span
          class="text-[10px] font-bold px-1.5 py-0.5 rounded"
          :class="typeBadgeClasses[entry.type] || 'bg-ds-bg-hover text-ds-text-tertiary'"
        >
          {{ entry.label }}
        </span>
        <template v-if="entry.pick">
          <Icon
            v-if="sportIcons[entry.pick.sport]"
            :icon="sportIcons[entry.pick.sport]"
            class="w-3.5 h-3.5 flex-shrink-0"
            :class="sportIconColors[entry.pick.sport] || 'text-ds-text-tertiary'"
          />
          <p class="text-xs text-ds-text-primary flex-1 truncate">{{ entry.pick.description }}</p>
          <span class="text-xs font-mono text-ds-text-secondary">{{ formatOdds(entry.pick.drafted_odds) }}</span>
        </template>
        <p v-else class="text-xs text-ds-text-tertiary italic flex-1">Empty</p>
      </div>

      <!-- Bench -->
      <div class="pt-1 border-t border-ds-border mt-1">
        <div
          v-for="slot in draft.benchSlots"
          :key="`b${slot}`"
          class="flex items-center gap-2 p-2 rounded-ds-sm"
          :class="getBench(slot) ? 'bg-ds-bg-hover' : 'bg-ds-bg-primary'"
        >
          <span class="text-[10px] font-bold text-ds-text-tertiary w-6">B{{ slot }}</span>
          <template v-if="getBench(slot)">
            <span
              class="text-[10px] font-bold px-1.5 py-0.5 rounded"
              :class="typeBadgeClasses[getBench(slot).slot_type] || 'bg-ds-bg-hover text-ds-text-tertiary'"
            >
              {{ typeLabels[getBench(slot).slot_type] || getBench(slot).slot_type }}
            </span>
            <Icon
              v-if="sportIcons[getBench(slot).sport]"
              :icon="sportIcons[getBench(slot).sport]"
              class="w-3.5 h-3.5 flex-shrink-0"
              :class="sportIconColors[getBench(slot).sport] || 'text-ds-text-tertiary'"
            />
            <p class="text-xs text-ds-text-primary flex-1 truncate">{{ getBench(slot).description }}</p>
            <span class="text-xs font-mono text-ds-text-secondary">{{ formatOdds(getBench(slot).drafted_odds) }}</span>
          </template>
          <p v-else class="text-xs text-ds-text-tertiary italic flex-1">Empty</p>
        </div>
      </div>
  </div>
</template>

