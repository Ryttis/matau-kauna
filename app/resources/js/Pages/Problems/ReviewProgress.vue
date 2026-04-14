<script setup>
import { ref, watch } from 'vue'
import axios from 'axios'
import { Head, router } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import AppBackButton from '@/Components/App/AppBackButton.vue'
import AppButton from '@/Components/App/AppButton.vue'
import AddEntryModal from '@/Components/App/Modals/AddEntryModal.vue'
import ConfirmModal from '@/Components/App/ConfirmModal.vue'
import AttachmentMediaLightbox from '@/Components/Problems/AttachmentMediaLightbox.vue'
import StatusBadge from '@/Components/Problems/StatusBadge.vue'
import { PROBLEM_STATUS_LABELS } from '@/constants/domain/problemStatus'

const showAddEntryModal = ref(false)
const editingEntry = ref(null)

const openAddEntry = () => {
    editingEntry.value = null
    showAddEntryModal.value = true
}

const openEditEntry = (entry) => {
    editingEntry.value = entry
    showAddEntryModal.value = true
}

const closeAddEntry = () => {
    showAddEntryModal.value = false
    editingEntry.value = null
}

const props = defineProps({
    problem: Object,
    reviewEntries: { type: Array, default: () => [] },
    backUrl: { type: String, default: '' },
    canAddEntry: { type: Boolean, default: true },
    canDeleteReviewAttachments: { type: Boolean, default: false },
    showReviewVisibilityLabels: { type: Boolean, default: false },
    showWatchToggle: { type: Boolean, default: false },
    isWatched: { type: Boolean, default: false },
    watchProblemId: { type: Number, default: null },
    fileUpload: {
        type: Object,
        default: () => ({}),
    },
    authUserId: { type: Number, default: null },
    isSystemAdmin: { type: Boolean, default: false },
})

const canEditEntry = (entry) => {
    if (props.isSystemAdmin) return true
    if (entry.user_id && props.authUserId) return entry.user_id === props.authUserId
    return false
}

const watchActive = ref(props.isWatched)
const watchLoading = ref(false)

watch(
    () => props.isWatched,
    (v) => { watchActive.value = v }
)

async function toggleProblemWatch() {
    if (!props.watchProblemId || watchLoading.value) return
    watchLoading.value = true
    try {
        const { data } = await axios.post(route('problems.watch.toggle', props.watchProblemId))
        watchActive.value = !!data.watched
    } finally {
        watchLoading.value = false
    }
}

const handleBack = () => {
    const url = props.backUrl || route('problems.show', props.problem.id)
    router.visit(url)
}

const showDeleteReviewAttachment = ref(false)
const pendingReviewAttachment = ref(null)
const deleteReviewAttachmentLoading = ref(false)

function openDeleteReviewAttachment(a, e) {
    e?.stopPropagation?.()
    e?.preventDefault?.()
    if (!props.canDeleteReviewAttachments || deleteReviewAttachmentLoading.value) return
    pendingReviewAttachment.value = a
    showDeleteReviewAttachment.value = true
}

function onCloseDeleteReviewAttachment() {
    if (deleteReviewAttachmentLoading.value) return
    showDeleteReviewAttachment.value = false
    pendingReviewAttachment.value = null
}

function confirmDeleteReviewAttachment() {
    const a = pendingReviewAttachment.value
    if (!a || !props.problem?.id) return
    deleteReviewAttachmentLoading.value = true
    router.delete(
        route('problems.reviewProgress.attachments.destroy', {
            problem: props.problem.id,
            attachment: a.id,
        }),
        {
            preserveScroll: true,
            onFinish: () => {
                deleteReviewAttachmentLoading.value = false
                showDeleteReviewAttachment.value = false
                pendingReviewAttachment.value = null
            },
        },
    )
}

const reviewLightboxPreview = ref(null)

function openReviewAttachmentLightbox(a) {
    reviewLightboxPreview.value = a
}

function closeReviewAttachmentLightbox() {
    reviewLightboxPreview.value = null
}
</script>

