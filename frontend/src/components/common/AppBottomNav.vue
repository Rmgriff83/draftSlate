<script setup>
import { computed } from 'vue'
import { useRoute } from 'vue-router'

const route = useRoute()

const tabs = [
  { name: 'Dashboard', path: '/app/dashboard', icon: 'dashboard' },
  { name: 'Leagues', path: '/app/leagues', icon: 'leagues' },
  { name: 'Feed', path: '/app/feed', icon: 'feed' },
]

const activeTab = computed(() => {
  return tabs.find((t) => route.path.startsWith(t.path))?.name || ''
})

const isDraftRoom = computed(() => /\/draft$/.test(route.path))
</script>

<template>
  <nav v-if="!isDraftRoom" class="fixed bottom-0 left-0 right-0 z-30 bg-ds-bg-secondary border-t border-ds-border safe-area-bottom">
    <div class="flex items-center justify-around h-16">
      <router-link
        v-for="tab in tabs"
        :key="tab.name"
        :to="tab.path"
        class="flex flex-col items-center justify-center flex-1 h-full transition-colors duration-ds-fast"
        :class="activeTab === tab.name ? 'text-ds-primary' : 'text-ds-text-tertiary hover:text-ds-text-secondary'"
      >
        <!-- Dashboard icon -->
        <svg v-if="tab.icon === 'dashboard'" class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
          <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6A2.25 2.25 0 016 3.75h2.25A2.25 2.25 0 0110.5 6v2.25a2.25 2.25 0 01-2.25 2.25H6a2.25 2.25 0 01-2.25-2.25V6zM3.75 15.75A2.25 2.25 0 016 13.5h2.25a2.25 2.25 0 012.25 2.25V18a2.25 2.25 0 01-2.25 2.25H6A2.25 2.25 0 013.75 18v-2.25zM13.5 6a2.25 2.25 0 012.25-2.25H18A2.25 2.25 0 0120.25 6v2.25A2.25 2.25 0 0118 10.5h-2.25a2.25 2.25 0 01-2.25-2.25V6zM13.5 15.75a2.25 2.25 0 012.25-2.25H18a2.25 2.25 0 012.25 2.25V18A2.25 2.25 0 0118 20.25h-2.25A2.25 2.25 0 0113.5 18v-2.25z" />
        </svg>
        <!-- Leagues icon -->
        <svg v-else-if="tab.icon === 'leagues'" class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
          <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 18.75h-9m9 0a3 3 0 013 3h-15a3 3 0 013-3m9 0v-4.5A3.75 3.75 0 0012 10.5a3.75 3.75 0 00-3.75 3.75v4.5m7.5-10.5a3 3 0 11-6 0 3 3 0 016 0z" />
        </svg>
        <!-- Feed icon -->
        <svg v-else-if="tab.icon === 'feed'" class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
          <path stroke-linecap="round" stroke-linejoin="round" d="M12 7.5h1.5m-1.5 3h1.5m-7.5 3h7.5m-7.5 3h7.5m3-9h3.375c.621 0 1.125.504 1.125 1.125V18a2.25 2.25 0 01-2.25 2.25M16.5 7.5V18a2.25 2.25 0 002.25 2.25M16.5 7.5V4.875c0-.621-.504-1.125-1.125-1.125H4.125C3.504 3.75 3 4.254 3 4.875V18a2.25 2.25 0 002.25 2.25h13.5M6 7.5h3v3H6v-3z" />
        </svg>
        <span class="text-xs mt-1 font-medium">{{ tab.name }}</span>
      </router-link>
    </div>
  </nav>
</template>

<style scoped>
.safe-area-bottom {
  padding-bottom: env(safe-area-inset-bottom, 0);
}
</style>
