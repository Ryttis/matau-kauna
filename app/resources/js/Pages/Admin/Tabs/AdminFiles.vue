<script setup>
import { onMounted, ref } from 'vue'
import axios from 'axios'
import AppButton from '@/Components/App/AppButton.vue'
import AppCheckbox from '@/Components/App/AppCheckbox.vue'
import SuccessModal from '@/Components/App/SuccessModal.vue'
import { adminApi } from '@/constants/api/adminApi'

const CHANNEL = {
    web: 'Naršyklė',
}

const defaultChannel = () => ({
    allow_photo: true,
    allow_video: false,
    max_photo_size_kb: 5120,
    max_video_size_kb: 51200,
    max_photo_files_per_problem: 5,
    max_video_files_per_problem: 1,
    max_total_upload_kb: 51200,
    description_max_chars: 65535,
})

const loading = ref(true)
const saving = ref(false)
const errorMessage = ref('')
const successMessage = ref('')
const showSuccessModal = ref(false)

const config = ref({
    web: defaultChannel(),
    mobile: defaultChannel(),
})

function mergeChannel(base, incoming) {
    if (!incoming || typeof incoming !== 'object') return { ...base }
    return { ...base, ...incoming }
}

async function load() {
    loading.value = true
    errorMessage.value = ''
    try {
        const { data } = await axios.get(adminApi.fileUploadSettings)
        const b = defaultChannel()
        config.value = {
            web: mergeChannel(b, data.config?.web),
            mobile: mergeChannel(b, data.config?.mobile),
        }
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
        const payload = { web: { ...config.value.web }, mobile: { ...config.value.mobile } }
        payload.web.max_video_files_per_problem = 1
        payload.mobile.max_video_files_per_problem = 1
        await axios.put(adminApi.fileUploadSettings, { config: payload })
        successMessage.value = 'Nustatymai išsaugoti.'
        showSuccessModal.value = true
        await load()
    } catch (e) {
        const msg = e.response?.data?.message
        const errs = e.response?.data?.errors
        if (errs) {
            const first = Object.values(errs).flat()[0]
            errorMessage.value = first || msg || 'Nepavyko išsaugoti.'
        } else {
            errorMessage.value = msg ?? 'Nepavyko išsaugoti.'
        }
    } finally {
        saving.value = false
    }
}

onMounted(load)
</script>

<template>
    <div class="admin-files">
        <h2 class="title">Failai</h2>

        <p v-if="errorMessage" class="error">{{ errorMessage }}</p>
        <SuccessModal
            :show="showSuccessModal"
            :message="successMessage"
            @close="showSuccessModal = false; successMessage = ''"
        />

        <div v-if="loading" class="loading">Kraunama...</div>

        <template v-else>
            <div class="channels-row">
                <section v-for="(label, ch) in CHANNEL" :key="ch" class="card">
                    <h3 class="card-title">{{ label }}</h3>

                    <div class="card-toggles">
                        <label class="toggle">
                            <AppCheckbox
                                :model-value="config[ch].allow_photo"
                                @update:model-value="(v) => (config[ch].allow_photo = v)"
                            />
                            <span>Nuotraukos (JPEG, PNG)</span>
                        </label>
                        <label class="toggle">
                            <AppCheckbox
                                :model-value="config[ch].allow_video"
                                @update:model-value="(v) => (config[ch].allow_video = v)"
                            />
                            <span class="toggle-label-wrap">
                                Vaizdai (MP4, WebM, MOV)
                                <span class="toggle-hint">1 vaizdo įrašas</span>
                            </span>
                        </label>
                    </div>

                    <div class="card-fields">
                        <div class="field-row">
                            <span class="field-lbl">Nuotrauka, KB</span>
                            <input
                                v-model.number="config[ch].max_photo_size_kb"
                                type="number"
                                min="64"
                                max="102400"
                                class="inp"
                            />
                        </div>
                        <div v-if="config[ch].allow_video" class="field-row">
                            <span class="field-lbl">Vaizdas, KB</span>
                            <input
                                v-model.number="config[ch].max_video_size_kb"
                                type="number"
                                min="256"
                                max="524288"
                                class="inp"
                            />
                        </div>
                        <div class="field-row">
                            <span class="field-lbl">Nuotraukų sk.</span>
                            <input
                                v-model.number="config[ch].max_photo_files_per_problem"
                                type="number"
                                min="0"
                                max="50"
                                class="inp"
                            />
                        </div>
                        <div class="field-row">
                            <span class="field-lbl">Bendra suma, KB</span>
                            <input
                                v-model.number="config[ch].max_total_upload_kb"
                                type="number"
                                min="256"
                                max="1048576"
                                class="inp"
                            />
                        </div>
                    </div>
                </section>
            </div>

            <div class="actions">
                <AppButton text="Išsaugoti" variant="primary" as-button :disabled="saving" @click="save" />
            </div>
        </template>
    </div>
</template>

<style scoped>
.admin-files {
    width: 100%;
    max-width: 100%;
    box-sizing: border-box;
}

.title {
    font-size: 20px;
    font-weight: 600;
    margin: 0 0 20px;
    color: #1a1a1a;
}

.error {
    color: #c00;
    margin-bottom: 12px;
}

.loading {
    padding: 24px 0;
    color: #666;
}

.channels-row {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 20px;
    align-items: stretch;
    width: 100%;
}

@media (max-width: 960px) {
    .channels-row {
        grid-template-columns: 1fr;
    }
}

.card {
    min-width: 0;
    background: #fff;
    border: 1px solid #e2e5e9;
    border-radius: 10px;
    padding: 0;
    overflow: hidden;
}

.card-title {
    margin: 0;
    padding: 14px 18px;
    font-size: 15px;
    font-weight: 600;
    color: #111;
    background: #f8f9fb;
    border-bottom: 1px solid #e2e5e9;
}

.card-toggles {
    padding: 14px 18px;
    display: flex;
    flex-direction: column;
    gap: 10px;
    border-bottom: 1px solid #eef0f3;
}

.toggle {
    display: flex;
    align-items: flex-start;
    gap: 10px;
    cursor: pointer;
    font-size: 14px;
    color: #333;
}

.toggle-label-wrap {
    display: flex;
    flex-direction: column;
    gap: 2px;
    line-height: 1.35;
}

.toggle-hint {
    font-size: 12px;
    font-weight: 400;
    color: #8a9199;
}

.card-fields {
    padding: 0;
}

.field-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 16px;
    padding: 10px 18px;
    border-bottom: 1px solid #f0f2f5;
    font-size: 14px;
}

.field-row:last-child {
    border-bottom: none;
}

.field-lbl {
    color: #444;
    flex: 1;
    min-width: 0;
}

.inp {
    width: 7rem;
    flex-shrink: 0;
    padding: 7px 10px;
    border: 1px solid #cfd4dc;
    border-radius: 6px;
    font-size: 14px;
    text-align: right;
    font-variant-numeric: tabular-nums;
}

.inp:focus {
    outline: none;
    border-color: var(--color-primary, #2563eb);
    box-shadow: 0 0 0 2px rgba(37, 99, 235, 0.2);
}

.actions {
    margin-top: 24px;
    display: flex;
    justify-content: flex-start;
    width: 100%;
}
</style>
