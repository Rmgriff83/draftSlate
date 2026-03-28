<script setup>
import { computed } from 'vue'
import { useDraftStore } from '@/stores/draft'

const draft = useDraftStore()

const minutes = computed(() => Math.floor(draft.timerSeconds / 60))
const seconds = computed(() => draft.timerSeconds % 60)
const timeDisplay = computed(() =>
  `${minutes.value}:${seconds.value.toString().padStart(2, '0')}`
)
const isUrgent = computed(() => draft.timerSeconds <= 10)

const roundDisplay = computed(() =>
  `R${draft.draftState?.current_round ?? 1}/${draft.draftState?.total_rounds ?? 1}`
)

function initials(name) {
  if (!name) return '?'
  return name
    .split(' ')
    .map((w) => w[0])
    .join('')
    .toUpperCase()
    .slice(0, 2)
}
</script>

<template>
  <div v-if="draft.upcomingDrafters.length" class="relative">
    <!-- Round badge (top-left overlay) -->
    <div class="absolute -top-2 -left-1 z-10 px-2 py-0.5 rounded-full bg-ds-bg-tertiary border border-ds-border text-[10px] font-bold text-ds-text-secondary shadow-sm">
      {{ roundDisplay }}
    </div>

    <!-- Timer badge (top-right overlay) -->
    <div
      class="absolute -top-2.5 -right-1 z-10 px-2.5 py-1 rounded-full border shadow-sm font-mono font-bold text-sm leading-none transition-colors duration-300"
      :class="isUrgent
        ? 'bg-ds-red/15 border-ds-red/40 text-ds-red animate-pulse'
        : 'bg-ds-bg-tertiary border-ds-border text-ds-text-primary'"
    >
      {{ timeDisplay }}
    </div>

    <!-- Card body -->
    <div class="ds-card p-3 pt-4">
      <div class="relative">
        <div class="overflow-x-auto overflow-y-visible hide-scrollbar">
          <TransitionGroup
            name="queue-slide"
            tag="div"
            class="flex gap-2"
          >
            <div
              v-for="drafter in draft.upcomingDrafters"
              :key="drafter.pickIndex"
              class="min-w-[72px] max-w-[72px] flex flex-col items-center gap-1.5 p-2 m-2 rounded-ds-sm border transition-all duration-300 relative"
              :class="
                drafter.isCurrent
                  ? 'bg-ds-primary/10 border-ds-primary/40'
                  : 'bg-ds-bg-secondary border-ds-border/50 opacity-60'
              "
            >
              <!-- "You're up" badge on current user's card -->
              <span
                v-if="drafter.isCurrent && draft.isMyTurn"
                class="absolute -top-1.5 left-1/2 -translate-x-1/2 px-1.5 py-0.5 rounded-full bg-ds-primary text-[7px] font-bold text-white uppercase tracking-wider whitespace-nowrap leading-none"
              >You're up</span>

              <div
                class="w-9 h-9 rounded-full flex items-center justify-center text-[11px] font-bold border-2 overflow-hidden flex-shrink-0"
                :class="
                  drafter.isCurrent
                    ? 'border-ds-primary bg-ds-primary/20 text-ds-primary ring-2 ring-ds-primary/30'
                    : 'border-ds-border bg-ds-bg-hover text-ds-text-secondary'
                "
              >
                <img
                  v-if="drafter.avatar_url"
                  :src="drafter.avatar_url"
                  :alt="drafter.team_name"
                  class="w-full h-full object-cover"
                />
                <span v-else>{{ initials(drafter.team_name) }}</span>
              </div>
              <!-- AUTO badge for autodraft members (outside overflow-hidden avatar) -->
              <span
                v-if="draft.autoDraftMembers.includes(drafter.id)"
                class="absolute top-[38px] left-1/2 -translate-x-1/2 px-1 py-px rounded-full bg-ds-red text-[6px] font-bold text-white uppercase tracking-wider whitespace-nowrap leading-none z-10"
              >AUTO</span>
              <span
                class="text-[10px] font-semibold w-full truncate text-center leading-tight"
                :class="drafter.isCurrent ? 'text-ds-primary' : 'text-ds-text-tertiary'"
              >
                {{ drafter.team_name }}
              </span>
            </div>
          </TransitionGroup>
        </div>
        <!-- Right fade gradient -->
        <div class="pointer-events-none absolute top-0 right-0 bottom-0 w-10 bg-gradient-to-l from-ds-bg-secondary to-transparent rounded-r-ds-sm"></div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.hide-scrollbar {
  -ms-overflow-style: none;
  scrollbar-width: none;
}
.hide-scrollbar::-webkit-scrollbar {
  display: none;
}

.queue-slide-enter-active {
  transition: all 0.4s cubic-bezier(0.16, 1, 0.3, 1);
}
.queue-slide-leave-active {
  transition: all 0.3s cubic-bezier(0.65, 0, 0.35, 1);
  position: absolute;
}
.queue-slide-enter-from {
  opacity: 0;
  transform: translateX(60px);
}
.queue-slide-leave-to {
  opacity: 0;
  transform: translateX(-60px);
}
.queue-slide-move {
  transition: transform 0.4s cubic-bezier(0.16, 1, 0.3, 1);
}
</style>
