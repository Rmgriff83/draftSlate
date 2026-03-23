<script setup>
import { ref, computed } from 'vue'
import { useRouter } from 'vue-router'
import { useLeagueStore } from '@/stores/league'
import { useLeagueRules } from '@/composables/useLeagueRules'
import StepBasics from '@/components/leagues/create/StepBasics.vue'
import StepBuyIn from '@/components/leagues/create/StepBuyIn.vue'
import StepRosterOdds from '@/components/leagues/create/StepRosterOdds.vue'
import StepDraft from '@/components/leagues/create/StepDraft.vue'
import StepSeason from '@/components/leagues/create/StepSeason.vue'
import StepReview from '@/components/leagues/create/StepReview.vue'

const router = useRouter()
const leagueStore = useLeagueStore()
const { canCreateLeague } = useLeagueRules()

const currentStep = ref(1)
const totalSteps = 6
const submitting = ref(false)
const submitError = ref('')

const form = ref({
  name: '',
  type: 'public',
  max_teams: 8,
  buy_in: 10,
  payout_structure: { first: 100 },
  roster_config: { moneyline: 1, spread: 1, total: 1, player_prop: 2 },
  aggregate_odds_floor: -250,
  bench_slots: 2,
  sports: ['basketball_nba'],
  matchup_duration_days: 7,
  draft_time: '20:00:00',
  draft_timezone: 'America/New_York',
  pick_timer_seconds: 60,
  total_matchups: 14,
  min_hours_before_game: 1,
  playoff_format: 'B',
  team_name: '',
})

const stepValid = ref({
  1: false, 2: false, 3: false, 4: false, 5: false, 6: true,
})

const canNext = computed(() => stepValid.value[currentStep.value])

function next() {
  if (currentStep.value < totalSteps && canNext.value) {
    currentStep.value++
  }
}

function prev() {
  if (currentStep.value > 1) {
    currentStep.value--
  }
}

function goToStep(step) {
  if (step < currentStep.value) {
    currentStep.value = step
  }
}

async function submit() {
  if (!canCreateLeague.value) {
    submitError.value = 'You have reached your maximum number of leagues.'
    return
  }

  submitting.value = true
  submitError.value = ''

  const result = await leagueStore.createLeague(form.value)
  submitting.value = false

  if (result.success) {
    router.push(`/app/leagues/${result.data.id}`)
  } else {
    submitError.value = result.message
  }
}

const stepLabels = ['Basics', 'Buy-in', 'Roster', 'Draft', 'Season', 'Review']
</script>

<template>
  <div class="space-y-6">
    <div class="flex items-center gap-3">
      <button @click="router.push('/app/leagues')" class="text-ds-text-tertiary hover:text-ds-text-primary transition-colors">
        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
          <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5L8.25 12l7.5-7.5" />
        </svg>
      </button>
      <h1 class="text-xl font-bold text-ds-text-primary">Create League</h1>
    </div>

    <!-- Step progress -->
    <div class="flex items-center gap-1">
      <template v-for="step in totalSteps" :key="step">
        <button
          @click="goToStep(step)"
          class="flex items-center justify-center w-8 h-8 rounded-full text-xs font-semibold transition-colors duration-ds-fast"
          :class="step === currentStep
            ? 'bg-ds-primary text-white'
            : step < currentStep
              ? 'bg-ds-primary/20 text-ds-primary cursor-pointer'
              : 'bg-ds-bg-hover text-ds-text-tertiary cursor-default'"
        >
          {{ step }}
        </button>
        <div
          v-if="step < totalSteps"
          class="flex-1 h-0.5 rounded"
          :class="step < currentStep ? 'bg-ds-primary/40' : 'bg-ds-border'"
        ></div>
      </template>
    </div>
    <p class="text-xs text-ds-text-tertiary text-center">Step {{ currentStep }}: {{ stepLabels[currentStep - 1] }}</p>

    <!-- Steps -->
    <KeepAlive>
      <StepBasics v-if="currentStep === 1" v-model="form" @valid="stepValid[1] = $event" />
      <StepBuyIn v-else-if="currentStep === 2" v-model="form" @valid="stepValid[2] = $event" />
      <StepRosterOdds v-else-if="currentStep === 3" v-model="form" @valid="stepValid[3] = $event" />
      <StepDraft v-else-if="currentStep === 4" v-model="form" @valid="stepValid[4] = $event" />
      <StepSeason v-else-if="currentStep === 5" v-model="form" @valid="stepValid[5] = $event" />
      <StepReview v-else-if="currentStep === 6" :form="form" @edit="goToStep" />
    </KeepAlive>

    <!-- Error -->
    <p v-if="submitError" class="text-sm text-ds-red text-center">{{ submitError }}</p>

    <!-- Navigation -->
    <div class="flex gap-3">
      <button
        v-if="currentStep > 1"
        @click="prev"
        class="flex-1 px-4 py-2.5 text-sm font-medium text-ds-text-secondary bg-ds-bg-hover rounded-ds-sm hover:bg-ds-border transition-colors duration-ds-fast"
      >
        Back
      </button>
      <button
        v-if="currentStep < totalSteps"
        @click="next"
        :disabled="!canNext"
        class="flex-1 px-4 py-2.5 text-sm font-semibold text-white bg-ds-primary hover:bg-ds-primary-light rounded-ds-sm transition-colors duration-ds-fast disabled:opacity-50"
      >
        Next
      </button>
      <button
        v-if="currentStep === totalSteps"
        @click="submit"
        :disabled="submitting"
        class="flex-1 px-4 py-2.5 text-sm font-semibold text-white bg-ds-primary hover:bg-ds-primary-light rounded-ds-sm transition-colors duration-ds-fast disabled:opacity-50"
      >
        {{ submitting ? 'Creating...' : 'Create League' }}
      </button>
    </div>
  </div>
</template>
