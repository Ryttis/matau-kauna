<script setup>
import AuthInput from '@/Components/Auth/AuthInput.vue';
import AppButton from '@/Components/App/AppButton.vue';
import AppPhoneInternational from '@/Components/App/AppPhoneInternational.vue';
import { isValidOptionalPhone } from '@/constants/phoneInternational';
import GuestHeader from '@/Components/Landing/GuestHeader.vue';
import LandingFooter from '@/Components/Landing/LandingFooter.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';

const props = defineProps({
    prefill: {
        type: Object,
        required: true,
    },
    companyName: {
        type: String,
        default: null,
    },
    isCompany: {
        type: Boolean,
        default: false,
    },
});

const form = useForm({
    name: props.prefill.name ?? '',
    surname: props.prefill.surname ?? '',
    email: props.prefill.email ?? '',
    phone: props.prefill.phone ?? '',
});

const localErrors = ref({
    name: '',
    surname: '',
    email: '',
    phone: '',
});

const emailPattern = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

function dropLocalError(field) {
    if (!localErrors.value[field]) {
        return;
    }
    const next = { ...localErrors.value };
    next[field] = '';
    localErrors.value = next;
}

watch(
    () => form.name,
    () => {
        dropLocalError('name');
        form.clearErrors('name');
    }
);
watch(
    () => form.surname,
    () => {
        dropLocalError('surname');
        form.clearErrors('surname');
    }
);
watch(
    () => form.email,
    () => {
        dropLocalError('email');
        form.clearErrors('email');
    }
);
watch(
    () => form.phone,
    () => {
        dropLocalError('phone');
        form.clearErrors('phone');
    }
);

function validateClient() {
    const err = { name: '', surname: '', email: '', phone: '' };
    const n = form.name.trim();
    const s = form.surname.trim();
    const em = form.email.trim();
    const ph = form.phone.trim();

    if (!n) {
        err.name = 'Įveskite vardą.';
    } else if (n.length > 255) {
        err.name = 'Vardas negali būti ilgesnis nei 255 simbolių.';
    }

    if (!s) {
        err.surname = 'Įveskite pavardę.';
    } else if (s.length > 255) {
        err.surname = 'Pavardė negali būti ilgesnė nei 255 simbolių.';
    }

    if (!em) {
        err.email = 'Įveskite el. pašto adresą.';
    } else if (!emailPattern.test(em)) {
        err.email = 'Įveskite teisingą el. pašto adresą.';
    } else if (em.length > 255) {
        err.email = 'El. paštas negali būti ilgesnis nei 255 simbolių.';
    }

    if (ph && !isValidOptionalPhone(ph)) {
        err.phone =
            'Įveskite teisingą numerį: pasirinkite šalį ir užpildykite lauką, arba palikite telefoną tuščią.';
    } else if (ph.length > 32) {
        err.phone = 'Telefono numeris negali būti ilgesnis nei 32 simboliai.';
    }

    localErrors.value = err;
    return !Object.values(err).some(Boolean);
}

function fieldError(field) {
    return localErrors.value[field] || form.errors[field] || '';
}

const hasLocalErrors = computed(() => Object.values(localErrors.value).some(Boolean));

const canSubmit = computed(() => {
    if (form.processing) {
        return false;
    }
    const filled =
        form.name.trim() !== '' &&
        form.surname.trim() !== '' &&
        form.email.trim() !== '';
    if (!filled) {
        return false;
    }
    return !hasLocalErrors.value;
});

const submit = () => {
    if (!validateClient()) {
        return;
    }
    form.post(route('viisp.register.store'), {
        preserveScroll: true,
        onSuccess: () => {
            localErrors.value = { name: '', surname: '', email: '', phone: '' };
        },
    });
};
</script>

