<script setup>
import { reactive, computed } from 'vue'
import { useRouter } from 'vue-router'
import { useVuelidate } from '@vuelidate/core'
import { required, email, minLength, sameAs, maxLength, helpers } from '@vuelidate/validators'
import { useAuthStore } from '@/stores/auth'
import SocialLoginButton from './SocialLoginButton.vue'

const router = useRouter()
const auth = useAuthStore()

const form = reactive({
  display_name: '',
  email: '',
  password: '',
  password_confirmation: '',
})

const serverErrors = reactive({})

const rules = {
  display_name: {
    required,
    maxLength: maxLength(50),
  },
  email: { required, email },
  password: {
    required,
    minLength: minLength(8),
  },
  password_confirmation: {
    required,
    sameAs: helpers.withMessage('Passwords must match', sameAs(computed(() => form.password))),
  },
}

const v$ = useVuelidate(rules, form)

const isSubmitting = computed(() => auth.loading)

async function handleSubmit() {
  const isValid = await v$.value.$validate()
  if (!isValid) return

  Object.keys(serverErrors).forEach((k) => delete serverErrors[k])

  const result = await auth.register({
    display_name: form.display_name,
    email: form.email,
    password: form.password,
    password_confirmation: form.password_confirmation,
  })

  if (result.success) {
    router.push('/app/dashboard')
  } else {
    Object.assign(serverErrors, result.errors)
  }
}
</script>

<template>
  <form @submit.prevent="handleSubmit" class="space-y-4">
    <h2 class="text-xl font-semibold text-ds-text-primary">Create account</h2>

    <div>
      <label for="display_name" class="block text-sm font-medium text-ds-text-secondary mb-1">Display Name</label>
      <input
        id="display_name"
        v-model="form.display_name"
        type="text"
        autocomplete="name"
        class="w-full px-3 py-2 bg-ds-bg-tertiary border border-ds-border rounded-ds-sm text-ds-text-primary placeholder-ds-text-tertiary focus:outline-none focus:border-ds-primary focus:ring-1 focus:ring-ds-primary"
        :class="{ 'border-ds-red': v$.display_name.$error || serverErrors.display_name }"
        placeholder="Your display name"
      />
      <p v-if="v$.display_name.$error" class="mt-1 text-xs text-ds-red">{{ v$.display_name.$errors[0].$message }}</p>
      <p v-else-if="serverErrors.display_name" class="mt-1 text-xs text-ds-red">{{ serverErrors.display_name[0] }}</p>
    </div>

    <div>
      <label for="reg-email" class="block text-sm font-medium text-ds-text-secondary mb-1">Email</label>
      <input
        id="reg-email"
        v-model="form.email"
        type="email"
        autocomplete="email"
        class="w-full px-3 py-2 bg-ds-bg-tertiary border border-ds-border rounded-ds-sm text-ds-text-primary placeholder-ds-text-tertiary focus:outline-none focus:border-ds-primary focus:ring-1 focus:ring-ds-primary"
        :class="{ 'border-ds-red': v$.email.$error || serverErrors.email }"
        placeholder="you@example.com"
      />
      <p v-if="v$.email.$error" class="mt-1 text-xs text-ds-red">{{ v$.email.$errors[0].$message }}</p>
      <p v-else-if="serverErrors.email" class="mt-1 text-xs text-ds-red">{{ serverErrors.email[0] }}</p>
    </div>

    <div>
      <label for="reg-password" class="block text-sm font-medium text-ds-text-secondary mb-1">Password</label>
      <input
        id="reg-password"
        v-model="form.password"
        type="password"
        autocomplete="new-password"
        class="w-full px-3 py-2 bg-ds-bg-tertiary border border-ds-border rounded-ds-sm text-ds-text-primary placeholder-ds-text-tertiary focus:outline-none focus:border-ds-primary focus:ring-1 focus:ring-ds-primary"
        :class="{ 'border-ds-red': v$.password.$error || serverErrors.password }"
        placeholder="Minimum 8 characters"
      />
      <p v-if="v$.password.$error" class="mt-1 text-xs text-ds-red">{{ v$.password.$errors[0].$message }}</p>
      <p v-else-if="serverErrors.password" class="mt-1 text-xs text-ds-red">{{ serverErrors.password[0] }}</p>
    </div>

    <div>
      <label for="reg-password-confirm" class="block text-sm font-medium text-ds-text-secondary mb-1">Confirm Password</label>
      <input
        id="reg-password-confirm"
        v-model="form.password_confirmation"
        type="password"
        autocomplete="new-password"
        class="w-full px-3 py-2 bg-ds-bg-tertiary border border-ds-border rounded-ds-sm text-ds-text-primary placeholder-ds-text-tertiary focus:outline-none focus:border-ds-primary focus:ring-1 focus:ring-ds-primary"
        :class="{ 'border-ds-red': v$.password_confirmation.$error }"
        placeholder="Confirm your password"
      />
      <p v-if="v$.password_confirmation.$error" class="mt-1 text-xs text-ds-red">{{ v$.password_confirmation.$errors[0].$message }}</p>
    </div>

    <button
      type="submit"
      :disabled="isSubmitting"
      class="w-full py-2.5 px-4 bg-ds-primary hover:bg-ds-primary-light text-white font-semibold rounded-ds-sm transition-colors duration-ds-fast ease-ds-out disabled:opacity-50 disabled:cursor-not-allowed"
    >
      {{ isSubmitting ? 'Creating account...' : 'Create account' }}
    </button>

    <div class="relative my-4">
      <div class="absolute inset-0 flex items-center"><div class="w-full border-t border-ds-border"></div></div>
      <div class="relative flex justify-center text-xs"><span class="px-2 bg-ds-bg-secondary text-ds-text-tertiary">or</span></div>
    </div>

    <SocialLoginButton />

    <p class="text-center text-sm text-ds-text-secondary mt-4">
      Already have an account?
      <router-link to="/login" class="text-ds-primary hover:text-ds-primary-light font-medium">Sign in</router-link>
    </p>
  </form>
</template>
