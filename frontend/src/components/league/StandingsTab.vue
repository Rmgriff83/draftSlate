<script setup>
import { computed } from 'vue'
import { useLeagueStore } from '@/stores/league'
import { useSlateStore } from '@/stores/slate'

const emit = defineEmits(['open-user-detail'])
const leagueStore = useLeagueStore()
const slate = useSlateStore()

const league = computed(() => leagueStore.currentLeague)
const isCompleted = computed(() => league.value?.state === 'completed')
const isRegularSeason = computed(() => league.value?.state === 'active')

const sortedStandings = computed(() =>
  [...slate.standings].sort((a, b) => (b.win_percentage ?? 0) - (a.win_percentage ?? 0))
)

const playoffCutoff = computed(() =>
  Math.ceil(slate.standings.length / 2)
)

const totalMatchups = computed(() => league.value?.total_matchups ?? 14)

// Clinch calculations (regular season only)
const clinchStatus = computed(() => {
  if (!isRegularSeason.value || sortedStandings.value.length === 0) return {}

  const total = totalMatchups.value
  const teams = sortedStandings.value.map(m => {
    const gamesPlayed = (m.wins ?? 0) + (m.losses ?? 0) + (m.ties ?? 0)
    const remaining = Math.max(0, total - gamesPlayed)
    const winPoints = (m.wins ?? 0) + (m.ties ?? 0) * 0.5
    return {
      id: m.membership_id,
      worstPct: total > 0 ? winPoints / total : 0,
      bestPct: total > 0 ? (winPoints + remaining) / total : 0,
    }
  })

  const cutoff = playoffCutoff.value
  const result = {}

  for (const team of teams) {
    const clinched1 = teams.every(t =>
      t.id === team.id || team.worstPct > t.bestPct
    )

    const couldPass = teams.filter(t =>
      t.id !== team.id && t.bestPct > team.worstPct
    ).length
    const clinchedPlayoff = couldPass < cutoff

    if (clinched1) {
      result[team.id] = 'seed1'
    } else if (clinchedPlayoff) {
      result[team.id] = 'playoff'
    }
  }

  return result
})

const hasClinches = computed(() => Object.keys(clinchStatus.value).length > 0)

const playoffStartWeek = computed(() => totalMatchups.value + 1)

function medalIcon(pos) {
  if (pos === 1) return { icon: '1st', cls: 'text-yellow-400 bg-yellow-400/10' }
  if (pos === 2) return { icon: '2nd', cls: 'text-gray-300 bg-gray-300/10' }
  if (pos === 3) return { icon: '3rd', cls: 'text-amber-600 bg-amber-600/10' }
  return null
}
</script>

<template>
  <div class="space-y-2">
    <!-- Empty state -->
    <div v-if="sortedStandings.length === 0" class="ds-card p-6 text-center">
      <p class="text-gray-400">No standings data yet. Complete some matchups first.</p>
    </div>

    <!-- Standings list -->
    <div v-else class="ds-card divide-y divide-gray-700/50">
      <div
        v-for="(member, idx) in sortedStandings"
        :key="member.membership_id"
        @click="emit('open-user-detail', member)"
        :class="[
          'flex items-center gap-3 px-4 py-3 transition-colors cursor-pointer active:bg-ds-bg-hover',
          member.is_current_user ? 'bg-ds-primary/10 border-l-2 border-ds-primary' : '',
        ]"
      >
        <!-- Rank / Final Position -->
        <div class="flex-shrink-0 w-7 text-center">
          <span
            :class="[
              'text-sm font-bold',
              (isCompleted ? member.final_position : idx + 1) <= 3 ? 'text-ds-primary' : 'text-gray-400',
            ]"
          >
            {{ isCompleted && member.final_position ? member.final_position : idx + 1 }}
          </span>
        </div>

        <!-- Team avatar initial -->
        <div
          :class="[
            'w-8 h-8 rounded-full flex items-center justify-center text-sm font-bold flex-shrink-0',
            member.is_current_user ? 'bg-ds-primary text-white' : 'bg-ds-bg-hover text-ds-text-secondary',
          ]"
        >
          {{ (member.team_name || '?')[0].toUpperCase() }}
        </div>

        <!-- Medal icon (completed state, positions 1-3) -->
        <span
          v-if="isCompleted && member.final_position && medalIcon(member.final_position)"
          :class="['text-[10px] font-bold px-1.5 py-0.5 rounded flex-shrink-0', medalIcon(member.final_position).cls]"
        >
          {{ medalIcon(member.final_position).icon }}
        </span>

        <!-- Team info -->
        <div class="flex-1 min-w-0">
          <p
            :class="[
              'text-sm font-medium truncate',
              member.is_current_user ? 'text-ds-text-primary' : 'text-ds-text-primary',
            ]"
          >
            {{ member.team_name }}
          </p>
          <p class="text-xs text-gray-500">{{ member.user_name }}</p>
        </div>

        <!-- Record -->
        <div class="text-right flex-shrink-0">
          <p class="text-sm font-mono text-ds-text-primary">
            {{ member.wins }}-{{ member.losses }}<span v-if="member.ties > 0">-{{ member.ties }}</span>
          </p>
          <p class="text-[10px] text-gray-500">
            {{ ((member.win_percentage ?? 0) * 100).toFixed(1) }}%
          </p>
        </div>

        <!-- Clinch indicator -->
        <div v-if="clinchStatus[member.membership_id]" class="flex-shrink-0 w-5 text-center">
          <span
            v-if="clinchStatus[member.membership_id] === 'seed1'"
            class="text-xs font-black text-ds-primary"
            title="Clinched #1 seed"
          >z</span>
          <span
            v-else-if="clinchStatus[member.membership_id] === 'playoff'"
            class="text-xs font-black text-ds-green"
            title="Clinched playoff spot"
          >x</span>
        </div>
        <div v-else-if="hasClinches" class="flex-shrink-0 w-5"></div>
      </div>
    </div>

    <!-- Footer: clinch key + playoff info -->
    <div v-if="sortedStandings.length > 0 && !isCompleted" class="px-1 space-y-2">
      <!-- Clinch key -->
      <div v-if="hasClinches" class="flex items-center gap-4">
        <div class="flex items-center gap-1.5">
          <span class="text-xs font-black text-ds-primary">z</span>
          <span class="text-[10px] text-gray-500">Clinched #1 seed</span>
        </div>
        <div class="flex items-center gap-1.5">
          <span class="text-xs font-black text-ds-green">x</span>
          <span class="text-[10px] text-gray-500">Clinched playoffs</span>
        </div>
      </div>

      <!-- Playoff start message -->
      <p v-if="isRegularSeason" class="text-[11px] text-gray-500">
        Playoffs begin Week {{ playoffStartWeek }}
      </p>
    </div>
  </div>
</template>
