<script setup>
import { ref, computed, watch, nextTick, onMounted, onUnmounted } from 'vue'

const props = defineProps({
    attachment: {
        type: Object,
        default: null,
    },
    attachments: {
        type: Array,
        default: () => [],
    },
    zIndex: {
        type: Number,
        default: 1200,
    },
})

const emit = defineEmits(['close', 'change'])

const overlayRef = ref(null)

const currentIndex = computed(() => {
    if (!props.attachment || !Array.isArray(props.attachments) || props.attachments.length === 0) {
        return -1
    }
    const ref1 = props.attachment.url
    const ref2 = props.attachment.id
    return props.attachments.findIndex((a) => {
        if (!a) return false
        if (ref2 != null && a.id != null) return a.id === ref2
        return a.url === ref1
    })
})

const canNavigate = computed(
    () => currentIndex.value !== -1 && props.attachments.length > 1,
)
const canPrev = computed(() => canNavigate.value && currentIndex.value > 0)
const canNext = computed(
    () => canNavigate.value && currentIndex.value < props.attachments.length - 1,
)

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

function goPrev() {
    if (!canPrev.value) return
    emit('change', props.attachments[currentIndex.value - 1])
}

function goNext() {
    if (!canNext.value) return
    emit('change', props.attachments[currentIndex.value + 1])
}

function onKeydown(event) {
    if (!props.attachment) return
    if (event.key === 'ArrowLeft') {
        goPrev()
    } else if (event.key === 'ArrowRight') {
        goNext()
    }
}

onMounted(() => {
    document.addEventListener('keydown', onKeydown)
})

onUnmounted(() => {
    document.removeEventListener('keydown', onKeydown)
})
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
            <button
                v-if="canPrev"
                type="button"
                class="attachment-media-lightbox__nav attachment-media-lightbox__nav--prev"
                aria-label="Ankstesnis"
                @click.stop="goPrev"
            >
                <svg viewBox="0 0 16 16" aria-hidden="true">
                    <path
                        fill-rule="evenodd"
                        d="M11.354 1.646a.5.5 0 0 1 0 .708L5.707 8l5.647 5.646a.5.5 0 0 1-.708.708l-6-6a.5.5 0 0 1 0-.708l6-6a.5.5 0 0 1 .708 0z"
                        fill="currentColor"
                    />
                </svg>
            </button>
            <button
                v-if="canNext"
                type="button"
                class="attachment-media-lightbox__nav attachment-media-lightbox__nav--next"
                aria-label="Kitas"
                @click.stop="goNext"
            >
                <svg viewBox="0 0 16 16" aria-hidden="true">
                    <path
                        fill-rule="evenodd"
                        d="M4.646 1.646a.5.5 0 0 1 .708 0l6 6a.5.5 0 0 1 0 .708l-6 6a.5.5 0 0 1-.708-.708L10.293 8 4.646 2.354a.5.5 0 0 1 0-.708z"
                        fill="currentColor"
                    />
                </svg>
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

.attachment-media-lightbox__nav {
    position: fixed;
    top: 50%;
    transform: translateY(-50%);
    z-index: 1;
    width: 48px;
    height: 48px;
    border: none;
    border-radius: 999px;
    background: rgba(255, 255, 255, 0.15);
    color: #fff;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    transition: background 0.15s ease;
}

.attachment-media-lightbox__nav:hover {
    background: rgba(255, 255, 255, 0.32);
}

.attachment-media-lightbox__nav svg {
    width: 22px;
    height: 22px;
    display: block;
}

.attachment-media-lightbox__nav--prev {
    left: 20px;
}

.attachment-media-lightbox__nav--next {
    right: 20px;
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

@media (max-width: 640px) {
    .attachment-media-lightbox__nav {
        width: 40px;
        height: 40px;
    }

    .attachment-media-lightbox__nav--prev {
        left: 8px;
    }

    .attachment-media-lightbox__nav--next {
        right: 8px;
    }
}
</style>
