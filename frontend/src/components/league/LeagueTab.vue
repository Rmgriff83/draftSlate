<script setup>
import { computed, ref, watch, onMounted } from 'vue'
import { useLeagueStore } from '@/stores/league'
import { useSlateStore } from '@/stores/slate'
import MatchupCard from './MatchupCard.vue'
import StandingsTab from './StandingsTab.vue'
import MatchupDetailModal from './MatchupDetailModal.vue'

const emit = defineEmits(['open-user-detail', 'open-detail'])

const leagueStore = useLeagueStore()
const slate = useSlateStore()

const league = computed(() => leagueStore.currentLeague)
const selectedMatchup = ref(null)

// Load week matchups when the component mounts or week changes
onMounted(() => {
  if (league.value) {
    slate.fetchWeekMatchups(league.value.id, slate.currentWeek)
  }
})

watch(() => slate.currentWeek, (week) => {
  if (league.value) {
    slate.fetchWeekMatchups(league.value.id, week)
  }
})

function openMatchup(matchup) {
  selectedMatchup.value = matchup
}
</script>

<template>
  <div class="space-y-5">
    <!-- Week Matchups section -->
    <div>
      <h3 class="text-xs font-semibold text-ds-text-tertiary uppercase tracking-wide mb-2 px-1">
        Week {{ slate.currentWeek }} Matchups
      </h3>
      <div v-if="slate.weekMatchups.length === 0" class="ds-card p-4 text-center">
        <p class="text-sm text-gray-400">No matchups scheduled this week.</p>
      </div>
      <div v-else class="space-y-2">
        <MatchupCard
          v-for="m in slate.weekMatchups"
          :key="m.id"
          :matchup="m"
          @click="openMatchup"
        />
      </div>
    </div>

    <!-- Standings section -->
    <div>
      <h3 class="text-xs font-semibold text-ds-text-tertiary uppercase tracking-wide mb-2 px-1">
        Standings
      </h3>
      <StandingsTab @open-user-detail="emit('open-user-detail', $event)" />
    </div>

    <!-- Matchup Detail Modal -->
    <MatchupDetailModal
      v-if="selectedMatchup"
      :league-id="league.id"
      :matchup-id="selectedMatchup.id"
      @close="selectedMatchup = null"
      @open-detail="emit('open-detail', $event)"
    />
  </div>
</template>
