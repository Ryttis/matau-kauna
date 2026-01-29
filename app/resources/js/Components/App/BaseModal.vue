<script setup>
import { computed } from 'vue'

const props = defineProps({
    title: {
        type: String,
        default: ''
    },
    open: {
        type: Boolean,
        default: false
    },
    show: {
        type: Boolean,
        default: false
    },
    width: {
        type: String,
        default: '520px'
    },
    maxWidth: {
        type: String,
        default: null
    },
    minWidth: {
        type: String,
        default: null
    },
    showCloseButton: {
        type: Boolean,
        default: true
    },
    closeOnOverlayClick: {
        type: Boolean,
        default: true
    },
    footerLayout: {
        type: String,
        default: 'row',
        validator: (value) => ['row', 'column'].includes(value)
    }
})

const emit = defineEmits(['close'])

const isOpen = computed(() => props.open || props.show)

const handleClose = () => {
    emit('close')
}

const handleOverlayClick = (event) => {
    if (props.closeOnOverlayClick && event.target === event.currentTarget) {
        handleClose()
    }
}
</script>

<template>
    <div v-if="isOpen" class="modal-overlay" @click="handleOverlayClick">
        <div class="modal" :style="{ width, maxWidth: maxWidth || 'none', minWidth: minWidth || 'none' }" @click.stop>
            <div v-if="$slots.header || title || showCloseButton" class="modal-header">
                <slot name="header">
                    <div v-if="title" class="modal-title">{{ title }}</div>
                    <div v-else class="modal-title-empty"></div>
                </slot>
                <button v-if="showCloseButton" type="button" class="x-btn" @click="handleClose">
                    <img src="/icons/close-icon.png" alt="Uždaryti" class="close-icon" />
                </button>
            </div>

            <div class="modal-body">
                <slot />
            </div>

            <div v-if="$slots.footer" class="modal-footer" :class="`modal-footer-${footerLayout}`">
                <slot name="footer" />
            </div>
        </div>
    </div>
</template>

<style scoped>
.modal-overlay {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0, 0, 0, 0.5);
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 1000;
    padding: 20px;
}

.modal {
    max-width: 100%;
    background: #ffffff;
    border-radius: 5px;
    overflow: hidden;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.15);
    max-height: 85vh;
    overflow-y: auto;
    display: flex;
    flex-direction: column;
}

.modal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 16px 20px;
    border-bottom: 1px solid #e0e0e0;
    flex-shrink: 0;
}

.modal-title {
    font-size: 16px;
    font-weight: 700;
    color: #333;
}

.modal-title-empty {
    flex: 1;
}

.x-btn {
    border: none;
    background: transparent;
    cursor: pointer;
    padding: 4px;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: opacity 0.2s;
    width: 48px;
    height: 48px;
    border-radius: 50%;
}

.x-btn:hover {
    opacity: 0.7;
}

.close-icon {
    width: 16px;
    height: 16px;
    display: block;
}

.modal-body {
    padding: 20px;
    flex: 1;
    overflow-y: auto;
}

.modal-footer {
    display: flex;
    justify-content: flex-end;
    gap: 10px;
    padding: 16px 20px;
    border-top: 1px solid #e0e0e0;
    flex-shrink: 0;
}

.modal-footer-column {
    flex-direction: column;
    align-items: center;
    gap: 10px;
}
</style>
