<script setup>
import { onMounted, ref } from 'vue'
import axios from 'axios'
import AppButton from '@/Components/App/AppButton.vue'
import AppCheckbox from '@/Components/App/AppCheckbox.vue'
import SuccessModal from '@/Components/App/SuccessModal.vue'
import { adminApi } from '@/constants/api/adminApi'

const CHANNEL = {
    web: 'Naršyklė',
    mobile: 'Mobilioji programėlė',
}

const VIDEO_DURATION_OPTIONS_SEC = [10, 15, 20, 25, 30, 35, 40, 45, 50, 55, 60]

const defaultPhotoFormats = () => [
    { label: 'JPEG', mime: 'image/jpeg', extensions: ['jpg', 'jpeg'] },
    { label: 'PNG', mime: 'image/png', extensions: ['png'] },
]

const defaultVideoFormats = () => [
    { label: 'MP4', mime: 'video/mp4', extensions: ['mp4'] },
    { label: 'WebM', mime: 'video/webm', extensions: ['webm'] },
    { label: 'MOV', mime: 'video/quicktime', extensions: ['mov'] },
]

const defaultChannel = () => ({
    allow_photo: true,
    allow_video: false,
    photo_formats: defaultPhotoFormats(),
    video_formats: defaultVideoFormats(),
    max_photo_size_kb: 5120,
    max_video_size_kb: 51200,
    max_photo_files_per_problem: 5,
    max_video_files_per_problem: 1,
    max_video_duration_seconds: 60,
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

function normalizeFormats(formats, fallback) {
    const normalized = Array.isArray(formats)
        ? formats
            .map((format) => ({
                label: String(format?.label ?? '').trim(),
                mime: String(format?.mime ?? '').trim().toLowerCase(),
                extensions: Array.isArray(format?.extensions)
                    ? format.extensions.map((ext) => String(ext).trim().toLowerCase().replace(/^\./, '')).filter(Boolean)
                    : [],
            }))
            .filter((format) => format.label && format.mime && format.extensions.length)
        : []

    return normalized.length ? normalized : fallback()
}

function mergeChannel(base, incoming) {
    if (!incoming || typeof incoming !== 'object') return { ...base }
    return {
        ...base,
        ...incoming,
        photo_formats: normalizeFormats(incoming.photo_formats, defaultPhotoFormats),
        video_formats: normalizeFormats(incoming.video_formats, defaultVideoFormats),
    }
}

function formatVideoCount(count) {
    const normalized = Number(count) || 0
    return normalized === 1 ? '1 vaizdo įrašas' : `${normalized} vaizdo įrašai`
}

function formatLabels(formats) {
    return formats.map((format) => format.label).filter(Boolean).join(', ')
}

function emptyFormat() {
    return { label: '', mime: '', extensions: [] }
}

function addFormat(channel, key) {
    config.value[channel][key].push(emptyFormat())
}

function removeFormat(channel, key, index) {
    config.value[channel][key].splice(index, 1)
}

function extensionsInput(format) {
    return Array.isArray(format.extensions) ? format.extensions.join(', ') : ''
}

function updateExtensions(format, value) {
    format.extensions = String(value ?? '')
        .split(/[,\s]+/)
        .map((ext) => ext.trim().toLowerCase().replace(/^\./, ''))
        .filter(Boolean)
}

async function load() {
    loading.value = true
    errorMessage.value = ''
    try {
        const { data } = await axios.get(adminApi.fileUploadSettings)
        config.value = {
            web: mergeChannel(defaultChannel(), data.config?.web),
            mobile: mergeChannel(defaultChannel(), data.config?.mobile),
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
        await axios.put(adminApi.fileUploadSettings, {
            config: {
                web: { ...config.value.web },
                mobile: { ...config.value.mobile },
            },
        })
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
        <h2 class="title">Failai ir Tekstas</h2>
        <p class="page-desc">
            Nustatykite, kokius failus pareiškėjas gali įkelti ir kiek teksto gali pateikti prie problemos.
            Naršyklės ir mobiliosios programėlės nustatymai valdomi atskirai.
        </p>

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
                            <span class="toggle-label-wrap">
                                Nuotraukos ({{ formatLabels(config[ch].photo_formats) }})
                            </span>
                        </label>
                        <label class="toggle">
                            <AppCheckbox
                                :model-value="config[ch].allow_video"
                                @update:model-value="(v) => (config[ch].allow_video = v)"
                            />
                            <span class="toggle-label-wrap">
                                Vaizdai ({{ formatLabels(config[ch].video_formats) }})
                                <span class="toggle-hint">
                                    {{ formatVideoCount(config[ch].max_video_files_per_problem) }},
                                    {{
                                        config[ch].max_video_duration_seconds >= 60
                                            ? 'iki 1 min.'
                                            : `iki ${config[ch].max_video_duration_seconds} s`
                                    }}
                                    trukmės
                                </span>
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
                                min="64"
                                max="102400"
                                class="inp"
                            />
                        </div>
                        <div v-if="config[ch].allow_video" class="field-row">
                            <span class="field-lbl">Maks. vaizdo trukmė</span>
                            <select v-model.number="config[ch].max_video_duration_seconds" class="inp inp--select">
                                <option v-for="sec in VIDEO_DURATION_OPTIONS_SEC" :key="sec" :value="sec">
                                    {{ sec === 60 ? '60 s (1 min.)' : `${sec} s` }}
                                </option>
                            </select>
                        </div>
                        <div v-if="config[ch].allow_video" class="field-row">
                            <span class="field-lbl">Vaizdo įrašų sk.</span>
                            <input
                                v-model.number="config[ch].max_video_files_per_problem"
                                type="number"
                                min="0"
                                max="20"
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
                            <span class="field-lbl">Aprašymo simbolių sk.</span>
                            <input
                                v-model.number="config[ch].description_max_chars"
                                type="number"
                                min="1000"
                                max="65535"
                                class="inp"
                            />
                        </div>
                    </div>

                    <div v-if="config[ch].allow_photo" class="formats-block">
                        <div class="formats-title-row">
                            <h4 class="formats-title">Nuotraukų formatai</h4>
                            <button type="button" class="link-btn" @click="addFormat(ch, 'photo_formats')">+ Pridėti</button>
                        </div>
                        <div v-for="(format, index) in config[ch].photo_formats" :key="`photo-${index}`" class="format-row">
                            <input v-model="format.label" class="format-input format-input--label" placeholder="Pavadinimas" />
                            <input v-model="format.mime" class="format-input" placeholder="MIME, pvz. image/jpeg" />
                            <input
                                :value="extensionsInput(format)"
                                class="format-input"
                                placeholder="Plėtiniai, pvz. jpg,jpeg"
                                @input="updateExtensions(format, $event.target.value)"
                            />
                            <button type="button" class="remove-btn" @click="removeFormat(ch, 'photo_formats', index)">Šalinti</button>
                        </div>
                    </div>

                    <div v-if="config[ch].allow_video" class="formats-block">
                        <div class="formats-title-row">
                            <h4 class="formats-title">Vaizdo formatai</h4>
                            <button type="button" class="link-btn" @click="addFormat(ch, 'video_formats')">+ Pridėti</button>
                        </div>
                        <div v-for="(format, index) in config[ch].video_formats" :key="`video-${index}`" class="format-row">
                            <input v-model="format.label" class="format-input format-input--label" placeholder="Pavadinimas" />
                            <input v-model="format.mime" class="format-input" placeholder="MIME, pvz. video/mp4" />
                            <input
                                :value="extensionsInput(format)"
                                class="format-input"
                                placeholder="Plėtiniai, pvz. mp4"
                                @input="updateExtensions(format, $event.target.value)"
                            />
                            <button type="button" class="remove-btn" @click="removeFormat(ch, 'video_formats', index)">Šalinti</button>
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

.admin-files > .page-desc {
    margin-bottom: 18px;
    max-width: 920px;
}

.title {
    font-size: 18px;
    font-weight: 700;
    line-height: 1.35;
    margin: 0;
    padding: 0;
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
    align-items: start;
    width: 100%;
}

@media (max-width: 1100px) {
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

.field-lbl {
    color: #444;
    flex: 1;
    min-width: 0;
}

.inp--select {
    width: auto;
    min-width: 8.5rem;
    text-align: left;
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

.inp:focus,
.format-input:focus {
    outline: none;
    border-color: var(--color-primary, #2563eb);
    box-shadow: 0 0 0 2px rgba(37, 99, 235, 0.2);
}

.formats-block {
    padding: 14px 18px 16px;
    border-top: 1px solid #eef0f3;
}

.formats-title-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    margin-bottom: 10px;
}

.formats-title {
    margin: 0;
    font-size: 14px;
    font-weight: 700;
}

.link-btn {
    border: 0;
    background: transparent;
    color: var(--color-primary, #1946a0);
    font-size: 13px;
    cursor: pointer;
    padding: 0;
}

.format-row {
    display: grid;
    grid-template-columns: minmax(90px, 0.8fr) minmax(150px, 1.2fr) minmax(130px, 1fr) auto;
    gap: 8px;
    align-items: center;
    margin-bottom: 8px;
}

.format-input {
    min-width: 0;
    padding: 7px 9px;
    border: 1px solid #cfd4dc;
    border-radius: 6px;
    font-size: 13px;
}

.remove-btn {
    border: 1px solid #f2b8b8;
    background: #fff;
    color: #c62828;
    border-radius: 6px;
    padding: 7px 9px;
    cursor: pointer;
    font-size: 13px;
}

.actions {
    margin-top: 24px;
    display: flex;
    justify-content: flex-start;
    width: 100%;
}
</style>
