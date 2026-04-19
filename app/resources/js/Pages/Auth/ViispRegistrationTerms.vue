<script setup>
import AppButton from '@/Components/App/AppButton.vue'
import GuestHeader from '@/Components/Landing/GuestHeader.vue'
import LandingFooter from '@/Components/Landing/LandingFooter.vue'
import { Head, Link, useForm } from '@inertiajs/vue3'
import { computed } from 'vue'

defineProps({
    terms_title: { type: String, default: 'Sistemos taisyklės ir asmens duomenų tvarkymas' },
    terms_body: { type: String, default: '' },
})

const form = useForm({
    accepted: false,
})

const canSubmit = computed(() => form.accepted)

const submit = () => {
    if (!canSubmit.value) return
    form.post(route('viisp.register.terms.store'), {
        onFinish: () => form.reset(),
    })
}
</script>

<template>
    <div class="viisp-reg-terms-page">
        <Head title="Taisyklės prieš registraciją" />

        <GuestHeader />

        <main class="viisp-reg-terms-main">
            <div class="viisp-reg-terms-card">
                <h1 class="viisp-reg-terms-title">
                    {{ terms_title }}
                </h1>

                <p class="viisp-reg-terms-lead">
                    Prieš pildant registracijos anketą, susipažinkite su taisyklėmis ir patvirtinkite sutikimą.
                </p>

                <div v-if="terms_body" class="viisp-reg-terms-content content-html" v-html="terms_body"></div>

                <div class="viisp-reg-terms-links">
                    <Link :href="route('privacy')" class="viisp-reg-terms-link">Privatumo politika</Link>
                    <Link :href="route('cookies')" class="viisp-reg-terms-link">Slapukų politika</Link>
                </div>

                <form class="viisp-reg-terms-form" @submit.prevent="submit">
                    <label class="viisp-reg-terms-checkbox-label">
                        <input
                            v-model="form.accepted"
                            type="checkbox"
                            name="accepted"
                            value="1"
                            class="viisp-reg-terms-checkbox"
                        />
                        <span class="viisp-reg-terms-checkbox-text">Sutinku su taisyklėmis</span>
                    </label>

                    <div v-if="form.errors.accepted" class="viisp-reg-terms-error">
                        {{ form.errors.accepted }}
                    </div>

                    <div class="viisp-reg-terms-actions">
                        <AppButton
                            text="Toliau į registraciją"
                            :as-button="true"
                            :disabled="!canSubmit || form.processing"
                            @click="submit"
                        />
                    </div>
                </form>
            </div>
        </main>

        <LandingFooter />
    </div>
</template>

<style scoped>
.viisp-reg-terms-page {
    min-height: 100vh;
    display: flex;
    flex-direction: column;
    background: #f5f6f8;
    font-family: var(--font-family-base, 'Open Sans', sans-serif);
}

.viisp-reg-terms-main {
    flex: 1;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 2rem 1rem;
}

.viisp-reg-terms-card {
    width: 100%;
    max-width: 440px;
    margin: 0 auto;
    background: #fff;
    border-radius: 12px;
    box-shadow: 0 4px 24px rgba(0, 0, 0, 0.08);
    padding: 2rem 1.75rem 2.25rem;
    box-sizing: border-box;
    font-size: 16px;
}

.viisp-reg-terms-title {
    margin: 0 0 0.75rem 0;
    font-size: 1.375rem;
    font-weight: 700;
    color: #1a1a1a;
    line-height: 1.3;
    text-align: center;
}

.viisp-reg-terms-lead {
    margin: 0 0 1.25rem 0;
    color: #555;
    line-height: 1.55;
    text-align: center;
    font-size: 0.95rem;
}

.viisp-reg-terms-content {
    margin: 0 0 1.25rem 0;
    padding: 1rem 1.1rem;
    background: #f6f7f9;
    border: 1px solid #e8eaed;
    border-radius: 8px;
    max-height: 38vh;
    overflow-y: auto;
    font-size: 0.9rem;
    line-height: 1.55;
    color: #333;
}

.viisp-reg-terms-content :deep(p) {
    margin: 0 0 0.5rem 0;
}

.viisp-reg-terms-content :deep(p:last-child) {
    margin-bottom: 0;
}

.viisp-reg-terms-content :deep(ul),
.viisp-reg-terms-content :deep(ol) {
    margin: 0 0 0.5rem 1rem;
}

.viisp-reg-terms-links {
    display: flex;
    flex-wrap: wrap;
    gap: 0.5rem 1.25rem;
    justify-content: center;
    align-items: center;
    margin-bottom: 1.35rem;
}

.viisp-reg-terms-link {
    font-size: 0.875rem;
    font-weight: 600;
    color: var(--color-primary, #1946a0);
    text-decoration: none;
}

.viisp-reg-terms-link:hover {
    text-decoration: none;
    opacity: 0.88;
}

.viisp-reg-terms-form {
    display: flex;
    flex-direction: column;
    gap: 1rem;
}

.viisp-reg-terms-checkbox-label {
    display: flex;
    align-items: flex-start;
    gap: 0.65rem;
    cursor: pointer;
    font-size: 0.95rem;
    line-height: 1.45;
    color: #333;
}

.viisp-reg-terms-checkbox {
    margin-top: 0.15rem;
    flex-shrink: 0;
    width: 18px;
    height: 18px;
    accent-color: var(--color-primary, #1946a0);
}

.viisp-reg-terms-checkbox-text {
    flex: 1;
}

.viisp-reg-terms-error {
    font-size: 0.8125rem;
    color: var(--color-error, #c62828);
}

.viisp-reg-terms-actions {
    display: flex;
    justify-content: stretch;
}

.viisp-reg-terms-actions :deep(.app-button) {
    width: 100%;
    height: 48px;
    min-height: 48px;
    border-radius: 8px;
    font-size: 1rem;
}
</style>