<template>
    <Head :title="`Nagrinėjimo eiga - ${problem.internal_number}`" />

    <AuthenticatedLayout>
        <div class="review-progress-page">
            <div class="review-progress-header">
                <AppBackButton @click="handleBack" icon-size="16px" />
                <div class="review-progress-header-info">
                    <div class="review-progress-header-info-item">
                        <span class="review-progress-header-label">Data</span>
                        <span class="review-progress-header-value">{{ new Date(problem.created_at).toLocaleDateString('lt-LT') }}</span>
                    </div>
                    <div class="review-progress-header-spacer"></div>
                    <div class="review-progress-header-info-item">
                        <span class="review-progress-header-label">Nr</span>
                        <span class="review-progress-header-value">{{ problem.internal_number }}</span>
                    </div>
                </div>
            </div>

            <div v-if="canAddEntry" class="review-progress-add-top">
                <AppButton
                    text="Pridėti įrašą"
                    variant="primary"
                    as-button
                    class="review-progress-add-btn"
                    @click="openAddEntry"
                />
            </div>

            <div class="review-progress-status-section">
                <StatusBadge :status="problem?.status" />
                <button
                    v-if="showWatchToggle"
                    type="button"
                    class="review-progress-watch-btn"
                    :class="{ 'is-active': watchActive }"
                    :aria-pressed="watchActive"
                    :title="watchActive ? 'Nebestebėti' : 'Stebėti'"
                    :aria-label="watchActive ? 'Stebima: gausite pranešimus apie naujus įrašus' : 'Stebėti ir gauti pranešimus apie naujus įrašus'"
                    :disabled="watchLoading"
                    @click="toggleProblemWatch"
                >
                    {{ watchActive ? 'Stebima' : 'Stebėti' }}
                </button>
            </div>

            <div class="review-progress-description">
                <div class="review-progress-description-label">Aprašymas</div>
                <div class="review-progress-description-text">{{ problem.description || 'Nėra aprašymo' }}</div>
            </div>

            <div class="review-progress-content">
                <div
                    v-for="entry in reviewEntries"
                    :key="entry.id ?? entry.date"
                    class="review-progress-entry"
                >
                    <div class="review-progress-entry-header">
                        <span class="review-progress-date">{{ new Date(entry.date).toLocaleDateString('lt-LT') }}</span>
                        <div class="review-progress-entry-header-actions">
                            <button
                                v-if="canEditEntry(entry)"
                                type="button"
                                class="review-progress-edit-btn"
                                @click="openEditEntry(entry)"
                            >
                                Koreguoti
                            </button>
                            <span class="review-progress-time">{{ new Date(entry.date).toLocaleTimeString('lt-LT', { hour: '2-digit', minute: '2-digit' }) }}</span>
                        </div>
                    </div>
                    <div class="review-progress-entry-body">
                        <div
                            v-if="entry.status_changed"
                            class="review-progress-status"
                        >
                            Būsena pakeista į: {{ PROBLEM_STATUS_LABELS[entry.status] || entry.status }}
                        </div>
                        <div class="review-progress-responsible">
                            Atsakingas: {{ entry.responsible }}
                        </div>
                        <template v-if="entry.comment">
                            <div class="review-progress-divider"></div>
                            <div class="review-progress-comment">
                                <div class="review-progress-comment-heading">
                                    <span class="review-progress-comment-label">Komentaras</span>
                                    <span
                                        v-if="showReviewVisibilityLabels && entry.visibility === 'internal'"
                                        class="review-progress-visibility review-progress-visibility--internal"
                                    >Vidinis</span>
                                    <span
                                        v-else-if="showReviewVisibilityLabels && entry.visibility === 'public'"
                                        class="review-progress-visibility review-progress-visibility--public"
                                    >Viešas</span>
                                </div>
                                <div class="review-progress-comment-text">{{ entry.comment }}</div>
                            </div>
                        </template>
                        <div
                            v-if="entry.attachments?.length"
                            class="review-progress-entry-attachments"
                        >
                            <div class="review-progress-attachments-label">Priedai</div>
                            <div class="review-progress-attachments-grid">
                                <div
                                    v-for="a in entry.attachments"
                                    :key="a.id"
                                    class="review-progress-attachment-item"
                                >
                                    <button
                                        v-if="canDeleteReviewAttachments"
                                        type="button"
                                        class="review-progress-attachment-delete"
                                        :disabled="deleteReviewAttachmentLoading"
                                        aria-label="Pašalinti priedą"
                                        @click="openDeleteReviewAttachment(a, $event)"
                                    >
                                        Šalinti
                                    </button>
                                    <button
                                        v-if="a.kind === 'photo'"
                                        type="button"
                                        class="review-progress-attachment-thumb-btn"
                                        aria-label="Peržiūrėti nuotrauką"
                                        @click="openReviewAttachmentLightbox(a)"
                                    >
                                        <span class="review-progress-attachment-link">
                                            <img :src="a.url" alt="" class="review-progress-attachment-thumb" />
                                        </span>
                                    </button>
                                    <button
                                        v-else
                                        type="button"
                                        class="review-progress-attachment-thumb-btn"
                                        aria-label="Peržiūrėti vaizdo įrašą"
                                        @click="openReviewAttachmentLightbox(a)"
                                    >
                                        <span class="review-progress-attachment-link">
                                            <video
                                                class="review-progress-attachment-video review-progress-attachment-video--thumb"
                                                muted
                                                playsinline
                                                :src="a.url"
                                            />
                                        </span>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div v-if="reviewEntries.length === 0" class="review-progress-empty">
                    <span>Nėra nagrinėjimo įrašų</span>
                </div>
            </div>
        </div>

        <AddEntryModal
            v-if="canAddEntry"
            :open="showAddEntryModal"
            :problem-id="problem.id"
            :current-status="problem.status || ''"
            :entry="editingEntry"
            :file-upload="fileUpload"
            @close="closeAddEntry"
        />

        <ConfirmModal
            :open="showDeleteReviewAttachment"
            message="Ar tikrai pašalinti šį priedą?"
            confirm-text="Pašalinti"
            cancel-text="Atšaukti"
            footer-layout="column"
            confirm-variant="danger"
            width="400px"
            :z-index="5000"
            :loading="deleteReviewAttachmentLoading"
            @close="onCloseDeleteReviewAttachment"
            @confirm="confirmDeleteReviewAttachment"
        />

        <AttachmentMediaLightbox
            :attachment="reviewLightboxPreview"
            :z-index="5600"
            @close="closeReviewAttachmentLightbox"
        />
    </AuthenticatedLayout>
