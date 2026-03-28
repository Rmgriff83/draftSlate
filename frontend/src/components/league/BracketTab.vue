<script setup>
import { ref, computed, onMounted, watch } from 'vue'
import { useLeagueStore } from '@/stores/league'
import { useSlateStore } from '@/stores/slate'

const leagueStore = useLeagueStore()
const slate = useSlateStore()

const league = computed(() => leagueStore.currentLeague)
const bracket = computed(() => slate.bracketData)
const payouts = computed(() => slate.payoutData)

const selectedRound = ref(null)
const loading = ref(false)

const roundLabels = {
  wild_card: 'Wild Card',
  semifinal: 'Semifinals',
  winners_semifinal: 'Semifinals',
  championship: 'Championship',
  winners_championship: 'Championship',
  third_place: '3rd Place',
  winners_consolation: '3rd Place',
  consolation_semi: 'Consolation',
  fifth_place: '5th Place',
  losers_round1: 'Losers R1',
  losers_round2: 'Losers R2',
}

const weeks = computed(() => {
  if (!bracket.value?.rounds) return []
  return Object.keys(bracket.value.rounds)
    .map(Number)
    .sort((a, b) => a - b)
})

const roundsForSelectedWeek = computed(() => {
  if (!bracket.value?.rounds || selectedRound.value === null) return {}
  return bracket.value.rounds[selectedRound.value] || {}
})

const roundKeys = computed(() => {
  if (!bracket.value?.rounds) return []
  const allRounds = []
  for (const week of weeks.value) {
    const weekRounds = bracket.value.rounds[week]
    for (const round of Object.keys(weekRounds)) {
      if (!allRounds.find(r => r.week === week && r.round === round)) {
        allRounds.push({ week, round, label: roundLabels[round] || round })
      }
    }
  }
  return allRounds
})

// Group round keys by week for display
const weekRoundMap = computed(() => {
  const map = {}
  for (const entry of roundKeys.value) {
    if (!map[entry.week]) map[entry.week] = []
    map[entry.week].push(entry)
  }
  return map
})

const isWinnersBracket = (round) =>
  ['winners_semifinal', 'winners_championship', 'winners_consolation', 'semifinal', 'championship', 'third_place', 'wild_card', 'fifth_place', 'consolation_semi'].includes(round)

const isLosersBracket = (round) =>
  ['losers_round1', 'losers_round2'].includes(round)

const winnersMatchups = computed(() => {
  const result = []
  for (const [round, matchups] of Object.entries(roundsForSelectedWeek.value)) {
    if (isWinnersBracket(round)) {
      result.push({ round, label: roundLabels[round] || round, matchups })
    }
  }
  return result
})

const losersMatchups = computed(() => {
  const result = []
  for (const [round, matchups] of Object.entries(roundsForSelectedWeek.value)) {
    if (isLosersBracket(round)) {
      result.push({ round, label: roundLabels[round] || round, matchups })
    }
  }
  return result
})

const hasLosers = computed(() => league.value?.playoff_format === 'D')

const finalStandings = computed(() => bracket.value?.final_standings || [])
const isCompleted = computed(() => league.value?.state === 'completed')

function medalIcon(pos) {
  if (pos === 1) return { icon: '1st', cls: 'text-yellow-400 bg-yellow-400/10' }
  if (pos === 2) return { icon: '2nd', cls: 'text-gray-300 bg-gray-300/10' }
  if (pos === 3) return { icon: '3rd', cls: 'text-amber-600 bg-amber-600/10' }
  return null
}

function getPayoutForPosition(position) {
  const list = payouts.value?.payouts || []
  const p = list.find(p => p.position === position)
  return p ? p.amount.toFixed(2) : null
}

onMounted(async () => {
  if (league.value) {
    loading.value = true
    await Promise.all([
      slate.fetchBracket(league.value.id),
      slate.fetchPayouts(league.value.id),
    ])
    loading.value = false
    if (weeks.value.length > 0) {
      selectedRound.value = weeks.value[weeks.value.length - 1]
    }
  }
})

