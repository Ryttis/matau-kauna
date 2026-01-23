<script setup>
const props = defineProps({
    text: {
        type: String,
        required: true
    },
    variant: {
        type: String,
        default: 'primary'
    }
})
</script>

<template>
    <button
        class="app-button"
        :class="`app-button--${variant}`"
        type="submit"
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
}

/* Primary */
.app-button--primary {
    background-color: #1946A0;
    color: #ffffff;
}

.app-button--primary:hover {
    background-color: #143a86;
}

/* Secondary */
.app-button--secondary {
    background-color: #FFF;
    color: #000;
    border: 1px solid #1946A0;
}

.app-button--secondary:hover {
    background-color: #e9ebef;
}
</style>
