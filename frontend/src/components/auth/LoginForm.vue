<script setup>
import { reactive, computed } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { useVuelidate } from '@vuelidate/core'
import { required, email } from '@vuelidate/validators'
import { useAuthStore } from '@/stores/auth'
import SocialLoginButton from './SocialLoginButton.vue'

const router = useRouter()
const route = useRoute()
const auth = useAuthStore()

const form = reactive({
  email: '',
  password: '',
})

const serverError = reactive({ message: '' })

const rules = {
  email: { required, email },
  password: { required },
}

const v$ = useVuelidate(rules, form)

const isSubmitting = computed(() => auth.loading)

async function handleSubmit() {
  const isValid = await v$.value.$validate()
  if (!isValid) return

  serverError.message = ''
  const result = await auth.login({
    email: form.email,
    password: form.password,
  })

  if (result.success) {
    const redirect = route.query.redirect || '/app/dashboard'
    router.push(redirect)
  } else {
    serverError.message = result.message
  }
}
</script>

<template>
  <form @submit.prevent="handleSubmit" class="space-y-4">
    <h2 class="text-xl font-semibold text-ds-text-primary">Sign in</h2>

    <div v-if="serverError.message" class="p-3 rounded-ds-sm bg-ds-red/10 border border-ds-red/20 text-ds-red text-sm">
      {{ serverError.message }}
    </div>

    <div>
      <label for="email" class="block text-sm font-medium text-ds-text-secondary mb-1">Email</label>
      <input
        id="email"
        v-model="form.email"
        type="email"
        autocomplete="email"
        class="w-full px-3 py-2 bg-ds-bg-tertiary border border-ds-border rounded-ds-sm text-ds-text-primary placeholder-ds-text-tertiary focus:outline-none focus:border-ds-primary focus:ring-1 focus:ring-ds-primary"
        :class="{ 'border-ds-red': v$.email.$error }"
        placeholder="you@example.com"
      />
      <p v-if="v$.email.$error" class="mt-1 text-xs text-ds-red">{{ v$.email.$errors[0].$message }}</p>
    </div>

    <div>
      <label for="password" class="block text-sm font-medium text-ds-text-secondary mb-1">Password</label>
      <input
        id="password"
        v-model="form.password"
        type="password"
        autocomplete="current-password"
        class="w-full px-3 py-2 bg-ds-bg-tertiary border border-ds-border rounded-ds-sm text-ds-text-primary placeholder-ds-text-tertiary focus:outline-none focus:border-ds-primary focus:ring-1 focus:ring-ds-primary"
        :class="{ 'border-ds-red': v$.password.$error }"
        placeholder="Your password"
      />
      <p v-if="v$.password.$error" class="mt-1 text-xs text-ds-red">{{ v$.password.$errors[0].$message }}</p>
    </div>

    <button
      type="submit"
      :disabled="isSubmitting"
      class="w-full py-2.5 px-4 bg-ds-primary hover:bg-ds-primary-light text-white font-semibold rounded-ds-sm transition-colors duration-ds-fast ease-ds-out disabled:opacity-50 disabled:cursor-not-allowed"
    >
      {{ isSubmitting ? 'Signing in...' : 'Sign in' }}
    </button>

    <div class="relative my-4">
      <div class="absolute inset-0 flex items-center"><div class="w-full border-t border-ds-border"></div></div>
      <div class="relative flex justify-center text-xs"><span class="px-2 bg-ds-bg-secondary text-ds-text-tertiary">or</span></div>
    </div>

    <SocialLoginButton />

    <p class="text-center text-sm text-ds-text-secondary mt-4">
      Don't have an account?
      <router-link to="/register" class="text-ds-primary hover:text-ds-primary-light font-medium">Sign up</router-link>
    </p>
  </form>
</template>
