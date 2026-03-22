<script setup>
import { ref, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useLeagueStore } from '@/stores/league'

const route = useRoute()
const router = useRouter()
const leagueStore = useLeagueStore()

const league = ref(null)
const teamName = ref('')
const error = ref('')
const loading = ref(true)
const joining = ref(false)
const alreadyMember = ref(false)

onMounted(async () => {
  const result = await leagueStore.joinByInviteCode(route.params.code)
  loading.value = false

  if (result.success) {
    league.value = result.data
    alreadyMember.value = result.data.is_member || false
  } else {
    error.value = result.message
  }
})

async function handleJoin() {
  if (!teamName.value.trim()) {
    error.value = 'Team name is required.'
    return
  }

  joining.value = true
  error.value = ''

  const result = await leagueStore.joinLeague(league.value.id, teamName.value.trim())
  joining.value = false

  if (result.success) {
    router.push(`/app/leagues/${league.value.id}`)
  } else {
    error.value = result.message
  }
}
</script>

<template>
  <div class="space-y-6">
    <h1 class="text-xl font-bold text-ds-text-primary">League Invite</h1>

    <!-- Loading -->
    <div v-if="loading" class="ds-card p-6 text-center">
      <p class="text-sm text-ds-text-tertiary">Loading invite...</p>
    </div>

    <!-- Error (invalid code) -->
    <div v-else-if="!league" class="ds-card p-6 text-center">
      <p class="text-sm text-ds-red mb-2">{{ error || 'Invalid invite link.' }}</p>
      <button
        @click="router.push('/app/leagues')"
        class="px-4 py-2 text-sm font-medium text-ds-primary hover:underline"
      >
        Go to My Leagues
      </button>
    </div>

    <!-- Already a member -->
    <div v-else-if="alreadyMember" class="ds-card p-6 text-center">
      <p class="text-sm text-ds-text-primary mb-1">You're already in {{ league.name }}!</p>
      <router-link
        :to="`/app/leagues/${league.id}`"
        class="text-sm text-ds-primary hover:underline"
      >
        Go to league
      </router-link>
    </div>

    <!-- Join form -->
    <template v-else>
      <div class="ds-card p-5">
        <h2 class="text-lg font-bold text-ds-text-primary mb-1">{{ league.name }}</h2>
        <p class="text-sm text-ds-text-secondary mb-3">
          Created by {{ league.commissioner?.display_name }} &middot;
          {{ league.member_count }}/{{ league.max_teams }} teams &middot;
          ${{ league.buy_in }} buy-in
        </p>

        <div class="mb-4">
          <label class="block text-sm font-medium text-ds-text-primary mb-1">Your Team Name</label>
          <input
            v-model="teamName"
            type="text"
            maxlength="50"
            placeholder="Enter a team name"
            class="w-full px-3 py-2 bg-ds-bg-primary border border-ds-border rounded-ds-sm text-ds-text-primary placeholder-ds-text-tertiary focus:outline-none focus:ring-2 focus:ring-ds-primary/50"
            @keyup.enter="handleJoin"
          />
        </div>

        <p v-if="error" class="text-xs text-ds-red mb-3">{{ error }}</p>

        <button
          @click="handleJoin"
          :disabled="joining"
          class="w-full py-2.5 text-sm font-semibold text-white bg-ds-primary hover:bg-ds-primary-light rounded-ds-sm transition-colors duration-ds-fast disabled:opacity-50"
        >
          {{ joining ? 'Joining...' : `Join League ($${league.buy_in})` }}
        </button>
      </div>
    </template>
  </div>
</template>
