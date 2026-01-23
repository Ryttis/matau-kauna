<script setup>
import AppButton from '@/Components/App/AppButton.vue';
import { Link } from '@inertiajs/vue3';

defineProps({
    canResetPassword: {
        type: Boolean,
        default: false,
    },
});
</script>

<template>
    <div class="login-form-input-wrp">
        <AppButton text="Prisijungti" />

        <Link
            v-if="canResetPassword"
            :href="route('password.request')"
            class="form-link"
        >
            Priminti slaptažodį
        </Link>
    </div>
</template>

<style scoped>
.login-form-input-wrp {
    margin: 0 40px;
}

.form-link {
    display: block;
    text-align: center;
    text-decoration: none;
    color: black;
    font-size: 12px;
}
</style>
