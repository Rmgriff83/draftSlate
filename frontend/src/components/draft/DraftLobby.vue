<script setup>
import { computed } from 'vue'
import { useDraftStore } from '@/stores/draft'
import { useAuthStore } from '@/stores/auth'
import { useLeagueStore } from '@/stores/league'

const props = defineProps({
  leagueId: { type: [String, Number], required: true },
})

const draft = useDraftStore()
const auth = useAuthStore()
const leagueStore = useLeagueStore()

const league = computed(() => leagueStore.currentLeague)

const isCommissioner = computed(() =>
  league.value?.is_commissioner || false
)

const startError = computed(() => draft.error)

async function handleStart() {
  await draft.startDraft(props.leagueId)
}
</script>

<template>
  <div class="ds-card p-6 text-center space-y-4">
    <svg class="w-16 h-16 text-ds-primary/40 mx-auto" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1">
      <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z" />
    </svg>

    <div>
      <h2 class="text-lg font-bold text-ds-text-primary">Draft Lobby</h2>
      <p class="text-sm text-ds-text-secondary mt-1">
        Waiting for the commissioner to start the draft.
      </p>
    </div>

    <!-- Members in lobby -->
    <div v-if="draft.draftState?.members" class="space-y-1">
      <p class="text-xs text-ds-text-tertiary uppercase tracking-wide">Players Ready</p>
      <div class="flex flex-wrap justify-center gap-2">
        <span
          v-for="member in draft.draftState.members"
          :key="member.id"
          class="px-2 py-1 text-xs font-medium bg-ds-bg-hover text-ds-text-secondary rounded-full"
        >
          {{ member.team_name }}
        </span>
      </div>
    </div>

    <p v-if="startError" class="text-sm text-ds-red">{{ startError }}</p>

    <button
      v-if="isCommissioner"
      @click="handleStart"
      :disabled="draft.loading"
      class="px-6 py-2.5 text-sm font-semibold text-white bg-ds-primary hover:bg-ds-primary-light rounded-ds-sm transition-colors duration-ds-fast disabled:opacity-50"
    >
      {{ draft.loading ? 'Starting...' : 'Start Draft' }}
    </button>
  </div>
</template>
