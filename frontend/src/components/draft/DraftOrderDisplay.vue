<script setup>
import { useDraftStore } from '@/stores/draft'

defineEmits(['close'])

const draft = useDraftStore()

const members = draft.draftState?.members || []
const order = draft.draftState?.draft_order || []
const weights = draft.draftState?.draft_order_weights || []

function getMember(id) {
  return members.find((m) => m.id === id)
}

function getWeight(id) {
  return weights.find((w) => w.membership_id === id)
}
</script>

<template>
  <Teleport to="body">
    <div class="fixed inset-0 z-50 flex items-center justify-center p-4">
      <div class="fixed inset-0 bg-black/50" @click="$emit('close')"></div>
      <div class="ds-card p-5 w-full max-w-sm relative z-10 max-h-[80vh] overflow-y-auto">
        <div class="flex items-center justify-between mb-4">
          <h2 class="text-lg font-bold text-ds-text-primary">Draft Order</h2>
          <button @click="$emit('close')" class="text-ds-text-tertiary hover:text-ds-text-primary">
            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
              <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
            </svg>
          </button>
        </div>

        <div class="space-y-2">
          <div
            v-for="(membershipId, index) in order"
            :key="membershipId"
            class="flex items-center gap-3 p-2 rounded-ds-sm"
            :class="membershipId === draft.draftState?.current_drafter_id ? 'bg-ds-primary/10' : 'bg-ds-bg-hover'"
          >
            <span
              class="w-7 h-7 rounded-full flex items-center justify-center text-xs font-bold flex-shrink-0"
              :class="membershipId === draft.draftState?.current_drafter_id
                ? 'bg-ds-primary text-white'
                : 'bg-ds-bg-secondary text-ds-text-tertiary'"
            >
              {{ index + 1 }}
            </span>
            <div class="flex-1 min-w-0">
              <p class="text-sm font-medium text-ds-text-primary truncate">
                {{ getMember(membershipId)?.team_name || 'Unknown' }}
              </p>
              <p class="text-xs text-ds-text-tertiary">
                {{ getMember(membershipId)?.user_name }}
              </p>
            </div>
            <div v-if="getWeight(membershipId)" class="text-right flex-shrink-0">
              <p class="text-xs text-ds-text-secondary">{{ getWeight(membershipId).prior_correct }} picks</p>
              <p class="text-[10px] text-ds-text-tertiary">wt: {{ getWeight(membershipId).weight }}</p>
            </div>
          </div>
        </div>

        <p class="text-[10px] text-ds-text-tertiary mt-3">
          Snake draft: odd rounds go 1-N, even rounds go N-1. Teams with fewer prior correct picks have higher odds of picking first.
        </p>
      </div>
    </div>
  </Teleport>
</template>