</template>

<style scoped>
.review-progress-page {
    max-width: 1200px;
    margin: 0 auto;
    padding: 20px;
}

.review-progress-header {
    display: flex;
    align-items: center;
    gap: 16px;
    margin-bottom: 20px;
    padding-bottom: 16px;
    border-bottom: 1px solid #e0e0e0;
}

.review-progress-add-top {
    display: flex;
    justify-content: center;
    margin-bottom: 20px;
}

.review-progress-add-top :deep(.review-progress-add-btn) {
    width: var(--button-width);
    height: var(--button-height);
    padding: 9px 24px;
    border-radius: var(--button-border-radius);
    font-family: var(--font-family-base, 'Open Sans', sans-serif);
}

.review-progress-header-info {
    display: flex;
    align-items: center;
    gap: 24px;
    flex: 1;
}

.review-progress-header-spacer {
    flex: 1;
}

.review-progress-header-info-item {
    display: flex;
    flex-direction: column;
    gap: 4px;
}

.review-progress-header-label {
    font-size: 14px;
    color: #666;
    font-family: var(--font-family-base, 'Open Sans', sans-serif);
}

.review-progress-header-value {
    font-size: 16px;
    font-weight: 600;
    color: #333;
    font-family: var(--font-family-base, 'Open Sans', sans-serif);
}

.review-progress-status-section {
    margin-bottom: 20px;
    display: flex;
    align-items: center;
    justify-content: flex-start;
    gap: 12px;
    flex-wrap: wrap;
}

