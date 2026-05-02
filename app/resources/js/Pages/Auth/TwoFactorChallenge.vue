<script setup>
import AuthInput from '@/Components/Auth/AuthInput.vue'
import AppButton from '@/Components/App/AppButton.vue'
import GuestHeader from '@/Components/Landing/GuestHeader.vue'
import LandingFooter from '@/Components/Landing/LandingFooter.vue'
import { Head, Link, useForm } from '@inertiajs/vue3'

const props = defineProps({
    email: { type: String, required: true },
    show_setup: { type: Boolean, default: false },
    setup_secret: { type: String, default: '' },
    setup_qr_image_url: { type: String, default: '' },
})

const form = useForm({
    code: '',
})

const submit = () => {
    form.post(route('two-factor.challenge.store'), {
        onFinish: () => form.reset('code'),
    })
}
</script>

<template>
    <div class="twofa-page">
        <Head title="2FA patvirtinimas" />
        <GuestHeader />
        <main class="twofa-page-main">
            <div class="twofa-card">
                <form class="twofa-form" @submit.prevent="submit">
                    <h2 class="twofa-title">2FA patvirtinimas</h2>
                    <p class="twofa-subtitle">
                        Įveskite 6 skaitmenų kodą iš Google Authenticator paskyrai:
                        <strong>{{ props.email }}</strong>
                    </p>
                    <div v-if="props.show_setup" class="twofa-setup">
                        <p class="twofa-setup-text">Pirmiausia nuskenuokite QR kodą Google Authenticator programėle:</p>
                        <img v-if="props.setup_qr_image_url" :src="props.setup_qr_image_url" alt="2FA QR kodas" class="twofa-qr" />
                        <label class="twofa-secret-label">
                            Atsarginis kodas (secret)
                            <input class="twofa-secret-input" :value="props.setup_secret" readonly>
                        </label>
                    </div>
                    <AuthInput
                        id="two-factor-code"
                        type="text"
                        placeholder="000000"
                        v-model="form.code"
                        :error="form.errors.code"
                    />
                    <div class="twofa-actions">
                        <AppButton text="Patvirtinti" :as-button="false" :loading="form.processing" />
                        <Link :href="route('two-factor.challenge.cancel')" method="post" as="button" type="button" class="twofa-cancel">
                            Atšaukti
                        </Link>
                    </div>
                </form>
            </div>
        </main>
        <LandingFooter />
    </div>
</template>

<style scoped>
.twofa-page {
    min-height: 100vh;
    display: flex;
    flex-direction: column;
    background: #f5f6f8;
}
.twofa-page-main {
    flex: 1;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 2rem 1rem;
}
.twofa-card {
    width: 100%;
    max-width: 440px;
    background: #fff;
    border-radius: 12px;
    box-shadow: 0 4px 24px rgba(0, 0, 0, 0.08);
    padding: 2rem 0;
}
.twofa-form {
    margin: 0 auto;
}
.twofa-title {
    margin: 0 40px 8px;
    font-size: 24px;
}
.twofa-subtitle {
    margin: 0 40px 16px;
    color: #666;
    font-size: 14px;
    line-height: 1.4;
}
.twofa-setup {
    margin: 0 40px 16px;
    padding: 12px;
    border: 1px solid #e5e7eb;
    border-radius: 10px;
    background: #f8fafc;
}
.twofa-setup-text {
    margin: 0 0 10px;
    font-size: 13px;
    color: #333;
}
.twofa-qr {
    display: block;
    width: 180px;
    height: 180px;
    margin: 0 0 10px;
    border-radius: 8px;
    background: #fff;
    border: 1px solid #d1d5db;
    padding: 6px;
}
.twofa-secret-label {
    display: block;
    font-size: 12px;
    color: #444;
}
.twofa-secret-input {
    margin-top: 6px;
    width: 100%;
    height: 38px;
    border: 1px solid #d1d5db;
    border-radius: 8px;
    padding: 0 10px;
    box-sizing: border-box;
    font-family: monospace;
}
.twofa-actions {
    margin: 0 40px;
}
.twofa-actions :deep(.app-button),
.twofa-cancel {
    width: 100%;
    height: 48px;
    min-height: 48px;
    box-sizing: border-box;
}

.twofa-actions :deep(.app-button) {
    margin-bottom: 12px;
}

.twofa-cancel {
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0;
    padding: 10px 16px;
    border: 1px solid #ccc;
    border-radius: var(--button-border-radius, 15px);
    background: #fff;
    color: #333;
    cursor: pointer;
    font-family: var(--font-family-base, 'Open Sans', sans-serif);
    font-size: var(--button-font-size, 16px);
    font-weight: var(--button-font-weight, 400);
    line-height: 150%;
    letter-spacing: -0.011em;
    text-decoration: none;
    -webkit-appearance: none;
    appearance: none;
}
</style>