watch(() => league.value?.id, async (id) => {
  if (id) {
    await Promise.all([
      slate.fetchBracket(id),
      slate.fetchPayouts(id),
    ])
    if (weeks.value.length > 0) {
      selectedRound.value = weeks.value[weeks.value.length - 1]
    }
  }
})
</script>

<template>
  <div class="space-y-4">
    <!-- Loading -->
    <div v-if="loading" class="ds-card p-6 text-center">
      <div class="w-5 h-5 border-2 border-ds-primary border-t-transparent rounded-full animate-spin mx-auto"></div>
    </div>

    <template v-else-if="bracket">
      <!-- Week/round selector pills -->
      <div class="flex gap-2 overflow-x-auto pb-1 -mx-1 px-1">
        <button
          v-for="week in weeks"
          :key="week"
          @click="selectedRound = week"
          :class="[
            'px-3 py-1.5 text-xs font-medium rounded-full whitespace-nowrap transition-colors flex-shrink-0',
            selectedRound === week
              ? 'bg-ds-primary text-white'
              : 'bg-ds-bg-hover text-ds-text-secondary hover:text-ds-text-primary',
          ]"
        >
          Week {{ week }}
        </button>
      </div>

      <!-- Winners bracket matchups -->
      <template v-if="winnersMatchups.length > 0">
        <div v-if="hasLosers" class="text-xs font-semibold text-ds-text-tertiary uppercase tracking-wide">
          Winners Bracket
        </div>
        <div v-for="group in winnersMatchups" :key="group.round" class="space-y-2">
          <h4 class="text-xs font-medium text-ds-text-tertiary">{{ group.label }}</h4>
          <div v-for="matchup in group.matchups" :key="matchup.id" class="ds-card p-3">
            <!-- Bye -->
            <div v-if="matchup.is_bye" class="flex items-center gap-2">
              <span class="text-[10px] font-bold text-ds-primary bg-ds-primary/10 px-1.5 py-0.5 rounded">#{{ matchup.home_team.playoff_seed }}</span>
              <span class="text-sm text-ds-text-primary font-medium">{{ matchup.home_team.team_name }}</span>
              <span class="text-xs text-ds-text-tertiary ml-auto">BYE</span>
            </div>
            <!-- Regular matchup -->
            <template v-else>
              <div class="flex items-center gap-2 mb-1.5">
                <span class="text-[10px] font-bold text-ds-primary bg-ds-primary/10 px-1.5 py-0.5 rounded">#{{ matchup.home_team.playoff_seed }}</span>
                <span
                  :class="[
                    'text-sm font-medium flex-1 truncate',
                    matchup.winner_id === matchup.home_team.id ? 'text-ds-green' : 'text-ds-text-primary',
                  ]"
                >
                  {{ matchup.home_team.team_name }}
                </span>
                <span class="text-sm font-bold tabular-nums" :class="matchup.winner_id === matchup.home_team.id ? 'text-ds-green' : 'text-ds-text-secondary'">
                  {{ matchup.home_score ?? '—' }}
                </span>
              </div>
              <div class="flex items-center gap-2">
                <span class="text-[10px] font-bold text-ds-primary bg-ds-primary/10 px-1.5 py-0.5 rounded">#{{ matchup.away_team.playoff_seed }}</span>
                <span
                  :class="[
                    'text-sm font-medium flex-1 truncate',
                    matchup.winner_id === matchup.away_team.id ? 'text-ds-green' : 'text-ds-text-primary',
                  ]"
                >
                  {{ matchup.away_team.team_name }}
                </span>
                <span class="text-sm font-bold tabular-nums" :class="matchup.winner_id === matchup.away_team.id ? 'text-ds-green' : 'text-ds-text-secondary'">
                  {{ matchup.away_score ?? '—' }}
                </span>
              </div>
              <div v-if="matchup.status !== 'completed'" class="mt-1">
                <span class="text-[10px] text-ds-text-tertiary">In progress</span>
              </div>
            </template>
          </div>
        </div>
      </template>

      <!-- Losers bracket matchups (Format D only) -->
      <template v-if="losersMatchups.length > 0">
        <div class="text-xs font-semibold text-ds-text-tertiary uppercase tracking-wide mt-3">
          Losers Bracket
        </div>
        <div v-for="group in losersMatchups" :key="group.round" class="space-y-2">
          <h4 class="text-xs font-medium text-ds-text-tertiary">{{ group.label }}</h4>
          <div v-for="matchup in group.matchups" :key="matchup.id" class="ds-card p-3">
            <div v-if="matchup.is_bye" class="flex items-center gap-2">
              <span class="text-[10px] font-bold text-ds-primary bg-ds-primary/10 px-1.5 py-0.5 rounded">#{{ matchup.home_team.playoff_seed }}</span>
              <span class="text-sm text-ds-text-primary font-medium">{{ matchup.home_team.team_name }}</span>
              <span class="text-xs text-ds-text-tertiary ml-auto">BYE</span>
            </div>
            <template v-else>
              <div class="flex items-center gap-2 mb-1.5">
                <span class="text-[10px] font-bold text-ds-text-tertiary bg-ds-bg-hover px-1.5 py-0.5 rounded">#{{ matchup.home_team.playoff_seed }}</span>
                <span
                  :class="[
                    'text-sm font-medium flex-1 truncate',
                    matchup.winner_id === matchup.home_team.id ? 'text-ds-green' : 'text-ds-text-primary',
                  ]"
                >
                  {{ matchup.home_team.team_name }}
                </span>
                <span class="text-sm font-bold tabular-nums" :class="matchup.winner_id === matchup.home_team.id ? 'text-ds-green' : 'text-ds-text-secondary'">
                  {{ matchup.home_score ?? '—' }}
                </span>
              </div>
              <div class="flex items-center gap-2">
                <span class="text-[10px] font-bold text-ds-text-tertiary bg-ds-bg-hover px-1.5 py-0.5 rounded">#{{ matchup.away_team.playoff_seed }}</span>
                <span
                  :class="[
                    'text-sm font-medium flex-1 truncate',
                    matchup.winner_id === matchup.away_team.id ? 'text-ds-green' : 'text-ds-text-primary',
                  ]"
                >
                  {{ matchup.away_team.team_name }}
                </span>
                <span class="text-sm font-bold tabular-nums" :class="matchup.winner_id === matchup.away_team.id ? 'text-ds-green' : 'text-ds-text-secondary'">
                  {{ matchup.away_score ?? '—' }}
                </span>
              </div>
            </template>
          </div>
        </div>
      </template>

      <!-- Final Standings (completed) -->
      <div v-if="isCompleted && finalStandings.length > 0" class="ds-card p-4">
        <h3 class="text-xs font-semibold text-ds-text-tertiary uppercase tracking-wide mb-3">Final Standings</h3>
        <div class="divide-y divide-ds-border -mx-4">
          <div
            v-for="entry in finalStandings"
            :key="entry.membership_id"
            class="flex items-center gap-3 px-4 py-2"
          >
            <span class="text-xs font-bold w-5 text-center flex-shrink-0" :class="entry.final_position <= 3 ? 'text-ds-primary' : 'text-ds-text-tertiary'">
              {{ entry.final_position }}
            </span>
            <span
              v-if="medalIcon(entry.final_position)"
              :class="['text-[10px] font-bold px-1.5 py-0.5 rounded flex-shrink-0', medalIcon(entry.final_position).cls]"
            >
              {{ medalIcon(entry.final_position).icon }}
            </span>
            <p class="flex-1 text-sm text-ds-text-primary truncate">{{ entry.team_name }}</p>
            <span class="text-xs text-ds-text-tertiary">{{ entry.user_name }}</span>
            <!-- Payout amount if available -->
            <span
              v-if="payouts && getPayoutForPosition(entry.final_position)"
              class="text-xs font-bold text-ds-green flex-shrink-0"
            >
              ${{ getPayoutForPosition(entry.final_position) }}
            </span>
          </div>
        </div>
      </div>
    </template>

    <!-- No bracket data -->
    <div v-else class="ds-card p-6 text-center">
      <p class="text-sm text-ds-text-secondary">No playoff bracket data yet.</p>
    </div>
  </div>
</template>

