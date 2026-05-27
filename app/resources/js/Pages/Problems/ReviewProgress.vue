<script setup>
import { Head } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import AppBackButton from '@/Components/App/AppBackButton.vue'
import AppButton from '@/Components/App/AppButton.vue'
import AddEntryModal from '@/Components/App/Modals/AddEntryModal.vue'
import { formatDateLt } from '@/utils/dateFormat'
import ConfirmModal from '@/Components/App/ConfirmModal.vue'
import AttachmentMediaLightbox from '@/Components/Problems/AttachmentMediaLightbox.vue'
import StatusBadge from '@/Components/Problems/StatusBadge.vue'
import { PROBLEM_STATUS_LABELS } from '@/constants/domain/problemStatus'
import { useReviewProgressPage } from '@/composables/useReviewProgressPage'
import '@/Components/Problems/review-progress-page.css'

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
    canEditReviewProgress: { type: Boolean, default: false },
})

const {
    showAddEntryModal,
    editingEntry,
    openAddEntryModal,
    openEditEntryModal,
    closeAddEntryModal,
    currentUserMayEditEntry,
    problemIsWatched,
    watchToggleInProgress,
    toggleProblemWatchSubscription,
    goBackFromReviewProgress,
    showDeleteAttachmentConfirm,
    deleteAttachmentInProgress,
    requestDeleteReviewAttachment,
    cancelDeleteReviewAttachment,
    confirmDeleteReviewAttachment,
    reviewAttachmentForLightbox,
    reviewAttachmentsForLightbox,
    openAttachmentInLightbox,
    closeAttachmentLightbox,
} = useReviewProgressPage(props)
</script>

<template>
    <Head :title="`Pranešimų eiga - ${problem.internal_number}`" />

    <AuthenticatedLayout>
        <div class="review-progress-page">
            <div class="review-progress-header">
                <AppBackButton @click="goBackFromReviewProgress" icon-size="16px" />
                <div class="review-progress-header-info">
                    <div class="review-progress-header-info-item">
                        <span class="review-progress-header-label">Data</span>
                        <span class="review-progress-header-value">{{ formatDateLt(problem.created_at) }}</span>
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
                    @click="openAddEntryModal"
                />
            </div>

            <div class="review-progress-status-section">
                <StatusBadge :status="problem?.status" />
                <button
                    v-if="showWatchToggle"
                    type="button"
                    class="review-progress-watch-btn"
                    :class="{ 'is-active': problemIsWatched }"
                    :aria-pressed="problemIsWatched"
                    :title="problemIsWatched ? 'Nebestebėti' : 'Stebėti'"
                    :aria-label="problemIsWatched ? 'Stebima: gausite pranešimus apie naujus įrašus' : 'Stebėti ir gauti pranešimus apie naujus įrašus'"
                    :disabled="watchToggleInProgress"
                    @click="toggleProblemWatchSubscription"
                >
                    {{ problemIsWatched ? 'Stebima' : 'Stebėti' }}
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
                        <span class="review-progress-date">{{ formatDateLt(entry.date) }}</span>
                        <div class="review-progress-entry-header-actions">
                            <button
                                v-if="currentUserMayEditEntry(entry)"
                                type="button"
                                class="review-progress-edit-btn"
                                @click="openEditEntryModal(entry)"
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
                        <div v-if="showReviewVisibilityLabels" class="review-progress-responsible">
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
                                    v-for="attachment in entry.attachments"
                                    :key="attachment.id"
                                    class="review-progress-attachment-item"
                                >
                                    <button
                                        v-if="canDeleteReviewAttachments"
                                        type="button"
                                        class="review-progress-attachment-delete"
                                        :disabled="deleteAttachmentInProgress"
                                        aria-label="Pašalinti priedą"
                                        @click="requestDeleteReviewAttachment(attachment, $event)"
                                    >
                                        Šalinti
                                    </button>
                                    <button
                                        v-if="attachment.kind === 'photo'"
                                        type="button"
                                        class="review-progress-attachment-thumb-btn"
                                        aria-label="Peržiūrėti nuotrauką"
                                        @click="openAttachmentInLightbox(attachment)"
                                    >
                                        <span class="review-progress-attachment-link">
                                            <img :src="attachment.url" alt="" class="review-progress-attachment-thumb" />
                                        </span>
                                    </button>
                                    <button
                                        v-else
                                        type="button"
                                        class="review-progress-attachment-thumb-btn"
                                        aria-label="Peržiūrėti vaizdo įrašą"
                                        @click="openAttachmentInLightbox(attachment)"
                                    >
                                        <span class="review-progress-attachment-link">
                                            <video
                                                class="review-progress-attachment-video review-progress-attachment-video--thumb"
                                                muted
                                                playsinline
                                                :src="attachment.url"
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
            :can-delete-review-attachments="canDeleteReviewAttachments"
            @close="closeAddEntryModal"
        />

        <ConfirmModal
            :open="showDeleteAttachmentConfirm"
            message="Ar tikrai pašalinti šį priedą?"
            confirm-text="Pašalinti"
            cancel-text="Atšaukti"
            footer-layout="column"
            confirm-variant="danger"
            width="400px"
            :z-index="5000"
            :loading="deleteAttachmentInProgress"
            @close="cancelDeleteReviewAttachment"
            @confirm="confirmDeleteReviewAttachment"
        />

        <AttachmentMediaLightbox
            :attachment="reviewAttachmentForLightbox"
            :attachments="reviewAttachmentsForLightbox"
            :z-index="5600"
            @close="closeAttachmentLightbox"
            @change="openAttachmentInLightbox"
        />
    </AuthenticatedLayout>
</template>
