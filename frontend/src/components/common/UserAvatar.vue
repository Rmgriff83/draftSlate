<script setup>
import { computed } from 'vue'

const props = defineProps({
  avatarUrl: { type: String, default: null },
  name: { type: String, default: '' },
  size: { type: String, default: 'md' },
})

const sizeClasses = {
  xs: 'w-5 h-5 text-[8px]',
  sm: 'w-6 h-6 text-[9px]',
  md: 'w-8 h-8 text-sm',
  lg: 'w-10 h-10 text-base',
  xl: 'w-14 h-14 text-lg',
}

const initials = computed(() => {
  if (!props.name) return '?'
  const parts = props.name.trim().split(/\s+/)
  if (parts.length >= 2) return (parts[0][0] + parts[1][0]).toUpperCase()
  return parts[0][0]?.toUpperCase() || '?'
})
</script>

<template>
  <div
    :class="[
      'rounded-full bg-ds-primary flex items-center justify-center font-semibold text-white overflow-hidden shrink-0',
      sizeClasses[size] || sizeClasses.md,
    ]"
  >
    <img
      v-if="avatarUrl"
      :src="avatarUrl"
      :alt="name"
      class="w-full h-full object-cover"
    />
    <span v-else>{{ initials }}</span>
  </div>
</template>
