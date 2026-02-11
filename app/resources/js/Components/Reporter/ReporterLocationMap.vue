<script setup>
import { ref, watch } from 'vue'
import { KAUNAS_CENTER } from '@/composables/leafletKaunas'
import { useKaunasMapLocationPicker } from '@/composables/useKaunasMapLocationPicker'

const props = defineProps({
    initialLat: { type: Number, default: () => KAUNAS_CENTER[0] },
    initialLng: { type: Number, default: () => KAUNAS_CENTER[1] },
})

const emit = defineEmits(['update:address', 'update:lat', 'update:lng'])

const mapContainer = ref(null)
const addressInput = ref('')

const { lat, lng, address, isGeocoding, searchAddress, centerMap } = useKaunasMapLocationPicker(
    mapContainer,
    { initialLat: props.initialLat, initialLng: props.initialLng }
)

watch(address, (v) => { addressInput.value = v ?? '' }, { immediate: true })
watch(addressInput, (v) => emit('update:address', v ?? ''), { immediate: true })
watch(lat, (v) => emit('update:lat', v ?? null), { immediate: true })
watch(lng, (v) => emit('update:lng', v ?? null), { immediate: true })

function onAddressSubmit() {
    const q = (addressInput.value || '').trim()
    if (q) searchAddress(q)
}

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
        <div class="reporter-location-address">
            <label class="reporter-report-label">Pagal adresą</label>
            <div class="reporter-location-address-row">
                <input
                    v-model="addressInput"
                    type="text"
                    class="reporter-location-input"
                    placeholder="Adresas"
                    :disabled="isGeocoding"
                    @keydown.enter.prevent="onAddressSubmit"
                />
                <button
                    type="button"
                    class="reporter-location-search-btn"
                    :disabled="isGeocoding"
                    @click="onAddressSubmit"
                >
                    {{ isGeocoding ? '...' : 'Rasti' }}
                </button>
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
    height: 40px;
    padding: 0 16px;
    border: 1px solid #1946a0;
    border-radius: 5px;
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
</style>
