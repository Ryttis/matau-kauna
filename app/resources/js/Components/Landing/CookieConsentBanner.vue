<script setup>
import { ref, computed, onMounted } from 'vue'
import { Link } from '@inertiajs/vue3'

const STORAGE_KEY = 'cookie_consent'

const defaultCategories = {
    necessary: true,
    analytics: false,
    marketing: false,
}

function readStoredConsent() {
    try {
        const raw = typeof localStorage !== 'undefined' ? localStorage.getItem(STORAGE_KEY) : null
        if (!raw) return null
        const data = JSON.parse(raw)
        if (data && (data.level === 'necessary' || data.level === 'custom' || data.level === 'all')) {
            return data
        }
        return null
    } catch {
        return null
    }
}

const consent = ref(readStoredConsent())
const showDetails = ref(false)
const customChoices = ref({ ...defaultCategories })

const isVisible = computed(() => consent.value === null)

onMounted(() => {
    const stored = readStoredConsent()
    if (stored) {
        consent.value = stored
        if (stored.categories) customChoices.value = { ...defaultCategories, ...stored.categories }
    }
})

function save(level, categories = null) {
    const data = {
        level,
        categories: categories ?? (level === 'custom' ? customChoices.value : (level === 'all' ? { necessary: true, analytics: true, marketing: true } : { necessary: true, analytics: false, marketing: false })),
        ts: Date.now(),
    }
    try {
        localStorage.setItem(STORAGE_KEY, JSON.stringify(data))
        consent.value = data
        showDetails.value = false
    } catch (e) {
        console.warn('Cookie consent save failed', e)
    }
}

function acceptNecessaryOnly() {
    save('necessary')
}

function acceptAll() {
    save('all')
}

function toggleCustom(category) {
    if (category === 'necessary') return
    customChoices.value = { ...customChoices.value, [category]: !customChoices.value[category] }
}

function saveCustom() {
    save('custom', { ...customChoices.value })
}
</script>

<template>
    <div v-if="isVisible" class="cookie-consent">
        <div class="cookie-consent-inner">
            <p class="cookie-consent-text">
                Naudodamiesi svetaine sutinkate su slapukų naudojimu.
                <Link :href="route('cookies')" class="cookie-consent-link">Slapukų informacija</Link>
            </p>
            <div class="cookie-consent-actions">
                <button type="button" class="cookie-consent-btn cookie-consent-btn--secondary" @click="acceptNecessaryOnly">
                    Tik būtini
                </button>
                <button type="button" class="cookie-consent-btn cookie-consent-btn--secondary" @click="showDetails = !showDetails">
                    {{ showDetails ? 'Slėpti' : 'Pasirinkti' }}
                </button>
                <button type="button" class="cookie-consent-btn cookie-consent-btn--primary" @click="acceptAll">
                    Priimti visus
                </button>
            </div>
        </div>
        <div v-if="showDetails" class="cookie-consent-details">
            <p class="cookie-consent-details-title">Pasirinkite slapukų kategorijas:</p>
            <label class="cookie-consent-checkbox">
                <input type="checkbox" checked disabled />
                <span>Būtini (reikalingi svetainės veikimui)</span>
            </label>
            <label class="cookie-consent-checkbox">
                <input type="checkbox" :checked="customChoices.analytics" @change="toggleCustom('analytics')" />
                <span>Analitika</span>
            </label>
            <label class="cookie-consent-checkbox">
                <input type="checkbox" :checked="customChoices.marketing" @change="toggleCustom('marketing')" />
                <span>Rinkodara</span>
            </label>
            <button type="button" class="cookie-consent-btn cookie-consent-btn--primary cookie-consent-save" @click="saveCustom">
                Išsaugoti pasirinkimą
            </button>
        </div>
    </div>
</template>

<style scoped>
.cookie-consent {
    position: fixed;
    bottom: 0;
    left: 0;
    right: 0;
    background: #fff;
    box-shadow: 0 -2px 12px rgba(0, 0, 0, 0.12);
    z-index: 9999;
    font-family: 'Open Sans', sans-serif;
}

.cookie-consent-inner {
    max-width: 1200px;
    margin: 0 auto;
    padding: 1rem 1.5rem;
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
}

.cookie-consent-text {
    margin: 0;
    font-size: 0.9375rem;
    color: #333;
    flex: 1;
    min-width: 200px;
}

.cookie-consent-link {
    color: #1946A0;
    text-decoration: underline;
}

.cookie-consent-link:hover {
    color: #0d2d66;
}

.cookie-consent-actions {
    display: flex;
    flex-wrap: wrap;
    gap: 0.5rem;
}

.cookie-consent-btn {
    padding: 0.5rem 1rem;
    border-radius: 6px;
    font-size: 0.875rem;
    font-weight: 500;
    cursor: pointer;
    border: 1px solid #ccc;
    background: #fff;
    color: #333;
}

.cookie-consent-btn--primary {
    background: #1946A0;
    color: #fff;
    border-color: #1946A0;
}

.cookie-consent-btn--primary:hover {
    background: #0d2d66;
    border-color: #0d2d66;
}

.cookie-consent-btn--secondary:hover {
    background: #f0f0f0;
}

.cookie-consent-details {
    border-top: 1px solid #eee;
    padding: 1rem 1.5rem;
    max-width: 1200px;
    margin: 0 auto;
}

.cookie-consent-details-title {
    margin: 0 0 0.75rem 0;
    font-size: 0.9375rem;
    font-weight: 600;
    color: #333;
}

.cookie-consent-checkbox {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    margin-bottom: 0.5rem;
    font-size: 0.875rem;
    color: #333;
    cursor: pointer;
}

.cookie-consent-checkbox input {
    width: 18px;
    height: 18px;
}

.cookie-consent-save {
    margin-top: 0.75rem;
}
</style>
