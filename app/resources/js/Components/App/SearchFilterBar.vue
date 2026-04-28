<script setup>
import { computed } from 'vue'
import { ICON_FILTER, ICON_SEARCH } from '@/constants/ui/icons'

const props = defineProps({
    modelValue: { type: String, default: '' },
    placeholder: { type: String, default: 'Ieškoti' },
    filterAriaLabel: { type: String, default: 'Filtruoti' },
})

const emit = defineEmits(['update:modelValue', 'input', 'filter-click'])

const hasValue = computed(() => (props.modelValue || '').trim().length > 0)

function onInput(event) {
    emit('update:modelValue', event.target.value)
    emit('input', event.target.value)
}

function clearSearch() {
    emit('update:modelValue', '')
    emit('input', '')
}

function openFilter() {
    emit('filter-click')
}
</script>

<template>
    <div class="search-filter-bar">
        <img :src="ICON_SEARCH" class="search-filter-bar__search-icon" alt="" aria-hidden="true" />
        <input
            :value="modelValue"
            type="search"
            class="search-filter-bar__input"
            :placeholder="placeholder"
            autocomplete="off"
            enterkeyhint="search"
            @input="onInput"
        />
        <button
            v-show="hasValue"
            type="button"
            class="search-filter-bar__clear"
            aria-label="Išvalyti paiešką"
            @click="clearSearch"
        >
            ×
        </button>
        <button
            type="button"
            class="search-filter-bar__filter-btn"
            :aria-label="filterAriaLabel"
            @click="openFilter"
        >
            <img :src="ICON_FILTER" class="search-filter-bar__filter-icon" alt="" />
        </button>
    </div>
</template>

<style>
.search-filter-bar {
    display: flex;
    align-items: center;
    gap: 10px;
    width: 100%;
    min-width: 0;
    position: relative;
    max-width: 400px;
}

.search-filter-bar__search-icon {
    position: absolute;
    left: 14px;
    width: 20px;
    height: 20px;
    pointer-events: none;
    object-fit: contain;
}

.search-filter-bar__input {
    flex: 1;
    min-width: 0;
    width: 100%;
    height: 44px;
    border: none;
    border-radius: 15px;
    background-color: #f6f7f9;
    color: #333;
    padding: 0 36px 0 44px;
    font-size: 16px;
    box-sizing: border-box;
}

.search-filter-bar__input::placeholder {
    color: #999;
}

.search-filter-bar__input::-webkit-search-cancel-button {
    display: none;
}

.search-filter-bar__clear {
    position: absolute;
    right: 56px;
    top: 50%;
    transform: translateY(-50%);
    width: 28px;
    height: 28px;
    padding: 0;
    border: none;
    border-radius: 50%;
    background: transparent;
    color: #666;
    font-size: 22px;
    line-height: 1;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
}

.search-filter-bar__clear:hover {
    background: rgba(0, 0, 0, 0.06);
    color: #333;
}

.search-filter-bar__filter-btn {
    flex-shrink: 0;
    width: 44px;
    height: 44px;
    border: none;
    border-radius: 15px;
    background: transparent;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    padding: 0;
    box-sizing: border-box;
}

.search-filter-bar__filter-btn:hover {
    background: #f0f0f0;
}

.search-filter-bar__filter-icon {
    width: 24px;
    height: 24px;
    display: block;
    object-fit: contain;
}
</style>
