<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import {
    PHONE_INTL_COUNTRIES,
    buildE164,
    canonicalPhoneDigits,
    digitsOnly,
    parsePhoneForInternationalInput,
} from '@/constants/phoneInternational'

const props = defineProps({
    modelValue: { type: String, default: '' },
    error: { type: String, default: '' },
    id: { type: String, required: true },
    placeholder: { type: String, default: 'Įveskite numerį be šalies kodo' },
})

const emit = defineEmits(['update:modelValue'])

const parsed0 = parsePhoneForInternationalInput(props.modelValue)
const open = ref(false)
const countrySearch = ref('')
const searchInputRef = ref(null)
let removeEscListener = null
const selectedIso = ref(parsed0.iso)
const national = ref(parsed0.national ?? '')

const filteredCountries = computed(() => {
    const q = countrySearch.value.trim().toLowerCase()
    if (!q) {
        return PHONE_INTL_COUNTRIES
    }
    const qDigits = digitsOnly(q)
    return PHONE_INTL_COUNTRIES.filter((c) => {
        if (c.name.toLowerCase().includes(q)) {
            return true
        }
        if (c.iso.toLowerCase().includes(q)) {
            return true
        }
        if (qDigits.length >= 2 && (c.dial.startsWith(qDigits) || qDigits.startsWith(c.dial))) {
            return true
        }
        return false
    })
})

const selectedCountry = computed(
    () => PHONE_INTL_COUNTRIES.find((c) => c.iso === selectedIso.value) ?? PHONE_INTL_COUNTRIES[0],
)

function flagClasses(iso) {
    return ['fi', `fi-${iso.toLowerCase()}`]
}

function currentBuilt() {
    const c = selectedCountry.value
    const n = digitsOnly(national.value)
    return n ? buildE164(c.dial, n) : ''
}

function applyFromModel(v) {
    const p = parsePhoneForInternationalInput(v)
    selectedIso.value = p.iso
    national.value = p.national ?? ''
}

function syncToParent() {
    const b = currentBuilt()
    if (canonicalPhoneDigits(b) === canonicalPhoneDigits(props.modelValue || '')) {
        return
    }
    emit('update:modelValue', b)
}

watch(
    () => props.modelValue,
    (v) => {
        if (canonicalPhoneDigits(currentBuilt()) === canonicalPhoneDigits(v || '')) {
            return
        }
        applyFromModel(v)
    },
)

function onNationalInput(e) {
    const raw = e.target.value
    const max = selectedCountry.value.natMax
    national.value = digitsOnly(raw).slice(0, max)
    syncToParent()
}

function pickCountry(c) {
    selectedIso.value = c.iso
    open.value = false
    syncToParent()
}

function toggleOpen() {
    open.value = !open.value
}

watch(open, (isOpen) => {
    if (removeEscListener) {
        removeEscListener()
        removeEscListener = null
    }
    if (isOpen) {
        countrySearch.value = ''
        nextTick(() => {
            searchInputRef.value?.focus()
        })
        const onEsc = (e) => {
            if (e.key === 'Escape') {
                open.value = false
            }
        }
        document.addEventListener('keydown', onEsc, true)
        removeEscListener = () => document.removeEventListener('keydown', onEsc, true)
    }
})

function onDocPointerDown(e) {
    const root = document.getElementById(`${props.id}-phone-root`)
    if (root && !root.contains(e.target)) {
        open.value = false
    }
}

onMounted(() => {
    document.addEventListener('pointerdown', onDocPointerDown, true)
})

onBeforeUnmount(() => {
    removeEscListener?.()
    document.removeEventListener('pointerdown', onDocPointerDown, true)
})
</script>

<template>
    <div :id="`${id}-phone-root`" class="form-input-wrp app-phone-intl">
        <div class="app-phone-intl-row" :class="{ 'form-input--error': error }">
            <div class="app-phone-intl-prefix">
                <button
                    type="button"
                    class="app-phone-intl-trigger"
                    :id="`${id}-country`"
                    :aria-expanded="open"
                    aria-haspopup="listbox"
                    @click="toggleOpen"
                >
                    <span
                        class="app-phone-intl-trigger-flag"
                        :class="flagClasses(selectedCountry.iso)"
                        aria-hidden="true"
                    />
                    <span class="app-phone-intl-trigger-dial">+{{ selectedCountry.dial }}</span>
                    <span class="app-phone-intl-caret" aria-hidden="true" />
                </button>
                <div v-show="open" class="app-phone-intl-panel">
                    <div class="app-phone-intl-search-wrap" @pointerdown.stop>
                        <input
                            ref="searchInputRef"
                            v-model="countrySearch"
                            type="search"
                            class="app-phone-intl-search"
                            :id="`${id}-country-search`"
                            placeholder="Ieškoti šalies ar kodo…"
                            autocomplete="off"
                            autocorrect="off"
                            spellcheck="false"
                            aria-label="Ieškoti šalies"
                        />
                    </div>
                    <ul class="app-phone-intl-list">
                        <li v-if="!filteredCountries.length" class="app-phone-intl-empty" role="presentation">
                            Nerasta
                        </li>
                        <li v-for="c in filteredCountries" :key="c.iso" role="none">
                            <button
                                type="button"
                                class="app-phone-intl-option"
                                :class="{ 'app-phone-intl-option--active': c.iso === selectedIso }"
                                @click="pickCountry(c)"
                            >
                                <span class="app-phone-intl-option-flag" :class="flagClasses(c.iso)" aria-hidden="true" />
                                <span class="app-phone-intl-option-dial">+{{ c.dial }}</span>
                                <span class="app-phone-intl-option-name">{{ c.name }}</span>
                            </button>
                        </li>
                    </ul>
                </div>
            </div>
            <input
                :id="`${id}-national`"
                class="form-input app-phone-intl-input"
                type="tel"
                inputmode="numeric"
                autocomplete="tel-national"
                :placeholder="placeholder"
                :value="national"
                @input="onNationalInput"
            />
        </div>
        <p v-if="error" class="form-input-error">{{ error }}</p>
    </div>
