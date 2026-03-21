<script setup>
import AppTopBar from '@/components/common/AppTopBar.vue'
import AppBottomNav from '@/components/common/AppBottomNav.vue'
</script>

<template>
  <div class="min-h-screen bg-ds-bg-primary">
    <AppTopBar />
    <main class="pb-20 pt-2 px-4">
      <router-view />
    </main>
    <AppBottomNav />
  </div>
</template>
