<script setup>
import { useRouter } from 'vue-router'

const router = useRouter()
</script>

<template>
  <div class="grid grid-cols-2 gap-3">
    <button
      @click="router.push('/app/leagues/browse')"
      class="ds-card-interactive p-4 text-center"
    >
      <svg class="w-8 h-8 text-ds-primary mx-auto mb-2" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
        <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z" />
      </svg>
      <span class="text-sm font-semibold text-ds-text-primary">Join a League</span>
    </button>
    <button
      @click="router.push('/app/leagues/create')"
      class="ds-card-interactive p-4 text-center"
    >
      <svg class="w-8 h-8 text-ds-primary mx-auto mb-2" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
      </svg>
      <span class="text-sm font-semibold text-ds-text-primary">Create League</span>
    </button>
  </div>
</template>
