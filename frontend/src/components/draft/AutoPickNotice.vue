<script setup>
import { ref, watch } from 'vue'
import { useDraftStore } from '@/stores/draft'

const draft = useDraftStore()
const show = ref(false)
const message = ref('')
let timeout = null

// Watch for new picks that are auto-picks
watch(
  () => draft.draftState?.picks?.length,
  (newLen, oldLen) => {
    if (!newLen || !oldLen || newLen <= oldLen) return
    const latestPick = draft.draftState.picks[draft.draftState.picks.length - 1]
    if (latestPick?.is_auto_pick) {
      message.value = `Auto-pick: ${latestPick.drafter_team} selected ${latestPick.description}`
      show.value = true
      if (timeout) clearTimeout(timeout)
      timeout = setTimeout(() => { show.value = false }, 4000)
    }
  }
)
</script>

<template>
  <Transition
    enter-active-class="transition ease-out duration-300"
    enter-from-class="opacity-0 -translate-y-4"
    enter-to-class="opacity-100 translate-y-0"
    leave-active-class="transition ease-in duration-200"
    leave-from-class="opacity-100 translate-y-0"
    leave-to-class="opacity-0 -translate-y-4"
  >
    <div
      v-if="show"
      class="fixed top-16 left-4 right-4 z-50 ds-card bg-ds-yellow/10 border-ds-yellow/30 p-3"
    >
      <div class="flex items-center gap-2">
        <svg class="w-4 h-4 text-ds-yellow flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
          <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z" />
        </svg>
        <p class="text-xs text-ds-text-primary flex-1">{{ message }}</p>
        <button @click="show = false" class="text-ds-text-tertiary">
          <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
          </svg>
        </button>
      </div>
    </div>
  </Transition>
</template>
