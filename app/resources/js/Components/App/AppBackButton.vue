<script setup>
import { ICON_LEFT_ARROW } from '@/constants/icons'

const props = defineProps({
    to: {
        type: [String, Object, Function],
        default: null
    },
    iconSize: {
        type: String,
        default: '16px',
        validator: (value) => ['16px', '24px'].includes(value)
    }
})

const emit = defineEmits(['click'])

const handleClick = () => {
    if (props.to) {
        if (typeof props.to === 'function') {
            props.to()
        } else if (typeof props.to === 'string') {
            window.location.href = props.to
        }
    }
    emit('click')
}
</script>

<template>
    <button @click="handleClick" class="app-back-button">
        <img
            :src="ICON_LEFT_ARROW"
            alt="Atgal"
            class="app-back-icon"
            :style="{ width: iconSize, height: iconSize }"
        />
    </button>
</template>

<style scoped>
.app-back-button {
    background: transparent;
    border: none;
    cursor: pointer;
    padding: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: opacity 0.2s;
    flex-shrink: 0;
}

.app-back-button:hover {
    opacity: 0.7;
}

.app-back-icon {
    display: block;
    width: 16px;
    height: 16px;
}
</style>
