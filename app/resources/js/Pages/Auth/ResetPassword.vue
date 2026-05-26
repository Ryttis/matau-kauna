<script setup>
import { Head, useForm } from '@inertiajs/vue3'
import AppInput from '@/Components/App/AppInput.vue'
import AppButton from '@/Components/App/AppButton.vue'

const props = defineProps({
  token: String,
  email: String,
})

const form = useForm({
  token: props.token,
  email: props.email || '',
  password: '',
  password_confirmation: '',
})

const submit = () => {
  form.post(route('password.update'))
}
</script>

<template>
  <Head title="Atkurti slaptažodį" />

  <div style="max-width: 420px; margin: 40px auto;">
    <h1 style="font-size: 22px; font-weight: 700; margin-bottom: 12px;">Atkurti slaptažodį</h1>

    <form @submit.prevent="submit" class="form-grid">
      <AppInput v-model="form.email" type="email" placeholder="El. paštas" />
      <div v-if="form.errors.email" class="error">{{ form.errors.email }}</div>

      <AppInput v-model="form.password" type="password" placeholder="Naujas slaptažodis" />
      <div v-if="form.errors.password" class="error">{{ form.errors.password }}</div>

      <AppInput v-model="form.password_confirmation" type="password" placeholder="Pakartoti slaptažodį" />

      <AppButton text="Pakeisti slaptažodį" variant="primary" :disabled="form.processing" />
    </form>
  </div>
</template>

<style scoped>
.form-grid { display: flex; flex-direction: column; gap: 10px; }
.error { color: var(--color-error); font-size: 12px; margin-top: -6px; white-space: pre-line; }
</style>
