<script setup>
const props = defineProps({
    text: {
        type: String,
        required: true
    },
    variant: {
        type: String,
        default: 'primary',
        validator: (v) => ['primary', 'secondary', 'light', 'outline-light', 'link'].includes(v)
    },
    disabled: {
        type: Boolean,
        default: false
    },
    asButton: {
        type: Boolean,
        default: false
    }
})

const emit = defineEmits(['click'])

const handleClick = (event) => {
    if (props.asButton) {
        event.preventDefault()
        event.stopPropagation()
        emit('click', event)
    }
}

const buttonType = props.asButton ? 'button' : 'submit'
</script>

<template>
    <button
        class="app-button"
        :class="[`app-button--${variant}`, { 'app-button--disabled': disabled, 'app-button--fixed': asButton }]"
        :type="buttonType"
        :disabled="disabled"
        @click="handleClick"
    >
        {{ text }}
    </button>
</template>

<style scoped>
.app-button {
    width: 100%;
    display: block;
    border: none;
    padding: 10px;
    border-radius: 5px;
    margin-bottom: 10px;
    font-size: 18px;
    font-family: 'Inter';
    cursor: pointer;
    transition: background-color 0.2s ease, color 0.2s ease;
    box-sizing: border-box;
    position: relative;
    z-index: 1;
}

.app-button--link {
    width: auto;
    padding: 0;
    margin-bottom: 0;
    background: none;
    color: #000;
    font-size: 16px;
    font-weight: 600;
}

.app-button--link:hover {
    background: none;
    text-decoration: underline;
}

.app-button--fixed {
    width: 290px;
    height: 45px;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 0 24px;
    margin-bottom: 0;
    font-weight: 400;
    font-family: 'Open Sans', sans-serif;
    line-height: 150%;
    letter-spacing: -1.1%;
    text-align: center;
    flex-shrink: 0;
}

.app-button:last-child {
    margin-bottom: 0;
}

.app-button--primary {
    background-color: #1946A0;
    color: #ffffff;
}

.app-button--primary:hover {
    background-color: #1a3d95;
}

.app-button--primary:active {
    background-color: #163882;
}

.app-button--secondary {
    background-color: #ffffff;
    color: #333333;
    border: 1px solid #1946A0;
}

.app-button--secondary:hover {
    background-color: #f5f5f5;
}

.app-button--light {
    background-color: #DCE2ED;
    color: #1946A0;
    border: none;
}

.app-button--light:hover:not(.app-button--disabled) {
    background-color: #CBD4E6;
}

.app-button--outline-light {
    background-color: #ffffff;
    color: #666666;
    border: 1px solid #A8B8D4;
}

.app-button--outline-light:hover:not(.app-button--disabled) {
    background-color: #f8f9fb;
}

.app-button--disabled {
    opacity: 0.5;
    cursor: not-allowed;
}

.app-button--disabled:hover {
    background-color: inherit;
}
</style>
