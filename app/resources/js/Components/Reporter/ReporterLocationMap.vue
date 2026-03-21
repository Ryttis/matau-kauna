<script setup>
import { ref, watch, onMounted, onUnmounted } from 'vue'
import { KAUNAS_CENTER } from '@/composables/leafletKaunas'
import { useKaunasMapLocationPicker } from '@/composables/useKaunasMapLocationPicker'
import { ICON_SEARCH } from '@/constants/icons'

const props = defineProps({
    initialLat: { type: Number, default: () => KAUNAS_CENTER[0] },
    initialLng: { type: Number, default: () => KAUNAS_CENTER[1] },
})

const emit = defineEmits(['update:address', 'update:lat', 'update:lng'])

const mapContainer = ref(null)
const addressInput = ref('')
const suggestions = ref([])
const showSuggestions = ref(false)
const loadingSuggestions = ref(false)
const addressDropdownRef = ref(null)
let suggestDebounceTimer = null
let lastSuggestQuery = ''

const { lat, lng, address, isGeocoding, searchAddress, applySuggestionSelection, fetchAddressSuggestions, centerMap } =
    useKaunasMapLocationPicker(mapContainer, { initialLat: props.initialLat, initialLng: props.initialLng })

watch(address, (v) => { addressInput.value = v ?? '' }, { immediate: true })
watch(addressInput, (v) => emit('update:address', v ?? ''), { immediate: true })
watch(lat, (v) => emit('update:lat', v ?? null), { immediate: true })
watch(lng, (v) => emit('update:lng', v ?? null), { immediate: true })

watch(addressInput, (query) => {
    if (suggestDebounceTimer) clearTimeout(suggestDebounceTimer)
    const q = (query || '').trim()
    if (q.length < 2) {
        suggestions.value = []
        showSuggestions.value = false
        return
    }
    suggestDebounceTimer = setTimeout(async () => {
        lastSuggestQuery = q
        loadingSuggestions.value = true
        showSuggestions.value = true
        try {
            const list = await fetchAddressSuggestions(q)
            if (lastSuggestQuery === q) {
                suggestions.value = list
            }
        } catch (_) {
            if (lastSuggestQuery === q) suggestions.value = []
        } finally {
            loadingSuggestions.value = false
        }
    }, 400)
})

function onAddressSubmit() {
    const q = (addressInput.value || '').trim()
    if (q) searchAddress(q)
    showSuggestions.value = false
}

function onSelectSuggestion(item) {
    applySuggestionSelection(item)
    suggestions.value = []
    showSuggestions.value = false
}

function closeSuggestions() {
    showSuggestions.value = false
}

function onKeydown(ev) {
    if (ev.key === 'Escape') closeSuggestions()
}

onMounted(() => {
    document.addEventListener('pointerdown', handlePointerOutside, true)
})
onUnmounted(() => {
    document.removeEventListener('pointerdown', handlePointerOutside, true)
    if (suggestDebounceTimer) clearTimeout(suggestDebounceTimer)
})

function handlePointerOutside(e) {
    if (!showSuggestions.value || !addressDropdownRef.value) {
        return
    }
    if (!addressDropdownRef.value.contains(e.target)) {
        closeSuggestions()
    }
}

function onSuggestionsListPointerDownCapture() {}

function onCenterClick() {
    centerMap()
}
</script>

<template>
    <div class="reporter-location-map">
        <div class="reporter-location-map-wrap">
            <div ref="mapContainer" class="reporter-location-map-container" />
            <button
                type="button"
                class="reporter-location-map-center-btn"
                aria-label="Centruoti žemėlapį"
                @click="onCenterClick"
            >
                <span class="reporter-location-map-center-icon" />
            </button>
        </div>
        <div ref="addressDropdownRef" class="reporter-location-address">
            <label class="reporter-report-label">Adresas (įveskite arba pasirinkite žemėlapyje)</label>
            <div class="reporter-location-address-row reporter-location-address-row--with-dropdown">
                <input
                    v-model="addressInput"
                    type="text"
                    class="reporter-location-input"
                    placeholder="Pradėkite rašyti gatvę ar adresą (Kaunas)"
                    :disabled="isGeocoding"
                    autocomplete="off"
                    @keydown.enter.prevent="onAddressSubmit"
                    @keydown="onKeydown"
                />
                <button
                    type="button"
                    class="reporter-location-search-btn"
                    :disabled="isGeocoding"
                    aria-label="Rasti"
                    @click="onAddressSubmit"
                >
                    <img v-if="!isGeocoding" :src="ICON_SEARCH" alt="" class="reporter-location-search-icon" />
                    <span v-else class="reporter-location-search-loading">...</span>
                </button>
            </div>
            <div
                v-if="showSuggestions"
                class="reporter-location-suggestions"
                @pointerdown.capture.prevent="onSuggestionsListPointerDownCapture"
            >
                <template v-if="loadingSuggestions">
                    <div class="reporter-location-suggestions-item reporter-location-suggestions-item--muted">Kraunama...</div>
                </template>
                <template v-else-if="suggestions.length">
                    <button
                        v-for="(item, idx) in suggestions"
                        :key="idx"
                        type="button"
                        class="reporter-location-suggestions-item"
                        @click="onSelectSuggestion(item)"
                    >
                        {{ item.display_name }}
                    </button>
                </template>
                <template v-else>
                    <div class="reporter-location-suggestions-item reporter-location-suggestions-item--muted">Nerasta Kauno adresų</div>
                </template>
            </div>
        </div>
    </div>
