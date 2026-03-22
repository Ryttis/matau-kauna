<script setup>
import { computed, useAttrs } from 'vue'
import { ICON_CLOSE } from '@/constants/icons'

defineOptions({ inheritAttrs: false })

const attrs = useAttrs()

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
    maxHeight: {
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
    },
    zIndex: {
        type: Number,
        default: 1000
    }
})

const emit = defineEmits(['close'])

const modalStyle = computed(() => {
    const s = { width: props.width }
    if (props.maxWidth != null && props.maxWidth !== '') {
        s.maxWidth = props.maxWidth
    }
    if (props.minWidth != null && props.minWidth !== '') {
        s.minWidth = props.minWidth
    }
    if (props.maxHeight != null && props.maxHeight !== '') {
        s.maxHeight = props.maxHeight
    }
    return s
})

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
    <Teleport to="body">
        <div v-if="isOpen" class="modal-overlay" :style="{ zIndex: props.zIndex }" @click="handleOverlayClick">
            <div class="modal" v-bind="attrs" :style="modalStyle" @click.stop>
                <div v-if="$slots.header || title || showCloseButton" class="modal-header">
                    <slot name="header">
                        <div v-if="title" class="modal-title">{{ title }}</div>
                        <div v-else class="modal-title-empty"></div>
                    </slot>
                    <button
                        v-if="showCloseButton"
                        type="button"
                        class="x-btn"
                        @click="handleClose"
                    >
                        <img :src="ICON_CLOSE" alt="Uždaryti" class="close-icon" />
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
    </Teleport>
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
    padding: 20px;
    overflow-y: auto;
    box-sizing: border-box;
}

.modal {
    max-width: 100%;
    background: var(--color-white, #fff);
    border-radius: 15px;
    overflow: hidden;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.15);
    max-height: min(85vh, 85dvh, calc(100vh - 40px), calc(100dvh - 40px));
    display: flex;
    flex-direction: column;
    margin: auto;
    flex-shrink: 0;
}

@media (max-width: 640px) {
    .modal-overlay {
        padding: 12px;
        align-items: stretch;
    }

    .modal {
        width: 100% !important;
        max-width: 100% !important;
        min-width: 0 !important;
        margin-top: auto;
        margin-bottom: auto;
        max-height: min(92vh, 92dvh, calc(100vh - 24px), calc(100dvh - 24px));
        border-radius: 12px;
    }
}

.modal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 16px 20px;
    flex-shrink: 0;
}

.modal-title {
    font-size: 16px;
    font-weight: 700;
    color: var(--color-text, #333);
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
    flex: 1 1 auto;
    min-height: 0;
    overflow: auto;
}

.modal-footer {
    display: flex;
    flex-direction: row;
    flex-wrap: wrap;
    justify-content: flex-end;
    align-items: center;
    gap: 10px;
    padding: 16px 20px;
    flex-shrink: 0;
}

.modal-footer.modal-footer-row :deep(.app-button.app-button--fixed) {
    width: auto;
    min-width: 100px;
    flex: 0 0 auto;
    margin-bottom: 0;
}

.modal-footer-column {
    flex-direction: column;
    align-items: center;
    gap: 10px;
}
</style>