<template>
    <div class="viisp-register-page">
        <Head title="Užbaikite registraciją" />

        <GuestHeader />

        <main class="viisp-register-main">
            <div class="viisp-register-intro">
                <h1 class="viisp-register-title">Užbaikite registraciją</h1>
                <p class="viisp-register-lead">
                    Vardas ir pavardė užpildomi pagal el. valdžios vartų informaciją ir negali būti keičiami.
                    Po patvirtinimo būsite automatiškai prijungti.
                </p>
                <p v-if="isCompany && companyName" class="viisp-register-company">
                    Jungiatės kaip įmonės atstovas: <strong>{{ companyName }}</strong>
                </p>
            </div>

            <form @submit.prevent="submit" class="viisp-register-form">
                <div class="viisp-register-field">
                    <label class="viisp-register-label" for="viisp-name">
                        Vardas <span class="viisp-register-req" aria-hidden="true">*</span>
                    </label>
                    <AuthInput
                        id="viisp-name"
                        type="text"
                        placeholder="Įveskite vardą"
                        v-model="form.name"
                        :readonly="true"
                        :error="fieldError('name')"
                    />
                </div>

                <div class="viisp-register-field">
                    <label class="viisp-register-label" for="viisp-surname">
                        Pavardė <span class="viisp-register-req" aria-hidden="true">*</span>
                    </label>
                    <AuthInput
                        id="viisp-surname"
                        type="text"
                        placeholder="Įveskite pavardę"
                        v-model="form.surname"
                        :readonly="true"
                        :error="fieldError('surname')"
                    />
                </div>

                <div class="viisp-register-field">
                    <label class="viisp-register-label" for="viisp-email">
                        El. paštas <span class="viisp-register-req" aria-hidden="true">*</span>
                    </label>
                    <AuthInput
                        id="viisp-email"
                        type="email"
                        placeholder="vardas@pavyzdys.lt"
                        v-model="form.email"
                        :error="fieldError('email')"
                    />
                </div>

                <div class="viisp-register-field">
                    <label class="viisp-register-label" for="viisp-phone-national">Telefono numeris</label>
                    <AppPhoneInternational
                        id="viisp-phone"
                        v-model="form.phone"
                        :error="fieldError('phone')"
                    />
                </div>

                <div class="viisp-register-field viisp-register-field--actions">
                    <AppButton
                        text="Patvirtinti ir prisijungti"
                        :disabled="!canSubmit || form.processing"
                    />
                </div>
            </form>
        </main>

        <LandingFooter />
    </div>
</template>

<style scoped>
.viisp-register-page {
    min-height: 100vh;
    display: flex;
    flex-direction: column;
    background: #fff;
    font-family: var(--font-family-base, 'Open Sans', sans-serif);
}

.viisp-register-main {
    flex: 1;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 2rem 1rem;
}

.viisp-register-intro {
    width: 100%;
    max-width: 440px;
    margin-bottom: 1.5rem;
}

.viisp-register-title {
    font-size: 1.5rem;
    font-weight: 700;
    margin: 0 0 0.75rem 0;
    color: #1a1a1a;
}

.viisp-register-lead {
    margin: 0;
    font-size: 1rem;
    line-height: 1.5;
    color: #444;
}

.viisp-register-company {
    margin: 1rem 0 0 0;
    font-size: 0.95rem;
    color: #333;
}

.viisp-register-label {
    display: block;
    font-size: 0.95rem;
    font-weight: 600;
    color: #333;
    margin-bottom: 6px;
}

.viisp-register-req {
    color: #c62828;
    font-weight: 700;
}

.viisp-register-form {
    width: 100%;
    max-width: 400px;
    margin: 0 auto;
    font-size: 18px;
    display: flex;
    flex-direction: column;
    gap: 1rem;
}

.viisp-register-field {
    width: 100%;
}

.viisp-register-field :deep(.form-input-wrp) {
    margin-left: 0;
    margin-right: 0;
}

.viisp-register-field--actions :deep(.app-button) {
    width: 100%;
    height: var(--button-height, 45px);
    min-height: var(--button-height, 45px);
    margin-bottom: 0;
}
</style>
