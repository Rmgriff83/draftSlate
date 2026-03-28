<script setup>
import { ref } from 'vue'
import { useRoute } from 'vue-router'
import { useDraftStore } from '@/stores/draft'

const route = useRoute()
const draft = useDraftStore()
const disabling = ref(false)

async function resumeDrafting() {
  disabling.value = true
  await draft.disableAutoDraft(route.params.id)
  disabling.value = false
}
</script>

<template>
  <div
    v-if="draft.amInAutoDraft"
    class="ds-card border-ds-red/40 bg-ds-red/10 p-3 flex items-center gap-3"
  >
    <svg class="w-5 h-5 text-ds-red flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
      <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z" />
    </svg>
    <p class="text-xs text-ds-red font-medium flex-1">
      Autodraft is on. Picks are being made automatically in 3 seconds.
    </p>
    <button
      @click="resumeDrafting"
      :disabled="disabling"
      class="px-3 py-1.5 text-xs font-semibold text-white bg-ds-red hover:bg-ds-red/80 rounded-ds-sm transition-colors whitespace-nowrap disabled:opacity-50"
    >
      {{ disabling ? 'Resuming...' : 'Resume Drafting' }}
    </button>
  </div>
</template>
