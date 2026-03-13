<script setup>
import { computed } from 'vue'
import { ICON_ARROW } from '@/constants/icons'

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
    },
    labelPosition: {
        type: String,
        default: 'top',
        validator: (value) => ['top', 'left'].includes(value)
    },
    variant: {
        type: String,
        default: 'blue',
        validator: (value) => ['blue', 'white'].includes(value)
    },
    rounded: {
        type: Boolean,
        default: false
    },
    disabled: {
        type: Boolean,
        default: false
    }
})

const emit = defineEmits(['update:modelValue'])

const value = computed({
    get: () => props.modelValue,
    set: (val) => emit('update:modelValue', val)
})
</script>

<template>
    <div class="app-select-group" :class="`app-select-group--${labelPosition}`">
        <label v-if="label" class="app-select-label">{{ label }}</label>

        <div class="app-select-wrapper">
            <select v-model="value" class="app-select" :class="[`app-select--${variant}`, { 'app-select--rounded': rounded }]" :disabled="disabled">
                <option v-if="placeholder && (modelValue === '' || modelValue === null || modelValue === undefined)" value="">{{ placeholder }}</option>
                <slot />
            </select>
            <div class="app-select-icon">
                <img :src="ICON_ARROW" alt="Dropdown" class="app-select-arrow" :class="`app-select-arrow--${variant}`" />
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

.app-select-group--left {
    flex-direction: row;
    align-items: center;
    gap: 12px;
}

.app-select-group--left .app-select-label {
    margin-bottom: 0;
    white-space: nowrap;
}

.app-select-group--left .app-select-wrapper {
    flex: 1;
}

.app-select-label {
    font-size: 16px;
    font-weight: 600;
    color: #333333;
    font-family: 'Inter', sans-serif;
    line-height: 1.5;
    margin-bottom: 0;
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
    border-radius: 15px;
    font-size: 16px;
    font-family: 'Inter', sans-serif;
    color: var(--color-white, #ffffff);
    background-color: var(--color-primary);
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
    border-color: var(--color-primary);
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

.app-select-arrow--white {
    filter: none;
}

.app-select--white {
    background-color: #ffffff;
    color: #333333;
    border: 1px solid #e0e0e0;
    padding: 10px 40px 10px 15px;
    height: 40px;
    line-height: 20px;
}

.app-select--white:focus {
    outline: none;
    border-color: var(--color-primary);
}

.app-select--white option {
    background-color: #ffffff;
    color: #333333;
}

.app-select:disabled {
    opacity: 0.6;
    cursor: not-allowed;
}

.app-select--rounded {
    border-radius: 15px;
}
</style>
