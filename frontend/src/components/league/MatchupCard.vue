<script setup>
import { computed } from 'vue'
import UserAvatar from '@/components/common/UserAvatar.vue'
import { useAuthStore } from '@/stores/auth'

const props = defineProps({
  matchup: { type: Object, required: true },
})

defineEmits(['click'])

const auth = useAuthStore()

const isUserMatchup = computed(() => {
  const userId = auth.user?.id
  if (!userId) return false
  return (
    props.matchup.home_team?.user_id === userId ||
    props.matchup.away_team?.user_id === userId
  )
})

const hasScores = computed(() =>
  props.matchup.home_score != null && props.matchup.away_score != null
)

const isCompleted = computed(() => props.matchup.status === 'completed')
</script>

<template>
  <div
    @click="$emit('click', matchup)"
    :class="[
      'ds-card p-3 cursor-pointer active:bg-ds-bg-hover transition-colors',
      isUserMatchup ? 'ring-1 ring-ds-primary/30' : '',
    ]"
  >
    <div class="flex items-center gap-3">
      <!-- Home team -->
      <div class="flex-1 flex items-center gap-2 min-w-0">
        <UserAvatar
          :avatar-url="matchup.home_team?.avatar_url"
          :name="matchup.home_team?.user_name || matchup.home_team?.team_name"
          size="sm"
        />
        <p class="text-xs font-medium text-ds-text-primary truncate">
          {{ matchup.home_team?.team_name }}
        </p>
      </div>

      <!-- Score / vs -->
      <div class="flex-shrink-0 text-center min-w-[56px]">
        <template v-if="hasScores">
          <span class="text-sm font-bold text-ds-text-primary">
            {{ matchup.home_score }}
          </span>
          <span class="text-xs text-gray-500 mx-1">&ndash;</span>
          <span class="text-sm font-bold text-ds-text-primary">
            {{ matchup.away_score }}
          </span>
        </template>
        <span v-else class="text-xs font-semibold text-gray-500">vs</span>

        <!-- Status badge -->
        <p class="mt-0.5">
          <span
            v-if="isCompleted"
            class="text-[9px] font-medium text-gray-500"
          >Final</span>
          <span
            v-else-if="hasScores"
            class="text-[9px] font-medium text-ds-green"
          >Live</span>
        </p>
      </div>

      <!-- Away team -->
      <div class="flex-1 flex items-center gap-2 min-w-0 justify-end">
        <p class="text-xs font-medium text-ds-text-primary truncate text-right">
          {{ matchup.away_team?.team_name }}
        </p>
        <UserAvatar
          :avatar-url="matchup.away_team?.avatar_url"
          :name="matchup.away_team?.user_name || matchup.away_team?.team_name"
          size="sm"
        />
      </div>
    </div>
  </div>
</template>
