<script setup>
import { onMounted, ref } from 'vue'
import axios from 'axios'
import AppButton from '@/Components/App/AppButton.vue'
import AppCheckbox from '@/Components/App/AppCheckbox.vue'
import AppInput from '@/Components/App/AppInput.vue'
import SuccessModal from '@/Components/App/SuccessModal.vue'
import { adminApi } from '@/constants/api/adminApi'

const loading = ref(true)
const saving = ref(false)
const errorMessage = ref('')
const successMessage = ref('')
const showSuccessModal = ref(false)

const form = ref({
    min_length: 8,
    require_lowercase: true,
    require_uppercase: true,
    require_special: true,
    two_factor_can_enable: true,
    prohibit_name_email: true,
})

async function load() {
    loading.value = true
    errorMessage.value = ''
    try {
        const { data } = await axios.get(adminApi.passwordPolicy)
        form.value = { ...form.value, ...data }
    } catch (e) {
        errorMessage.value = e.response?.data?.message ?? 'Nepavyko užkrauti.'
    } finally {
        loading.value = false
    }
}

async function save() {
    saving.value = true
    errorMessage.value = ''
    successMessage.value = ''
    try {
        await axios.put(adminApi.passwordPolicy, form.value)
        successMessage.value = 'Nustatymai išsaugoti.'
        showSuccessModal.value = true
    } catch (e) {
        errorMessage.value = e.response?.data?.message ?? 'Nepavyko išsaugoti.'
    } finally {
        saving.value = false
    }
}

onMounted(load)
</script>

<template>
    <div class="password-policy-tab">
        <h3 class="tab-title">Slaptažodžio reikalavimai</h3>
        <p class="tab-desc">Nustatymai taikomi vidiniams naudotojams ir pareiškėjams (registracija, slaptažodžio keitimas).</p>

        <p v-if="errorMessage" class="error">{{ errorMessage }}</p>
        <SuccessModal
            :show="showSuccessModal"
            :message="successMessage"
            @close="showSuccessModal = false; successMessage = ''"
        />

        <div v-if="loading" class="loading">Kraunama...</div>

        <template v-else>
            <div class="form-block">
                <label class="field-label">Minimalus simbolių skaičius</label>
                <AppInput v-model.number="form.min_length" type="number" min="6" max="128" class="input-narrow" />
            </div>
            <div class="form-block">
                <label class="checkbox-row">
                    <AppCheckbox v-model="form.require_lowercase" />
                    <span>Reikalauti bent vienos mažosios raidės</span>
                </label>
            </div>
            <div class="form-block">
                <label class="checkbox-row">
                    <AppCheckbox v-model="form.require_uppercase" />
                    <span>Reikalauti bent vienos didžiosios raidės</span>
                </label>
            </div>
            <div class="form-block">
                <label class="checkbox-row">
                    <AppCheckbox v-model="form.require_special" />
                    <span>Reikalauti bent vieno specialaus simbolio</span>
                </label>
            </div>
            <div class="form-block">
                <label class="checkbox-row">
                    <AppCheckbox v-model="form.prohibit_name_email" />
                    <span>Drausti slaptažodyje naudoti vardą, pavardę ar el. pašto adresą</span>
                </label>
            </div>
            <div class="form-block">
                <label class="checkbox-row">
                    <AppCheckbox v-model="form.two_factor_can_enable" />
                    <span>Leisti įjungti dviejų faktorių autentifikavimą</span>
                </label>
            </div>
            <AppButton text="Išsaugoti" variant="primary" as-button :disabled="saving" @click="save" />
        </template>
    </div>
</template>

<style scoped>
.password-policy-tab {
    padding: 8px 0;
}
.tab-title {
    font-size: 17px;
    font-weight: 600;
    margin: 0 0 4px 0;
}
.tab-desc {
    font-size: 14px;
    color: #555;
    margin: 0 0 20px 0;
}
.error {
    color: #b91c1c;
    font-size: 14px;
    margin: 0 0 12px 0;
}
.loading {
    color: #666;
    font-size: 14px;
}
.form-block {
    margin-bottom: 16px;
}
.field-label {
    display: block;
    font-size: 14px;
    margin-bottom: 6px;
    color: #333;
}
.input-narrow {
    width: 80px;
}
.input-narrow :deep(.app-input-edit) {
    width: 80px;
    padding: 8px 12px;
    height: auto;
    min-height: 38px;
    line-height: 1.4;
    box-sizing: border-box;
}
.checkbox-row {
    display: flex;
    align-items: center;
    gap: 8px;
    cursor: pointer;
    font-size: 14px;
    color: #333;
}
</style>
