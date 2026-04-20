<script setup>
import { ref, watch, nextTick } from 'vue'

const props = defineProps({
    attachment: {
        type: Object,
        default: null,
    },
    zIndex: {
        type: Number,
        default: 1200,
    },
})

const emit = defineEmits(['close'])

const overlayRef = ref(null)

watch(
    () => props.attachment,
    (v) => {
        if (v) {
            nextTick(() => overlayRef.value?.focus?.())
        }
    },
)

function onClose() {
    emit('close')
}
</script>

<template>
    <Teleport to="body">
        <div
            v-if="attachment"
            ref="overlayRef"
            class="attachment-media-lightbox"
            role="dialog"
            aria-modal="true"
            tabindex="-1"
            :style="{ zIndex: zIndex }"
            @click.self="onClose"
            @keydown.escape="onClose"
        >
            <button
                type="button"
                class="attachment-media-lightbox__close"
                aria-label="Uždaryti"
                @click="onClose"
            >
                ×
            </button>
            <img
                v-if="attachment.kind === 'photo'"
                :src="attachment.url"
                alt=""
                class="attachment-media-lightbox__media"
            />
            <video
                v-else
                :src="attachment.url"
                class="attachment-media-lightbox__media attachment-media-lightbox__media--video"
                controls
                playsinline
            />
        </div>
    </Teleport>
</template>

<style scoped>
.attachment-media-lightbox {
    position: fixed;
    inset: 0;
    background: rgba(0, 0, 0, 0.88);
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 24px;
    box-sizing: border-box;
}

.attachment-media-lightbox__close {
    position: fixed;
    top: 16px;
    right: 20px;
    z-index: 1;
    width: 44px;
    height: 44px;
    border: none;
    border-radius: 8px;
    background: rgba(255, 255, 255, 0.15);
    color: #fff;
    font-size: 28px;
    line-height: 1;
    cursor: pointer;
}

.attachment-media-lightbox__close:hover {
    background: rgba(255, 255, 255, 0.28);
}

.attachment-media-lightbox__media {
    max-width: 100%;
    max-height: calc(100vh - 48px);
    width: auto;
    height: auto;
    object-fit: contain;
}

.attachment-media-lightbox__media--video {
    max-width: min(100%, 960px);
    background: #000;
}
</style>
