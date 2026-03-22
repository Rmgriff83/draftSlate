<script setup>
import { onMounted, computed } from 'vue'
import { useAuthStore } from '@/stores/auth'
import { useLeagueStore } from '@/stores/league'
import AlertBanner from '@/components/dashboard/AlertBanner.vue'
import LeagueCardRow from '@/components/dashboard/LeagueCardRow.vue'
import ActionCTAs from '@/components/dashboard/ActionCTAs.vue'
import QuickStats from '@/components/dashboard/QuickStats.vue'

const auth = useAuthStore()
const leagueStore = useLeagueStore()

const hasLeagues = computed(() =>
  leagueStore.myLeagues.filter((l) => l.state !== 'cancelled').length > 0
)

onMounted(() => {
  leagueStore.fetchMyLeagues()
})
</script>

<template>
  <div class="space-y-6">
    <div>
      <h1 class="text-xl font-bold text-ds-text-primary">Welcome back, {{ auth.user?.display_name }}</h1>
      <p class="text-sm text-ds-text-secondary mt-1">Here's your DraftSlate overview.</p>
    </div>

    <!-- Full dashboard when user has leagues -->
    <template v-if="hasLeagues">
      <AlertBanner :leagues="leagueStore.myLeagues" />
      <div>
        <h2 class="text-sm font-semibold text-ds-text-secondary uppercase tracking-wide mb-3">Your Leagues</h2>
        <LeagueCardRow :leagues="leagueStore.myLeagues.filter((l) => l.state !== 'cancelled')" />
      </div>
      <QuickStats :leagues="leagueStore.myLeagues" />
      <ActionCTAs />
    </template>

    <!-- Empty state -->
    <template v-else>
      <div class="ds-card p-6 text-center">
        <svg class="w-12 h-12 text-ds-text-tertiary mx-auto mb-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1">
          <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 18.75h-9m9 0a3 3 0 013 3h-15a3 3 0 013-3m9 0v-4.5A3.75 3.75 0 0012 10.5a3.75 3.75 0 00-3.75 3.75v4.5m7.5-10.5a3 3 0 11-6 0 3 3 0 016 0z" />
        </svg>
        <p class="text-ds-text-secondary mb-1">No leagues yet.</p>
        <p class="text-sm text-ds-text-tertiary mb-4">Join or create one to get started!</p>
        <ActionCTAs />
      </div>
    </template>
  </div>
</template>
