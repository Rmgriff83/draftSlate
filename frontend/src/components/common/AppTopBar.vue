<script setup>
import { ref, computed } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'

const auth = useAuthStore()
const route = useRoute()
const router = useRouter()
const dropdownOpen = ref(false)

const isDraftRoom = computed(() => /\/draft$/.test(route.path))
const leagueId = computed(() => route.params.id)

function toggleDropdown() {
  dropdownOpen.value = !dropdownOpen.value
}

function closeDropdown() {
  dropdownOpen.value = false
}

async function handleLogout() {
  closeDropdown()
  await auth.logout()
  router.push('/login')
}
</script>

<template>
  <header class="sticky top-0 z-30 bg-ds-bg-secondary border-b border-ds-border relative">
    <div class="flex items-center justify-between h-14 px-4">
      <!-- Left slot -->
      <div class="w-20">
        <button
          v-if="isDraftRoom"
          @click="router.push(`/app/leagues/${leagueId}`)"
          class="flex items-center gap-1 text-amber-500 hover:text-amber-400 transition-colors"
        >
          <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5L8.25 12l7.5-7.5" />
          </svg>
          <span class="text-xs font-semibold">Exit</span>
        </button>
        <router-link v-else to="/app/dashboard" class="text-lg font-bold text-ds-primary">
          DraftSlate
        </router-link>
      </div>

      <!-- Center logo (draft mode) -->
      <router-link
        v-if="isDraftRoom"
        to="/app/dashboard"
        class="text-lg font-bold text-ds-primary absolute left-1/2 -translate-x-1/2"
      >
        DraftSlate
      </router-link>

      <div class="relative">
        <button
          @click="toggleDropdown"
          class="flex items-center gap-2 p-1 rounded-full hover:bg-ds-bg-hover transition-colors duration-ds-fast"
        >
          <div class="w-8 h-8 rounded-full bg-ds-primary flex items-center justify-center text-sm font-semibold text-white">
            {{ auth.user?.display_name?.charAt(0)?.toUpperCase() || '?' }}
          </div>
        </button>

        <Transition
          enter-active-class="transition ease-ds-out duration-ds-fast"
          enter-from-class="opacity-0 scale-95"
          enter-to-class="opacity-100 scale-100"
          leave-active-class="transition ease-ds-out duration-ds-fast"
          leave-from-class="opacity-100 scale-100"
          leave-to-class="opacity-0 scale-95"
        >
          <div
            v-if="dropdownOpen"
            class="absolute right-0 mt-2 w-48 bg-ds-bg-tertiary border border-ds-border rounded-ds shadow-ds-lg py-1 z-50"
          >
            <div class="px-3 py-2 border-b border-ds-border">
              <p class="text-sm font-medium text-ds-text-primary truncate">{{ auth.user?.display_name }}</p>
              <p class="text-xs text-ds-text-tertiary truncate">{{ auth.user?.email }}</p>
            </div>
            <button
              @click="handleLogout"
              class="w-full text-left px-3 py-2 text-sm text-ds-red hover:bg-ds-bg-hover transition-colors"
            >
              Sign out
            </button>
          </div>
        </Transition>

        <div v-if="dropdownOpen" class="fixed inset-0 z-40" @click="closeDropdown"></div>
      </div>
    </div>
  </header>
</template>