</template>

<style scoped>
.app-phone-intl-row {
    display: flex;
    gap: 8px;
    align-items: stretch;
    margin-bottom: 4px;
    border: 1px solid #d9d9d9;
    border-radius: 5px;
    padding: 2px;
    background: #fff;
}

.app-phone-intl-row.form-input--error {
    border-color: #c00;
}

.app-phone-intl-row:focus-within:not(.form-input--error) {
    border-color: #1946a0;
    box-shadow: 0 0 0 1px rgba(25, 70, 160, 0.25);
}

.app-phone-intl-prefix {
    position: relative;
    flex: 0 0 auto;
}

.app-phone-intl-trigger {
    display: flex;
    align-items: center;
    gap: 6px;
    height: 100%;
    min-height: 42px;
    padding: 0 10px;
    border: none;
    border-radius: 4px;
    background: #f3f4f6;
    cursor: pointer;
    font-size: 18px;
    white-space: nowrap;
    color: #1a1a1a;
}

.app-phone-intl-trigger:hover {
    background: #e5e7eb;
}

.app-phone-intl-trigger-flag {
    flex-shrink: 0;
    border-radius: 2px;
    box-shadow: 0 0 0 1px rgba(0, 0, 0, 0.08);
}

.app-phone-intl-trigger-dial {
    font-weight: 500;
}

.app-phone-intl-caret {
    width: 0;
    height: 0;
    border-left: 5px solid transparent;
    border-right: 5px solid transparent;
    border-top: 6px solid #555;
    margin-left: 2px;
}

.app-phone-intl-panel {
    position: absolute;
    left: 0;
    top: calc(100% + 4px);
    z-index: 50;
    min-width: 280px;
    max-width: min(100vw - 24px, 320px);
    background: #fff;
    border: 1px solid #d9d9d9;
    border-radius: 8px;
    box-shadow: 0 8px 24px rgba(0, 0, 0, 0.12);
    overflow: hidden;
    display: flex;
    flex-direction: column;
}

.app-phone-intl-search-wrap {
    padding: 8px;
    border-bottom: 1px solid #e5e7eb;
    flex-shrink: 0;
}

.app-phone-intl-search {
    width: 100%;
    box-sizing: border-box;
    padding: 8px 10px;
    border: 1px solid #d1d5db;
    border-radius: 6px;
    font-size: 14px;
    font-family: inherit;
}

.app-phone-intl-search:focus {
    outline: none;
    border-color: #1946a0;
    box-shadow: 0 0 0 1px rgba(25, 70, 160, 0.2);
}

.app-phone-intl-list {
    margin: 0;
    padding: 4px 0;
    list-style: none;
    max-height: 200px;
    overflow-y: auto;
    flex: 1;
    min-height: 0;
}

.app-phone-intl-empty {
    padding: 12px 14px;
    font-size: 14px;
    color: #6b7280;
}

.app-phone-intl-option {
    display: flex;
    align-items: center;
    gap: 10px;
    width: 100%;
    padding: 10px 12px;
    border: none;
    background: transparent;
    cursor: pointer;
    font-size: 15px;
    text-align: left;
}

.app-phone-intl-option:hover {
    background: #f3f4f6;
}

.app-phone-intl-option--active {
    background: #eef2ff;
}

.app-phone-intl-option-flag {
    flex: 0 0 auto;
    border-radius: 2px;
    box-shadow: 0 0 0 1px rgba(0, 0, 0, 0.06);
}

.app-phone-intl-option-dial {
    flex: 0 0 auto;
    font-weight: 600;
    color: #1946a0;
    min-width: 52px;
}

.app-phone-intl-option-name {
    flex: 1;
    color: #333;
}

.app-phone-intl-input {
    flex: 1;
    min-width: 0;
    margin-bottom: 0;
    border: none;
    border-radius: 4px;
    box-shadow: none;
    font-size: 18px;
    padding: 10px 8px;
}

.app-phone-intl-input:focus {
    outline: none;
}
</style>
