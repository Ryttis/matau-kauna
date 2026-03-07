<script setup>
const props = defineProps({
    modelValue: {
        type: Boolean,
        default: false,
    },
    disabled: {
        type: Boolean,
        default: false,
    },
})

const emit = defineEmits(['update:modelValue'])

function onChange(e) {
    if (props.disabled) return
    emit('update:modelValue', e.target.checked)
}
</script>

<template>
    <label class="app-switch" :class="{ 'app-switch--on': modelValue, 'app-switch--disabled': disabled }">
        <input
            type="checkbox"
            :checked="modelValue"
            :disabled="disabled"
            class="app-switch-input"
            @change="onChange"
        />
        <span class="app-switch-slider" />
    </label>
</template>

<style scoped>
.app-switch {
    --switch-width: 44px;
    --switch-height: 22px;
    --switch-thumb-size: 14px;
    --switch-padding: 4px;
    --switch-thumb-off: 18px;
    --switch-color: var(--color-primary);
    --switch-track-off: var(--color-gray-400);
    --switch-track-on: var(--color-white);

    position: relative;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: var(--switch-width);
    height: var(--switch-height);
}

.app-switch-input {
    opacity: 0;
    width: 0;
    height: 0;
}

.app-switch-slider {
    position: absolute;
    inset: 0;
    cursor: pointer;
    background-color: var(--switch-track-off);
    border: 2px solid transparent;
    border-radius: 22px;
    box-sizing: border-box;
    transition: background-color 0.25s ease, border-color 0.25s ease;
}

.app-switch-slider::before {
    position: absolute;
    content: '';
    width: var(--switch-thumb-size);
    height: var(--switch-thumb-size);
    left: var(--switch-padding);
    top: 50%;
    transform: translateY(-50%);
    background-color: var(--switch-color);
    border-radius: 50%;
    box-shadow: 0 1px 2px rgba(0, 0, 0, 0.15);
    transition: transform 0.25s ease;
}

.app-switch--on .app-switch-slider,
.app-switch-input:checked + .app-switch-slider {
    background-color: var(--switch-track-on) !important;
    border-color: var(--switch-color);
}

.app-switch--on .app-switch-slider::before,
.app-switch-input:checked + .app-switch-slider::before {
    transform: translateY(-50%) translateX(var(--switch-thumb-off));
}

.app-switch--disabled .app-switch-slider {
    opacity: 0.6;
    cursor: not-allowed;
}
</style>
