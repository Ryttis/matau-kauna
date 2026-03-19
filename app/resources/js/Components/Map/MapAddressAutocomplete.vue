<script setup>
import { ref, watch, computed } from 'vue'
import axios from 'axios'

const props = defineProps({
    placeholder: { type: String, default: 'Suveskite adresą' },
    variant: { type: String, default: 'default' },
    searchMode: { type: String, default: 'all' },
    resetToken: { type: Number, default: 0 },
})

const emit = defineEmits(['select', 'clear'])

const emptyMessage = computed(() => {
    if (props.searchMode === 'number') {
        return 'Nerasta problemų pagal šį numerį'
    }
    if (props.searchMode === 'address') {
        return 'Nerasta problemų pagal šį adresą'
    }
    return 'Nerasta problemų'
})

const inputValue = ref('')
const results = ref([])
const loading = ref(false)
const open = ref(false)
const wrapperRef = ref(null)

let debounceTimer = null
const DEBOUNCE_MS = 300

function fetchResults() {
    const q = inputValue.value.trim()
    if (q === '') {
        results.value = []
        open.value = false
        return
    }
    open.value = true
    loading.value = true
    const mode = ['number', 'address'].includes(props.searchMode) ? props.searchMode : 'all'
    axios.get(route('map.addressSearch'), { params: { q, mode } })
        .then((res) => {
            results.value = Array.isArray(res.data) ? res.data : []
        })
        .catch(() => {
            results.value = []
        })
        .finally(() => { loading.value = false })
}

function onInput() {
    if (debounceTimer) clearTimeout(debounceTimer)
    if (inputValue.value.trim() === '') {
        results.value = []
        open.value = false
        emit('clear')
        return
    }
    debounceTimer = setTimeout(fetchResults, DEBOUNCE_MS)
}

function onFocus() {
    if (inputValue.value.trim()) fetchResults()
}

function select(problem) {
    const addr = problem.address || ''
    const num = problem.internal_number || ''
    inputValue.value = addr ? `${num} — ${addr}`.trim() : num
    open.value = false
    results.value = []
    emit('select', problem)
}

function onBlur() {
    setTimeout(() => { open.value = false }, 150)
}

function onClickOutside(event) {
    if (wrapperRef.value && !wrapperRef.value.contains(event.target)) {
        open.value = false
    }
}

watch(open, (isOpen) => {
    if (isOpen) {
        document.addEventListener('click', onClickOutside)
    } else {
        document.removeEventListener('click', onClickOutside)
    }
})

watch(
    () => props.resetToken,
    () => {
        inputValue.value = ''
        results.value = []
        open.value = false
    },
)
</script>

<template>
    <div
        ref="wrapperRef"
        class="map-address-autocomplete"
        :class="{
            'map-address-autocomplete--pill': variant === 'pill',
            'map-address-autocomplete--pill-bordered': variant === 'pill-bordered',
        }"
    >
        <input
            v-model="inputValue"
            type="text"
            class="map-address-autocomplete-input"
            :placeholder="placeholder"
            autocomplete="off"
            @input="onInput"
            @focus="onFocus"
            @blur="onBlur"
        />
        <div v-if="open" class="map-address-autocomplete-dropdown">
            <div v-if="loading" class="map-address-autocomplete-item map-address-autocomplete-item--muted">
                Kraunama…
            </div>
            <template v-else>
                <button
                    v-for="p in results"
                    :key="p.id"
                    type="button"
                    class="map-address-autocomplete-item"
                    @mousedown.prevent="select(p)"
                >
                    <span class="map-address-autocomplete-number">{{ p.internal_number }}</span>
                    <span v-if="p.address" class="map-address-autocomplete-address">{{ p.address }}</span>
                </button>
                <div v-if="results.length === 0" class="map-address-autocomplete-item map-address-autocomplete-item--muted">
                    {{ emptyMessage }}
                </div>
            </template>
        </div>
    </div>
</template>

<style scoped>
.map-address-autocomplete {
    position: relative;
    width: 100%;
}

.map-address-autocomplete-input {
    width: 100%;
    height: var(--control-height, 45px);
    border: none;
    border-radius: 5px;
    background-color: #fff;
    color: #333;
    padding: 0 15px;
    font-size: 16px;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
    box-sizing: border-box;
}

.map-address-autocomplete--pill .map-address-autocomplete-input {
    height: 40px;
    border-radius: 20px;
    background-color: #f6f7f9;
    box-shadow: none;
}

.map-address-autocomplete--pill-bordered .map-address-autocomplete-input {
    height: 48px;
    border-radius: 24px;
    background-color: #fff;
    border: 1px solid #d8d8d8;
    box-shadow: none;
}

.map-address-autocomplete--pill-bordered .map-address-autocomplete-input::placeholder {
    color: #9a9a9a;
}

.map-address-autocomplete-dropdown {
    position: absolute;
    top: 100%;
    left: 0;
    right: 0;
    margin-top: 4px;
    background: #fff;
    border: 1px solid #e0e0e0;
    border-radius: 5px;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
    max-height: 280px;
    overflow-y: auto;
    z-index: 1100;
}

.map-address-autocomplete-item {
    display: flex;
    flex-direction: column;
    align-items: flex-start;
    gap: 2px;
    width: 100%;
    padding: 10px 15px;
    text-align: left;
    border: none;
    background: none;
    cursor: pointer;
    font-size: 14px;
    color: #333;
}

.map-address-autocomplete-item:hover {
    background: #f6f7f9;
}

.map-address-autocomplete-item--muted {
    color: #666;
    cursor: default;
}

.map-address-autocomplete-number {
    font-weight: 600;
    font-size: 14px;
    color: #333;
}

.map-address-autocomplete-address {
    font-size: 12px;
    color: #666;
    font-weight: 400;
}
</style>
