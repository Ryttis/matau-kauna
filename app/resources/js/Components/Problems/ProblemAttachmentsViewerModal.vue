<script setup>
import { watch, ref, computed, nextTick } from 'vue'
import { router } from '@inertiajs/vue3'
import BaseModal from '@/Components/App/BaseModal.vue'
import ConfirmModal from '@/Components/App/ConfirmModal.vue'
import AttachmentMediaLightbox from '@/Components/Problems/AttachmentMediaLightbox.vue'

const props = defineProps({
    show: {
        type: Boolean,
        default: false,
    },
    attachments: {
        type: Array,
        default: () => [],
    },
    problemId: {
        type: [Number, String],
        default: null,
    },
    canDelete: {
        type: Boolean,
        default: false,
    },
    initialAttachmentId: {
        type: [Number, String],
        default: null,
    },
})

const emit = defineEmits(['close'])

const preview = ref(null)
const showDeleteConfirm = ref(false)
const pendingAttachment = ref(null)
const deleteLoading = ref(false)

const viewerModalOpen = computed(
    () => props.show && !showDeleteConfirm.value
)

watch(
    () => props.show,
    (v) => {
        if (!v) {
            preview.value = null
            showDeleteConfirm.value = false
            pendingAttachment.value = null
            deleteLoading.value = false
            return
        }
        const id = props.initialAttachmentId
        if (id == null) return
        nextTick(() => {
            const a = props.attachments.find((x) => String(x.id) === String(id))
            if (a) {
                openPreview(a)
            }
        })
    }
)

function openPreview(a) {
    preview.value = a
}

function closePreview() {
    preview.value = null
}

function openDeleteConfirm(a, e) {
    e.stopPropagation()
    e.preventDefault()
    if (!props.problemId || deleteLoading.value) return
    pendingAttachment.value = a
    showDeleteConfirm.value = true
}

function onCloseDeleteConfirm() {
    if (deleteLoading.value) return
    showDeleteConfirm.value = false
    pendingAttachment.value = null
}

function confirmDelete() {
    const a = pendingAttachment.value
    if (!a || !props.problemId) return

    deleteLoading.value = true
    router.delete(
        route('problems.attachments.destroy', {
            problem: props.problemId,
            attachment: a.id,
        }),
        {
            preserveScroll: true,
            onSuccess: () => {
                emit('close')
                if (preview.value?.id === a.id) {
                    preview.value = null
                }
            },
            onFinish: () => {
                deleteLoading.value = false
                showDeleteConfirm.value = false
                pendingAttachment.value = null
            },
        }
    )
}
</script>

<template>
    <BaseModal
        :show="viewerModalOpen"
        width="920px"
        max-width="96vw"
        max-height="88vh"
        :z-index="1100"
        @close="emit('close')"
    >
        <template #header>
            <span class="viewer-title">Priedai</span>
        </template>

        <div class="viewer-grid">
            <div
                v-for="a in attachments"
                :key="a.id"
                class="viewer-tile-wrap"
            >
                <button
                    type="button"
                    class="viewer-tile"
                    @click="openPreview(a)"
                >
                    <img
                        v-if="a.kind === 'photo'"
                        :src="a.url"
                        alt=""
                        class="viewer-thumb"
                    />
                    <video
                        v-else
                        :src="a.url"
                        class="viewer-thumb viewer-thumb-video"
                        muted
                        playsinline
                    />
                    <span class="viewer-tile-hint">Peržiūrėti</span>
                </button>
                <button
                    v-if="canDelete && problemId"
                    type="button"
                    class="viewer-delete"
                    :disabled="deleteLoading"
                    aria-label="Pašalinti priedą"
                    @click="openDeleteConfirm(a, $event)"
                >
                    Šalinti
                </button>
            </div>
        </div>
    </BaseModal>

    <ConfirmModal
        :open="showDeleteConfirm"
        message="Ar tikrai pašalinti šį priedą?"
        confirm-text="Pašalinti"
        cancel-text="Atšaukti"
        footer-layout="column"
        confirm-variant="danger"
        width="400px"
        :z-index="5000"
        :loading="deleteLoading"
        @close="onCloseDeleteConfirm"
        @confirm="confirmDelete"
    />

    <AttachmentMediaLightbox :attachment="preview" @close="closePreview" />
</template>

<style scoped>
.viewer-title {
    font-size: 18px;
    font-weight: 600;
    font-family: 'Open Sans', sans-serif;
}

.viewer-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(140px, 1fr));
    gap: 12px;
    padding: 4px 0 8px;
    min-height: 160px;
    max-height: min(60vh, 520px);
    overflow: auto;
}

.viewer-tile-wrap {
    position: relative;
    aspect-ratio: 4 / 3;
}

.viewer-tile {
    position: absolute;
    inset: 0;
    border: 1px solid var(--color-border, #e5e5e5);
    border-radius: 8px;
    overflow: hidden;
    padding: 0;
    background: #f5f5f5;
    cursor: pointer;
    display: block;
    width: 100%;
    height: 100%;
}

.viewer-tile:hover .viewer-tile-hint {
    opacity: 1;
}

.viewer-thumb {
    width: 100%;
    height: 100%;
    object-fit: cover;
    display: block;
}

.viewer-thumb-video {
    object-fit: cover;
}

.viewer-tile-hint {
    position: absolute;
    inset: 0;
    display: flex;
    align-items: center;
    justify-content: center;
    background: rgba(0, 0, 0, 0.45);
    color: #fff;
    font-size: 14px;
    font-weight: 600;
    opacity: 0;
    transition: opacity 0.15s;
    pointer-events: none;
}

.viewer-delete {
    position: absolute;
    top: 6px;
    right: 6px;
    z-index: 2;
    padding: 4px 8px;
    font-size: 12px;
    font-weight: 600;
    font-family: 'Open Sans', sans-serif;
    color: #fff;
    background: rgba(180, 40, 40, 0.92);
    border: none;
    border-radius: 6px;
    cursor: pointer;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.25);
}

.viewer-delete:hover:not(:disabled) {
    background: rgba(200, 50, 50, 1);
}

.viewer-delete:disabled {
    opacity: 0.7;
    cursor: wait;
}

</style>
