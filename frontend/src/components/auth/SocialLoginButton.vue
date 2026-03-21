<script setup>
defineProps({
  provider: {
    type: String,
    default: 'google',
  },
})

function redirectToProvider() {
  window.location.href = '/api/v1/auth/google/redirect'
}
</script>

<template>
  <button
    type="button"
    @click="redirectToProvider"
    class="w-full flex items-center justify-center gap-3 px-4 py-2.5 border border-ds-border rounded-ds-sm bg-ds-bg-tertiary text-ds-text-primary hover:bg-ds-bg-hover transition-colors duration-ds-fast ease-ds-out"
  >
    <svg class="w-5 h-5" viewBox="0 0 24 24">
      <path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92a5.06 5.06 0 01-2.2 3.32v2.76h3.56c2.08-1.92 3.28-4.74 3.28-8.09z"/>
      <path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.56-2.76c-.98.66-2.23 1.06-3.72 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/>
      <path fill="#FBBC05" d="M5.84 14.11a7.12 7.12 0 010-4.22V7.05H2.18A11.96 11.96 0 001 12c0 1.94.46 3.77 1.18 5.05l3.66-2.84z"/>
      <path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.05l3.66 2.84c.87-2.6 3.3-4.51 6.16-4.51z"/>
    </svg>
    <span class="text-sm font-medium">Continue with Google</span>
  </button>
</template>
