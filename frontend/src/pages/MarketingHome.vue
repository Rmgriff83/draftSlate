<script setup>
import { ref, onMounted } from 'vue'

const features = [
  {
    title: 'Weekly Snake Draft',
    description: 'Draft real odds-based picks every week in a competitive snake draft with your league.',
    icon: 'draft',
  },
  {
    title: 'Head-to-Head Matchups',
    description: 'Go head-to-head each week. More correct picks wins. Simple, intense, strategic.',
    icon: 'matchup',
  },
  {
    title: 'Live Odds Tracking',
    description: 'Watch odds drift in real-time. See how your picks move from draft to kickoff.',
    icon: 'odds',
  },
  {
    title: 'Real Money Leagues',
    description: 'Buy in, compete for a full season, and win real payouts. Secure Stripe payments.',
    icon: 'money',
  },
]

const visible = ref(false)

onMounted(() => {
  visible.value = true
})
</script>

<template>
  <div class="min-h-screen bg-ds-bg-primary">
    <!-- Hero -->
    <section class="relative overflow-hidden">
      <div class="absolute inset-0 ds-gradient-animated bg-gradient-to-br from-ds-primary via-ds-primary-dark to-ds-bg-primary opacity-30"></div>
      <div class="relative max-w-4xl mx-auto px-6 py-24 text-center">
        <h1 class="text-4xl sm:text-5xl font-extrabold text-ds-text-primary leading-tight">
          Fantasy Sports,
          <span class="text-transparent bg-clip-text bg-gradient-to-r from-ds-primary-light to-ds-green">Reimagined</span>
        </h1>
        <p class="mt-6 text-lg text-ds-text-secondary max-w-2xl mx-auto">
          Draft real sportsbook picks. Compete head-to-head every week. The thrill of fantasy meets the edge of sports betting.
        </p>
        <div class="mt-8 flex flex-col sm:flex-row gap-4 justify-center">
          <router-link
            to="/register"
            class="px-8 py-3 bg-ds-primary hover:bg-ds-primary-light text-white font-semibold rounded-ds-sm shadow-ds-glow transition-all duration-ds-normal ease-ds-out hover:shadow-lg"
          >
            Get Started Free
          </router-link>
          <router-link
            to="/login"
            class="px-8 py-3 border border-ds-border text-ds-text-primary hover:bg-ds-bg-hover font-semibold rounded-ds-sm transition-colors duration-ds-fast ease-ds-out"
          >
            Sign In
          </router-link>
        </div>
      </div>
    </section>

    <!-- Features -->
    <section class="max-w-5xl mx-auto px-6 py-20">
      <h2 class="text-2xl font-bold text-ds-text-primary text-center mb-12">How DraftSlate Works</h2>
      <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
        <div
          v-for="(feature, index) in features"
          :key="feature.title"
          class="ds-card-interactive ds-card p-6"
          :class="visible ? 'ds-feature-card-enter' : 'opacity-0'"
          :style="{ animationDelay: `${index * 100}ms` }"
        >
          <!-- Draft icon -->
          <div class="w-10 h-10 rounded-ds-sm bg-ds-primary/10 flex items-center justify-center mb-4">
            <svg v-if="feature.icon === 'draft'" class="w-5 h-5 text-ds-primary" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
              <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 12h16.5m-16.5 3.75h16.5M3.75 19.5h16.5M5.625 4.5h12.75a1.875 1.875 0 010 3.75H5.625a1.875 1.875 0 010-3.75z" />
            </svg>
            <svg v-else-if="feature.icon === 'matchup'" class="w-5 h-5 text-ds-green" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
              <path stroke-linecap="round" stroke-linejoin="round" d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 013 19.875v-6.75zM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V8.625zM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V4.125z" />
            </svg>
            <svg v-else-if="feature.icon === 'odds'" class="w-5 h-5 text-ds-blue" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
              <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 18L9 11.25l4.306 4.307a11.95 11.95 0 015.814-5.519l2.74-1.22m0 0l-5.94-2.28m5.94 2.28l-2.28 5.941" />
            </svg>
            <svg v-else-if="feature.icon === 'money'" class="w-5 h-5 text-ds-gold" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
              <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v12m-3-2.818l.879.659c1.171.879 3.07.879 4.242 0 1.172-.879 1.172-2.303 0-3.182C13.536 12.219 12.768 12 12 12c-.725 0-1.45-.22-2.003-.659-1.106-.879-1.106-2.303 0-3.182s2.9-.879 4.006 0l.415.33M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
          </div>
          <h3 class="text-lg font-semibold text-ds-text-primary">{{ feature.title }}</h3>
          <p class="mt-2 text-sm text-ds-text-secondary leading-relaxed">{{ feature.description }}</p>
        </div>
      </div>
    </section>

    <!-- Footer -->
    <footer class="border-t border-ds-border py-8 text-center">
      <p class="text-sm text-ds-text-tertiary">DraftSlate &mdash; Fantasy sports meets sports betting strategy.</p>
    </footer>
  </div>
</template>
