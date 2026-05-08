<script setup>
import { ICON_ARROW } from '@/constants/ui/icons'

defineProps({
    modelValue: {
        type: [String, Number],
        default: '',
    },
    options: {
        type: Array,
        required: true,
    },
})

const emit = defineEmits(['update:modelValue'])

function onChange(e) {
    emit('update:modelValue', e.target.value)
}
</script>

<template>
    <div class="public-filter-select">
        <select
            class="public-filter-select__control"
            :value="modelValue"
            @change="onChange"
        >
            <option
                v-for="opt in options"
                :key="String(opt.value)"
                :value="opt.value"
            >
                {{ opt.label }}
            </option>
        </select>
        <span class="public-filter-select__icon" aria-hidden="true">
            <img :src="ICON_ARROW" alt="" class="public-filter-select__arrow" />
        </span>
    </div>
</template>

<style scoped>
.public-filter-select {
    position: relative;
    display: block;
    min-width: 0;
    flex: 1 1 200px;
    max-width: 320px;
}

.public-filter-select__control {
    width: 100%;
    box-sizing: border-box;
    margin: 0;
    padding: 10px 44px 10px 14px;
    font-size: 0.9375rem;
    font-family: var(--font-family-base, 'Open Sans', sans-serif);
    line-height: 1.35;
    color: #1a1a1a;
    background-color: #fff;
    border: 1px solid #cfcfcf;
    border-radius: 8px;
    cursor: pointer;
    appearance: none;
    -webkit-appearance: none;
    -moz-appearance: none;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.public-filter-select__control:hover {
    border-color: #b8b8b8;
}

.public-filter-select__control:focus {
    outline: none;
    border-color: var(--color-primary, #1946a0);
    box-shadow: 0 0 0 2px rgba(25, 70, 160, 0.18);
}

.public-filter-select__icon {
    position: absolute;
    right: 12px;
    top: 50%;
    transform: translateY(-50%);
    width: 20px;
    height: 20px;
    display: flex;
    align-items: center;
    justify-content: center;
    pointer-events: none;
}

.public-filter-select__arrow {
    width: 20px;
    height: 20px;
    display: block;
    opacity: 0.7;
    object-fit: contain;
}
</style>
