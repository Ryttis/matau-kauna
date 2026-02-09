<script setup>
import AppButton from '@/Components/App/AppButton.vue';
import LandingHeader from '@/Components/Landing/LandingHeader.vue';
import LandingFooter from '@/Components/Landing/LandingFooter.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';

const form = useForm({
    accepted: false,
});

const canSubmit = computed(() => form.accepted);

const submit = () => {
    if (!canSubmit.value) return;
    form.post(route('reporter.terms.store'), {
        onFinish: () => form.reset(),
    });
};
</script>

<template>
    <div class="reporter-terms-page">
        <Head title="Susipažinimas su asmens duomenų naudojimu ir tvarkymu" />

        <LandingHeader />

        <main class="reporter-terms-main">
            <div class="reporter-terms-card">
                <h1 class="reporter-terms-title">
                    Susipažinimas su asmens duomenų<br />naudojimu ir tvarkymu
                </h1>

                <p class="reporter-terms-text">
                    Prieš naudodamiesi sistema, susipažinkite su asmens duomenų naudojimo ir tvarkymo
                    taisyklėmis.
                </p>

                <div class="reporter-terms-links">
                    <Link :href="route('privacy')" class="reporter-terms-link">Privatumo politika</Link>
                    <Link :href="route('privacy')" class="reporter-terms-link">Slapukų politika</Link>
                </div>

                <form @submit.prevent="submit" class="reporter-terms-form">
                    <label class="reporter-terms-checkbox-label">
                        <input
                            v-model="form.accepted"
                            type="checkbox"
                            name="accepted"
                            value="1"
                            class="reporter-terms-checkbox"
                        />
                        <span class="reporter-terms-checkbox-text">Sutinku su taisyklėmis</span>
                    </label>

                    <div v-if="form.errors.accepted" class="reporter-terms-error">
                        {{ form.errors.accepted }}
                    </div>

                    <div class="reporter-terms-actions">
                        <AppButton
                            text="Toliau"
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
.reporter-terms-page {
    min-height: 100vh;
    display: flex;
    flex-direction: column;
    background: #fff;
    font-family: 'Open Sans', sans-serif;
}

.reporter-terms-main {
    flex: 1;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 2rem 1rem;
}

.reporter-terms-card {
    width: 100%;
    max-width: 440px;
    margin: 0 auto;
    font-size: 18px;
}

.reporter-terms-title {
    margin: 0 0 1rem 0;
    font-size: 24px;
    font-weight: 600;
    color: #333;
    line-height: 1.3;
    text-align: center;
}

.reporter-terms-text {
    margin: 0 0 1rem 0;
    color: #666;
    line-height: 1.5;
    font-size: 14px;
}

.reporter-terms-links {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
    margin-bottom: 1.25rem;
}

.reporter-terms-link {
    color: #666;
    font-size: 14px;
    text-decoration: underline;
}

.reporter-terms-link:hover {
    color: #1946A0;
}

.reporter-terms-form {
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
}

.reporter-terms-checkbox-label {
    display: flex;
    align-items: flex-start;
    gap: 0.5rem;
    cursor: pointer;
    font-size: 14px;
    color: #333;
}

.reporter-terms-checkbox {
    margin-top: 0.2rem;
    width: 18px;
    height: 18px;
    accent-color: #1946A0;
}

.reporter-terms-checkbox-text {
    flex: 1;
}

.reporter-terms-error {
    font-size: 12px;
    color: #c00;
}

.reporter-terms-actions :deep(.app-button) {
    width: 100%;
    height: 45px;
    min-height: 45px;
    display: flex;
    align-items: center;
    justify-content: center;
}
</style>
