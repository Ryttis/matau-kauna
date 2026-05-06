<script setup>
import { computed } from 'vue'

const props = defineProps({
    modelValue: {
        type: Boolean,
        default: false
    },
    value: {
        type: [String, Number, Boolean],
        default: null
    },
    disabled: {
        type: Boolean,
        default: false
    }
})

const emit = defineEmits(['update:modelValue', 'change'])

const isChecked = computed({
    get: () => props.modelValue,
    set: (val) => {
        emit('update:modelValue', val)
    }
})

const handleChange = (event) => {
    const newValue = event.target.checked
    isChecked.value = newValue
    emit('change', newValue)
}
</script>

<template>
    <input
        type="checkbox"
        :checked="isChecked"
        :value="value"
        :disabled="disabled"
        @change="handleChange"
        class="app-checkbox"
    />
</template>

<style scoped>
.app-checkbox {
    width: 18px;
    height: 18px;
    cursor: pointer;
    border: 1px solid #1946A0;
    border-radius: 2px;
    appearance: none;
    -webkit-appearance: none;
    -moz-appearance: none;
    background-color: #ffffff;
    position: relative;
    flex-shrink: 0;
    margin: 0;
}

.app-checkbox:checked {
    background-color: #1946A0;
    border-color: #1946A0;
}

.app-checkbox:checked::after {
    content: '✓';
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    color: #ffffff;
    font-size: 12px;
    font-weight: bold;
    line-height: 1;
}

.app-checkbox:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}

.app-checkbox:focus {
    outline: none;
}

.app-checkbox:focus-visible {
    box-shadow: 0 0 0 2px rgba(25, 70, 160, 0.35);
}
</style>
