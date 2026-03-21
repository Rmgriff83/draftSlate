<template>
  <div class="min-h-screen flex items-center justify-center bg-ds-bg-primary px-4">
    <div class="text-center">
      <h1 class="text-6xl font-extrabold text-ds-primary">404</h1>
      <p class="mt-4 text-lg text-ds-text-secondary">Page not found.</p>
      <router-link
        to="/"
        class="inline-block mt-6 px-6 py-2.5 bg-ds-primary hover:bg-ds-primary-light text-white font-semibold rounded-ds-sm transition-colors duration-ds-fast ease-ds-out"
      >
        Go Home
      </router-link>
    </div>
  </div>
</template>
