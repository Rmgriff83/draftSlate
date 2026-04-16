<script setup>
import { ref, computed } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { useThemeStore } from '@/stores/theme'

const auth = useAuthStore()
const theme = useThemeStore()
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

      <div class="flex items-center gap-2">
        <!-- Theme toggle -->
        <button
          @click="theme.toggle()"
          class="p-1.5 rounded-full hover:bg-ds-bg-hover transition-colors duration-ds-fast text-ds-text-tertiary hover:text-ds-text-primary"
          :title="theme.isDark ? 'Switch to light mode' : 'Switch to dark mode'"
        >
          <!-- Sun icon (shown in dark mode) -->
          <svg v-if="theme.isDark" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 3v2.25m6.364.386l-1.591 1.591M21 12h-2.25m-.386 6.364l-1.591-1.591M12 18.75V21m-4.773-4.227l-1.591 1.591M5.25 12H3m4.227-4.773L5.636 5.636M15.75 12a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0z" />
          </svg>
          <!-- Moon icon (shown in light mode) -->
          <svg v-else class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
            <path stroke-linecap="round" stroke-linejoin="round" d="M21.752 15.002A9.718 9.718 0 0118 15.75c-5.385 0-9.75-4.365-9.75-9.75 0-1.33.266-2.597.748-3.752A9.753 9.753 0 003 11.25C3 16.635 7.365 21 12.75 21a9.753 9.753 0 009.002-5.998z" />
          </svg>
        </button>

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
    </div>
  </header>
</template>
