<script setup>
import { ref, computed } from 'vue'
import { router } from '@inertiajs/vue3'
import ConfirmModal from '@/Components/App/ConfirmModal.vue'
import ProblemAttachmentsViewerModal from '@/Components/Problems/ProblemAttachmentsViewerModal.vue'
import AttachmentMediaLightbox from '@/Components/Problems/AttachmentMediaLightbox.vue'

const props = defineProps({
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
    showHeadingWhenEmpty: {
        type: Boolean,
        default: false,
    },
    compact: {
        type: Boolean,
        default: false,
    },
    hideHeading: {
        type: Boolean,
        default: false,
    },
    directLightbox: {
        type: Boolean,
        default: false,
    },
    fillCompactSurface: {
        type: Boolean,
        default: false,
    },
    skipCompactMinHeight: {
        type: Boolean,
        default: false,
    },
    compactUseSmallThumbs: {
        type: Boolean,
        default: false,
    },
    showCompactOpenHint: {
        type: Boolean,
        default: false,
    },
})

const compactThumbClasses = computed(() =>
    props.compactUseSmallThumbs ? ['strip-thumb'] : ['strip-thumb', 'strip-thumb--sm'],
)

const compactVideoClasses = computed(() => [...compactThumbClasses.value, 'strip-video'])

const showViewer = ref(false)
const initialOpenId = ref(null)

const preview = ref(null)
const showDeleteConfirm = ref(false)
const pendingAttachment = ref(null)
const deleteLoading = ref(false)

function openViewerAll() {
    initialOpenId.value = null
    showViewer.value = true
}

function openViewerFromThumb(a) {
    initialOpenId.value = a.id
    showViewer.value = true
}

function onViewerClose() {
    showViewer.value = false
    initialOpenId.value = null
}

function openLightbox(a) {
    preview.value = a
}

function closeLightbox() {
    preview.value = null
}

function openDeleteConfirm(a, e) {
    e?.stopPropagation()
    e?.preventDefault()
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

const showTitle = () =>
    !props.hideHeading && (props.attachments?.length || props.showHeadingWhenEmpty)
</script>

<template>
    <div class="attachments-panel" :class="{ 'attachments-panel--compact': compact }">
        <h3
            v-if="showTitle()"
            class="attachments-heading"
            :class="{
                'attachments-heading--spaced': attachments?.length || showHeadingWhenEmpty,
            }"
        >
            Priedai
        </h3>

        <template v-if="compact">
            <div
                class="attachments-compact-surface"
                :class="{
                    'attachments-compact-surface--fill': fillCompactSurface,
                    'attachments-compact-surface--no-min': skipCompactMinHeight,
                }"
            >
                <template v-if="attachments?.length">
                    <div class="attachments-row-compact">
                        <div
                            v-for="a in attachments"
                            :key="a.id"
                            class="thumb-tile"
                        >
                            <button
                                type="button"
                                class="thumb-btn"
                                aria-label="Peržiūrėti"
                                @click="directLightbox ? openLightbox(a) : openViewerFromThumb(a)"
                            >
                                <img
                                    v-if="a.kind === 'photo'"
                                    :src="a.url"
                                    alt=""
                                    :class="compactThumbClasses"
                                />
                                <video
                                    v-else
                                    :src="a.url"
                                    :class="compactVideoClasses"
                                    muted
                                    playsinline
                                />
                            </button>
                            <button
                                v-if="directLightbox && canDelete && problemId"
                                type="button"
                                class="thumb-remove"
                                :disabled="deleteLoading"
                                aria-label="Pašalinti"
                                @click="openDeleteConfirm(a, $event)"
                            >
                                Šalinti
                            </button>
                        </div>
                        <div v-if="$slots.add" class="attachments-add-wrap">
                            <slot name="add" />
                        </div>
                    </div>
                    <p
                        v-if="showCompactOpenHint"
                        class="attachments-hint attachments-hint--compact"
                    >
                        Paspauskite miniatiūrą, kad atidarytumėte didesnėje peržiūroje.
                    </p>
                </template>
                <div
                    v-else-if="$slots.empty"
                    class="attachments-empty-custom attachments-empty-custom--fill"
                >
                    <slot name="empty" />
                </div>
                <div v-else class="media-placeholder media-placeholder--compact">
                    <span>Nėra priedų</span>
                </div>
            </div>
        </template>

        <template v-else>
            <div
                v-if="attachments?.length"
                class="attachments-trigger"
                role="button"
                tabindex="0"
                @click="openViewerAll"
                @keydown.enter.prevent="openViewerAll"
                @keydown.space.prevent="openViewerAll"
            >
                <div class="attachments-strip">
                    <template v-for="a in attachments" :key="a.id">
                        <img
                            v-if="a.kind === 'photo'"
                            :src="a.url"
                            alt=""
                            class="strip-thumb"
                        />
                        <video
                            v-else
                            :src="a.url"
                            class="strip-thumb strip-video"
                            muted
                            playsinline
                        />
                    </template>
                </div>
                <p class="attachments-hint">Spustelėkite, kad atidarytumėte peržiūrą</p>
            </div>
            <div v-else-if="$slots.empty" class="attachments-empty-custom">
                <slot name="empty" />
            </div>
            <div v-else class="media-placeholder">
                <span>Nėra priedų</span>
            </div>
        </template>

        <ProblemAttachmentsViewerModal
            v-if="!directLightbox"
            :show="showViewer"
            :attachments="attachments"
            :problem-id="problemId"
            :can-delete="canDelete"
            :initial-attachment-id="initialOpenId"
            @close="onViewerClose"
        />

        <ConfirmModal
            v-if="directLightbox"
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

        <AttachmentMediaLightbox
            v-if="directLightbox"
            :attachment="preview"
            @close="closeLightbox"
        />
    </div>