</template>

<style scoped>
.reporter-location-map {
    display: flex;
    flex-direction: column;
    gap: 20px;
}

.reporter-location-map-wrap {
    position: relative;
    width: 100%;
    min-height: 280px;
    background: #e8e8e8;
    border-radius: 8px;
    overflow: hidden;
}

.reporter-location-map-container {
    width: 100%;
    height: 280px;
}

.reporter-location-map-center-btn {
    position: absolute;
    left: 12px;
    bottom: 12px;
    width: 36px;
    height: 36px;
    padding: 0;
    border: none;
    border-radius: 6px;
    background: #fff;
    box-shadow: 0 1px 4px rgba(0,0,0,0.2);
    cursor: pointer;
}

.reporter-location-map-center-btn:hover {
    background: #f5f5f5;
}

.reporter-location-map-center-icon {
    display: block;
    width: 20px;
    height: 20px;
    margin: 0 auto;
    background: radial-gradient(circle, #333 30%, transparent 30%);
    border: 2px solid #333;
    border-radius: 50%;
    box-sizing: border-box;
}

.reporter-location-address {
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.reporter-report-label {
    font-size: 16px;
    font-weight: 600;
    color: #333;
}

.reporter-location-address-row {
    display: flex;
    gap: 8px;
}

.reporter-location-address-row--with-dropdown,
.reporter-location-address {
    position: relative;
}

.reporter-location-suggestions {
    position: absolute;
    left: 0;
    right: 0;
    top: 100%;
    margin-top: 4px;
    background: #fff;
    border: 1px solid #e0e0e0;
    border-radius: 6px;
    box-shadow: 0 4px 16px rgba(0, 0, 0, 0.18);
    max-height: 320px;
    overflow-y: auto;
    z-index: 1000;
}

.reporter-location-suggestions-item {
    display: block;
    width: 100%;
    padding: 10px 12px;
    border: none;
    background: none;
    text-align: left;
    font-size: 14px;
    color: #333;
    cursor: pointer;
    font-family: inherit;
    line-height: 1.35;
}

.reporter-location-suggestions-item:hover {
    background: #f0f4fc;
}

.reporter-location-suggestions-item--muted {
    color: #666;
    cursor: default;
}

.reporter-location-suggestions-item--muted:hover {
    background: transparent;
}

.reporter-location-input {
    flex: 1;
    height: 40px;
    padding: 0 12px;
    border: 1px solid #e0e0e0;
    border-radius: 5px;
    font-size: 16px;
    font-family: inherit;
    color: #333;
    background: #fff;
    box-sizing: border-box;
}

.reporter-location-input:focus {
    outline: none;
    border-color: #1946a0;
}

.reporter-location-search-btn {
    flex-shrink: 0;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    height: 40px;
    min-width: 40px;
    padding: 0 12px;
    border: 1px solid #1946a0;
    border-radius: 15px;
    background: #fff;
    color: #1946a0;
    font-size: 14px;
    font-weight: 600;
    cursor: pointer;
}

.reporter-location-search-btn:hover:not(:disabled) {
    background: #f0f4fc;
}

.reporter-location-search-btn:disabled {
    opacity: 0.7;
    cursor: not-allowed;
}

.reporter-location-search-icon {
    display: block;
    width: 20px;
    height: 20px;
    object-fit: contain;
}

.reporter-location-search-loading {
    font-size: 14px;
    font-weight: 600;
}
</style>
