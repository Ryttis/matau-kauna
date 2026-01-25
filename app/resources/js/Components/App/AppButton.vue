<script setup>
const props = defineProps({
    text: {
        type: String,
        required: true
    },
    variant: {
        type: String,
        default: 'primary'
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

const handleClick = () => {
    if (props.asButton) {
        emit('click')
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
}

.app-button--fixed {
    width: 290px;
    height: 45px;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 0 24px;
    font-weight: 400;
    font-family: 'Open Sans', sans-serif;
    line-height: 150%;
    letter-spacing: -1.1%;
    text-align: center;
}

.app-button:last-child {
    margin-bottom: 0;
}

/* Primary - tamsiai mėlynas fonas, baltas tekstas */
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

/* Secondary - baltas fonas, tamsiai mėlynas rėmelis, tamsiai pilkas tekstas */
.app-button--secondary {
    background-color: #ffffff;
    color: #333333;
    border: 1px solid #1946A0;
}

.app-button--secondary:hover {
    background-color: #f5f5f5;
}

.app-button--disabled {
    opacity: 0.5;
    cursor: not-allowed;
}

.app-button--disabled:hover {
    background-color: inherit;
}
</style>
