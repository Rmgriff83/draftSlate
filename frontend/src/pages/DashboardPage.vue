<script setup>
import { useAuthStore } from '@/stores/auth'

const auth = useAuthStore()
</script>

<template>
  <div class="space-y-6">
    <div>
      <h1 class="text-xl font-bold text-ds-text-primary">Welcome back, {{ auth.user?.display_name }}</h1>
      <p class="text-sm text-ds-text-secondary mt-1">Here's your DraftSlate overview.</p>
    </div>

    <div class="ds-card p-6 text-center">
      <p class="text-ds-text-secondary">No leagues yet. Join or create one to get started!</p>
      <button class="mt-4 px-6 py-2 bg-ds-primary hover:bg-ds-primary-light text-white font-semibold rounded-ds-sm transition-colors duration-ds-fast ease-ds-out">
        Browse Leagues
      </button>
    </div>
  </div>
</template>
