<script setup>
</script>

<template>
  <div class="min-h-screen flex items-center justify-center bg-ds-bg-primary px-4">
    <div class="w-full max-w-md">
      <div class="text-center mb-8">
        <h1 class="text-3xl font-bold text-ds-primary">DraftSlate</h1>
        <p class="mt-2 text-ds-text-secondary text-sm">Fantasy sports, reimagined.</p>
      </div>
      <div class="bg-ds-bg-secondary border border-ds-border rounded-ds p-6 shadow-ds-lg">
        <slot />
      </div>
    </div>
  </div>
</template>
