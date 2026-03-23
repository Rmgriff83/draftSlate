<script setup>
import { computed } from 'vue'

const props = defineProps({
  form: { type: Object, required: true },
})

const emit = defineEmits(['edit'])

const tzLabels = {
  'America/New_York': 'ET',
  'America/Chicago': 'CT',
  'America/Denver': 'MT',
  'America/Los_Angeles': 'PT',
}

const playoffLabels = {
  A: 'Single Elimination',
  B: 'Double Elimination',
  C: 'Round Robin Finals',
  D: 'No Playoffs',
}

const sportLabels = {
  basketball_nba: 'NBA',
  americanfootball_nfl: 'NFL',
  baseball_mlb: 'MLB',
  icehockey_nhl: 'NHL',
}

const typeLabels = {
  moneyline: 'ML',
  spread: 'Spread',
  total: 'O/U',
  player_prop: 'Props',
}

const totalStarters = computed(() =>
  Object.values(props.form.roster_config || {}).reduce((sum, v) => sum + (v || 0), 0)
)

const rosterBreakdown = computed(() => {
  const config = props.form.roster_config || {}
  return Object.entries(config)
    .filter(([, count]) => count > 0)
    .map(([type, count]) => `${count} ${typeLabels[type] || type}`)
    .join(', ')
})

const sportsDisplay = computed(() =>
  (props.form.sports || []).map((s) => sportLabels[s] || s).join(', ')
)

function payoutLabel(ps) {
  if (ps?.first === 100 && !ps?.second) return 'Winner Take All'
  if (ps?.first === 60 && ps?.second === 25) return 'Top 3 (60/25/15)'
  if (ps?.first === 50 && ps?.weekly === 20) return 'Top 3 + Weekly'
  return 'Custom'
}
</script>

<template>
  <div class="space-y-3">
    <h2 class="text-sm font-semibold text-ds-text-secondary uppercase tracking-wide">Review Settings</h2>

    <div class="ds-card divide-y divide-ds-border">
      <!-- Basics -->
      <div class="p-3 flex items-start justify-between">
        <div>
          <p class="text-xs text-ds-text-tertiary">League Name</p>
          <p class="text-sm font-medium text-ds-text-primary">{{ form.name }}</p>
          <p class="text-xs text-ds-text-tertiary mt-0.5">{{ form.type === 'public' ? 'Public' : 'Private' }} &middot; {{ form.max_teams }} teams &middot; {{ sportsDisplay }}</p>
        </div>
        <button @click="$emit('edit', 1)" class="text-xs text-ds-primary hover:underline">Edit</button>
      </div>

      <!-- Buy-in -->
      <div class="p-3 flex items-start justify-between">
        <div>
          <p class="text-xs text-ds-text-tertiary">Buy-in & Payout</p>
          <p class="text-sm font-medium text-ds-text-primary">${{ form.buy_in }} &middot; {{ payoutLabel(form.payout_structure) }}</p>
          <p class="text-xs text-ds-text-tertiary mt-0.5">Total pot: ${{ (form.buy_in * form.max_teams).toFixed(2) }}</p>
        </div>
        <button @click="$emit('edit', 2)" class="text-xs text-ds-primary hover:underline">Edit</button>
      </div>

      <!-- Roster -->
      <div class="p-3 flex items-start justify-between">
        <div>
          <p class="text-xs text-ds-text-tertiary">Roster & Odds</p>
          <p class="text-sm font-medium text-ds-text-primary">{{ rosterBreakdown }} = {{ totalStarters }} starters + {{ form.bench_slots }} bench</p>
          <p class="text-xs text-ds-text-tertiary mt-0.5">Aggregate floor: {{ form.aggregate_odds_floor }}</p>
        </div>
        <button @click="$emit('edit', 3)" class="text-xs text-ds-primary hover:underline">Edit</button>
      </div>

      <!-- Draft -->
      <div class="p-3 flex items-start justify-between">
        <div>
          <p class="text-xs text-ds-text-tertiary">Draft Settings</p>
          <p class="text-sm font-medium text-ds-text-primary">Every {{ form.matchup_duration_days }} day{{ form.matchup_duration_days !== 1 ? 's' : '' }} at {{ form.draft_time?.slice(0, 5) }} {{ tzLabels[form.draft_timezone] || form.draft_timezone }}</p>
          <p class="text-xs text-ds-text-tertiary mt-0.5">{{ form.pick_timer_seconds }}s pick timer · {{ form.min_hours_before_game }}h event cutoff</p>
        </div>
        <button @click="$emit('edit', 4)" class="text-xs text-ds-primary hover:underline">Edit</button>
      </div>

      <!-- Season -->
      <div class="p-3 flex items-start justify-between">
        <div>
          <p class="text-xs text-ds-text-tertiary">Season & Playoffs</p>
          <p class="text-sm font-medium text-ds-text-primary">{{ form.total_matchups }} matchups · {{ playoffLabels[form.playoff_format] }}</p>
        </div>
        <button @click="$emit('edit', 5)" class="text-xs text-ds-primary hover:underline">Edit</button>
      </div>

      <!-- Team Name -->
      <div class="p-3">
        <p class="text-xs text-ds-text-tertiary">Your Team</p>
        <p class="text-sm font-medium text-ds-text-primary">{{ form.team_name }}</p>
      </div>
    </div>
  </div>
</template>
