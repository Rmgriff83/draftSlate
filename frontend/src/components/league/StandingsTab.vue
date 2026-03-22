<script setup>
import { computed } from 'vue'
import { useSlateStore } from '@/stores/slate'

const slate = useSlateStore()

const playoffCutoff = computed(() => {
  // Show playoff seed on top half of teams
  return Math.ceil(slate.standings.length / 2)
})
</script>

<template>
  <div class="space-y-2">
    <!-- Empty state -->
    <div v-if="slate.standings.length === 0" class="ds-card p-6 text-center">
      <p class="text-gray-400">No standings data yet. Complete some matchups first.</p>
    </div>

    <!-- Standings list -->
    <div v-else class="ds-card divide-y divide-gray-700/50">
      <div
        v-for="member in slate.standings"
        :key="member.membership_id"
        :class="[
          'flex items-center gap-3 px-4 py-3 transition-colors',
          member.is_current_user ? 'bg-ds-primary/10 border-l-2 border-ds-primary' : '',
        ]"
      >
        <!-- Rank -->
        <div class="flex-shrink-0 w-7 text-center">
          <span
            :class="[
              'text-sm font-bold',
              member.rank <= 3 ? 'text-ds-primary' : 'text-gray-400',
            ]"
          >
            {{ member.rank }}
          </span>
        </div>

        <!-- Team avatar initial -->
        <div
          :class="[
            'w-8 h-8 rounded-full flex items-center justify-center text-sm font-bold flex-shrink-0',
            member.is_current_user ? 'bg-ds-primary text-white' : 'bg-ds-bg-hover text-gray-300',
          ]"
        >
          {{ (member.team_name || '?')[0].toUpperCase() }}
        </div>

        <!-- Team info -->
        <div class="flex-1 min-w-0">
          <p
            :class="[
              'text-sm font-medium truncate',
              member.is_current_user ? 'text-white' : 'text-gray-200',
            ]"
          >
            {{ member.team_name }}
          </p>
          <p class="text-xs text-gray-500">{{ member.user_name }}</p>
        </div>

        <!-- Record -->
        <div class="text-right flex-shrink-0">
          <p class="text-sm font-mono text-gray-200">
            {{ member.wins }}-{{ member.losses }}<span v-if="member.ties > 0">-{{ member.ties }}</span>
          </p>
          <p class="text-[10px] text-gray-500">
            {{ member.total_correct_picks }} correct
          </p>
        </div>

        <!-- Playoff seed indicator -->
        <div v-if="member.playoff_seed && member.rank <= playoffCutoff" class="flex-shrink-0">
          <span class="text-[10px] font-bold text-ds-primary bg-ds-primary/10 px-1.5 py-0.5 rounded">
            #{{ member.playoff_seed }}
          </span>
        </div>
      </div>
    </div>
  </div>
</template>