.review-progress-watch-btn {
    border: 1px solid var(--color-primary, #1946a0);
    background: #fff;
    color: var(--color-primary, #1946a0);
    padding: 0 16px;
    min-width: 120px;
    height: var(--control-height, 45px);
    cursor: pointer;
    line-height: 1;
    font-size: 15px;
    font-weight: 600;
    border-radius: var(--control-radius, 15px);
    font-family: var(--font-family-base, 'Open Sans', sans-serif);
    white-space: nowrap;
    transition: background 0.15s ease, opacity 0.15s ease;
}

.review-progress-watch-btn:hover:not(:disabled) {
    background: #e8eef7;
}

.review-progress-watch-btn:disabled {
    opacity: 0.5;
    cursor: wait;
}

.review-progress-watch-btn.is-active {
    background: var(--color-primary, #1946a0);
    color: #fff;
    border-color: var(--color-primary, #1946a0);
}

.review-progress-watch-btn.is-active:hover:not(:disabled) {
    background: var(--color-primary-hover, #153a8a);
    border-color: var(--color-primary-hover, #153a8a);
}

.review-progress-description {
    margin-bottom: 40px;
    padding: 20px;
    background: #f9f9f9;
    border-radius: 5px;
    border: 1px solid #e0e0e0;
}

.review-progress-description-label {
    font-size: 16px;
    font-weight: 600;
    color: #333;
    font-family: var(--font-family-base, 'Open Sans', sans-serif);
    margin-bottom: 12px;
}

.review-progress-description-text {
    font-size: 16px;
    color: #333;
    font-family: var(--font-family-base, 'Open Sans', sans-serif);
    line-height: 1.5;
    white-space: pre-wrap;
}


.review-progress-title {
    font-size: 24px;
    font-weight: 400;
    color: #333333;
    margin: 0;
    font-family: var(--font-family-base, 'Open Sans', sans-serif);
}

.review-progress-content {
    display: flex;
    flex-direction: column;
    gap: 20px;
}

.review-progress-entry {
    background: #ffffff;
    border: 1px solid #e0e0e0;
    border-radius: 5px;
    padding: 20px;
}

.review-progress-entry-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 16px;
    gap: 12px;
}

.review-progress-entry-header-actions {
    display: flex;
    align-items: center;
    gap: 12px;
    flex-shrink: 0;
}

.review-progress-edit-btn {
    border: none;
    background: none;
    padding: 0;
    font-size: 15px;
    font-weight: 600;
    color: var(--color-primary, #1946A0);
    font-family: var(--font-family-base, 'Open Sans', sans-serif);
    cursor: pointer;
    text-decoration: none;
}

.review-progress-edit-btn:hover {
    color: #0f2d6b;
}

.review-progress-date {
    font-size: 16px;
    color: #333333;
    font-family: var(--font-family-base, 'Open Sans', sans-serif);
    font-weight: 400;
}

.review-progress-time {
    font-size: 16px;
    color: #333333;
    font-family: var(--font-family-base, 'Open Sans', sans-serif);
    font-weight: 400;
}

.review-progress-entry-body {
    display: flex;
    flex-direction: column;
    gap: 12px;
}

.review-progress-status {
    font-size: 16px;
    color: #333333;
    font-family: var(--font-family-base, 'Open Sans', sans-serif);
    font-weight: 400;
}

.review-progress-responsible {
    font-size: 16px;
    color: #333333;
    font-family: var(--font-family-base, 'Open Sans', sans-serif);
    font-weight: 400;
}

.review-progress-divider {
    height: 1px;
    background: #e0e0e0;
    margin: 12px 0;
}

.review-progress-comment {
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.review-progress-comment-heading {
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 10px;
}

.review-progress-visibility {
    font-size: 12px;
    font-weight: 600;
    padding: 2px 10px;
    border-radius: 999px;
}

.review-progress-visibility--internal {
    background: #fdebd0;
    color: #a04000;
}

.review-progress-visibility--public {
    background: #e8f4fd;
    color: #1565c0;
}

.review-progress-comment-label {
    font-size: 16px;
    color: #333333;
    font-family: var(--font-family-base, 'Open Sans', sans-serif);
    font-weight: 400;
}

.review-progress-comment-text {
    font-size: 16px;
    color: #333333;
    font-family: var(--font-family-base, 'Open Sans', sans-serif);
    font-weight: 400;
    min-height: 20px;
    padding-bottom: 4px;
}

.review-progress-entry-attachments {
    margin-top: 16px;
    padding-top: 12px;
    border-top: 1px solid #e0e0e0;
}

.review-progress-attachments-label {
    font-size: 14px;
    font-weight: 600;
    color: #333;
    margin-bottom: 10px;
    font-family: var(--font-family-base, 'Open Sans', sans-serif);
}

.review-progress-attachments-grid {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
}

.review-progress-attachment-item {
    position: relative;
    display: inline-block;
    max-width: 100%;
}

.review-progress-attachment-delete {
    position: absolute;
    top: 6px;
    right: 6px;
    z-index: 2;
    padding: 4px 10px;
    font-size: 12px;
    font-weight: 600;
    font-family: var(--font-family-base, 'Open Sans', sans-serif);
    color: #fff;
    background: rgba(180, 40, 40, 0.92);
    border: none;
    border-radius: 6px;
    cursor: pointer;
    line-height: 1.2;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.25);
}

.review-progress-attachment-delete:hover:not(:disabled) {
    background: rgba(200, 50, 50, 1);
}

.review-progress-attachment-delete:disabled {
    opacity: 0.5;
    cursor: wait;
}

.review-progress-attachment-thumb-btn {
    padding: 0;
    margin: 0;
    border: none;
    background: none;
    cursor: pointer;
    display: block;
    line-height: 0;
    border-radius: 8px;
    font: inherit;
}

.review-progress-attachment-thumb-btn:focus-visible {
    outline: 2px solid var(--color-primary, #1946a0);
    outline-offset: 2px;
}

.review-progress-attachment-link {
    display: block;
    width: 120px;
    height: 120px;
    border-radius: 8px;
    overflow: hidden;
    border: 1px solid #e0e0e0;
    background: #f5f5f5;
    box-sizing: border-box;
}

.review-progress-attachment-thumb {
    display: block;
    width: 100%;
    height: 100%;
    object-fit: cover;
    vertical-align: top;
}

.review-progress-attachment-video {
    display: block;
    width: 120px;
    height: 120px;
    border-radius: 8px;
    border: 1px solid #e0e0e0;
    box-sizing: border-box;
    object-fit: cover;
    background: #111;
}

.review-progress-attachment-video--thumb {
    border: none;
    border-radius: 0;
}

.review-progress-empty {
    text-align: center;
    padding: 40px;
    color: #999;
    font-size: 16px;
    font-family: var(--font-family-base, 'Open Sans', sans-serif);
}

</style>