</template>

<style scoped>
.attachments-panel {
    min-height: 150px;
}

.attachments-panel--compact {
    min-height: 0;
    display: flex;
    flex-direction: column;
    flex: 1 1 auto;
}

.attachments-compact-surface {
    width: 100%;
    flex: 1 1 auto;
    border: 1px solid #e0e0e0;
    border-radius: 5px;
    background: #f9f9f9;
    padding: 12px 16px;
    box-sizing: border-box;
    display: flex;
    flex-direction: column;
}

.attachments-compact-surface:not(.attachments-compact-surface--fill):not(.attachments-compact-surface--no-min) {
    min-height: var(--reporter-desc-block-min-h, 220px);
}

.attachments-compact-surface--fill {
    min-height: 0;
    height: 100%;
    flex: 1 1 auto;
}

.attachments-heading {
    font-size: 16px;
    font-weight: 600;
    margin: 0;
    color: var(--color-text, #333);
    font-family: 'Open Sans', sans-serif;
}

.attachments-panel--compact .attachments-heading {
    font-size: 14px;
}

.attachments-heading--spaced {
    margin-bottom: 8px;
}

.attachments-empty-custom {
    min-width: 0;
}

.attachments-empty-custom--fill {
    flex: 1 1 auto;
    display: flex;
    flex-direction: column;
    min-height: 0;
    align-items: stretch;
}

.attachments-row-compact {
    flex: 1 1 auto;
    display: flex;
    flex-wrap: wrap;
    align-items: flex-start;
    align-content: flex-start;
    gap: 10px;
    min-height: 0;
}

.thumb-tile {
    position: relative;
    flex: none;
    line-height: 0;
}

.thumb-btn {
    padding: 0;
    border: none;
    background: none;
    cursor: pointer;
    border-radius: 6px;
    line-height: 0;
    display: block;
}

.thumb-btn:focus-visible {
    outline: 2px solid var(--color-primary, #1946a0);
    outline-offset: 2px;
}

.thumb-remove {
    position: absolute;
    top: 4px;
    right: 4px;
    z-index: 2;
    padding: 2px 6px;
    font-size: 11px;
    font-weight: 600;
    font-family: 'Open Sans', sans-serif;
    color: #fff;
    background: rgba(180, 40, 40, 0.92);
    border: none;
    border-radius: 4px;
    cursor: pointer;
    box-shadow: 0 1px 2px rgba(0, 0, 0, 0.2);
}

.thumb-remove:hover:not(:disabled) {
    background: rgba(200, 50, 50, 1);
}

.thumb-remove:disabled {
    opacity: 0.7;
    cursor: wait;
}

.attachments-add-wrap {
    flex: none;
    line-height: 0;
}

.attachments-trigger {
    cursor: pointer;
    border-radius: 8px;
    padding: 8px 8px 12px;
    margin: -8px;
    transition: background 0.15s;
}

.attachments-trigger:hover {
    background: rgba(25, 70, 160, 0.06);
}

.attachments-strip {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
}

.strip-thumb {
    width: 72px;
    height: 72px;
    object-fit: cover;
    border-radius: 6px;
    border: 1px solid var(--color-border, #e0e0e0);
    display: block;
}

.strip-thumb--sm {
    width: 96px;
    height: 96px;
    border-radius: 6px;
    vertical-align: middle;
}

.strip-video {
    background: #111;
}

.attachments-hint {
    margin: 10px 0 0;
    font-size: 13px;
    color: #666;
    font-family: 'Open Sans', sans-serif;
}

.attachments-hint--compact {
    margin: 10px 0 0;
    font-size: 12px;
}

.media-placeholder {
    width: 100%;
    height: 150px;
    background: repeating-conic-gradient(#f0f0f0 0% 25%, #ffffff 0% 50%) 50% / 20px 20px;
    border: 1px solid #e0e0e0;
    border-radius: 5px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #999;
    font-size: 16px;
    font-family: 'Open Sans', sans-serif;
}

.media-placeholder--compact {
    flex: 1 1 auto;
    min-height: 120px;
    height: auto;
    font-size: 14px;
    background: transparent;
    border: none;
}

</style>
