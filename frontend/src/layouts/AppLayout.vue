<script setup>
import { computed } from 'vue'
import { useRoute } from 'vue-router'
import AppTopBar from '@/components/common/AppTopBar.vue'
import AppBottomNav from '@/components/common/AppBottomNav.vue'

const route = useRoute()
const isDraftRoom = computed(() => /\/draft$/.test(route.path))
</script>

<template>
  <div class="min-h-screen bg-ds-bg-primary">
    <AppTopBar />
    <main :class="isDraftRoom ? 'pb-4 pt-2 px-4' : 'pb-20 pt-2 px-4'">
      <router-view />
    </main>
    <AppBottomNav />
  </div>
</template>
