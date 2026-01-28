<script setup>
import { computed } from 'vue'

const props = defineProps({
    modelValue: {
        type: [String, Number],
        default: ''
    },
    label: {
        type: String,
        default: ''
    },
    placeholder: {
        type: String,
        default: 'Pasirinkti'
    }
})

const emit = defineEmits(['update:modelValue'])

const value = computed({
    get: () => props.modelValue,
    set: (val) => emit('update:modelValue', val)
})
</script>

<template>
    <div class="app-select-group">
        <label v-if="label" class="app-select-label">{{ label }}</label>

        <div class="app-select-wrapper">
            <select v-model="value" class="app-select">
                <option v-if="placeholder && (modelValue === '' || modelValue === null || modelValue === undefined)" value="">{{ placeholder }}</option>
                <slot>
                </slot>
            </select>
            <div class="app-select-icon">
                <img src="/icons/arrow-icon.png" alt="Dropdown" class="app-select-arrow" />
            </div>
        </div>
    </div>
</template>

<style scoped>
.app-select-group {
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.app-select-label {
    font-size: 16px;
    font-weight: 600;
    color: #333333;
    font-family: 'Inter', sans-serif;
    line-height: 1.5;
}

.app-select-wrapper {
    position: relative;
    width: 100%;
}

.app-select {
    width: 100%;
    height: 48px;
    padding: 0 45px 0 15px;
    border: none;
    border-radius: 5px;
    font-size: 16px;
    font-family: 'Inter', sans-serif;
    color: #ffffff;
    background-color: #1946A0;
    cursor: pointer;
    box-sizing: border-box;
    appearance: none;
    -webkit-appearance: none;
    -moz-appearance: none;
    text-align: left;
    line-height: 48px;
}

.app-select:focus {
    outline: none;
    border-color: #1946A0;
}

.app-select option {
    background-color: #ffffff;
    color: #333333;
    padding: 10px;
}

.app-select-icon {
    position: absolute;
    right: 15px;
    top: 50%;
    transform: translateY(-50%);
    display: flex;
    align-items: center;
    justify-content: center;
    pointer-events: none;
    z-index: 1;
    height: 16px;
}

.app-select-arrow {
    display: block;
    width: 16px;
    height: 16px;
    filter: brightness(0) invert(1);
    object-fit: contain;
}
</style>
