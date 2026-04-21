import { ref, watch } from 'vue'
import axios from 'axios'
import { router } from '@inertiajs/vue3'

export function useReviewProgressPage(props) {
    const showAddEntryModal = ref(false)
    const editingEntry = ref(null)

    const openAddEntryModal = () => {
        editingEntry.value = null
        showAddEntryModal.value = true
    }

    const openEditEntryModal = (entry) => {
        editingEntry.value = entry
        showAddEntryModal.value = true
    }

    const closeAddEntryModal = () => {
        showAddEntryModal.value = false
        editingEntry.value = null
    }

    const currentUserMayEditEntry = (entry) => {
        if (props.isSystemAdmin) return true
        if (entry.user_id && props.authUserId) return entry.user_id === props.authUserId
        return false
    }

    const problemIsWatched = ref(props.isWatched)
    const watchToggleInProgress = ref(false)

    watch(
        () => props.isWatched,
        (isWatched) => { problemIsWatched.value = isWatched },
    )

    async function toggleProblemWatchSubscription() {
        if (!props.watchProblemId || watchToggleInProgress.value) return
        watchToggleInProgress.value = true
        try {
            const { data } = await axios.post(route('problems.watch.toggle', props.watchProblemId))
            problemIsWatched.value = !!data.watched
        } finally {
            watchToggleInProgress.value = false
        }
    }

    const goBackFromReviewProgress = () => {
        const targetUrl = props.backUrl || route('problems.show', props.problem.id)
        router.visit(targetUrl)
    }

    const showDeleteAttachmentConfirm = ref(false)
    const attachmentQueuedForDelete = ref(null)
    const deleteAttachmentInProgress = ref(false)

    function requestDeleteReviewAttachment(attachment, event) {
        event?.stopPropagation?.()
        event?.preventDefault?.()
        if (!props.canDeleteReviewAttachments || deleteAttachmentInProgress.value) return
        attachmentQueuedForDelete.value = attachment
        showDeleteAttachmentConfirm.value = true
    }

    function cancelDeleteReviewAttachment() {
        if (deleteAttachmentInProgress.value) return
        showDeleteAttachmentConfirm.value = false
        attachmentQueuedForDelete.value = null
    }

    function confirmDeleteReviewAttachment() {
        const attachment = attachmentQueuedForDelete.value
        if (!attachment || !props.problem?.id) return
        deleteAttachmentInProgress.value = true
        router.delete(
            route('problems.reviewProgress.attachments.destroy', {
                problem: props.problem.id,
                attachment: attachment.id,
            }),
            {
                preserveScroll: true,
                onFinish: () => {
                    deleteAttachmentInProgress.value = false
                    showDeleteAttachmentConfirm.value = false
                    attachmentQueuedForDelete.value = null
                },
            },
        )
    }

    const reviewAttachmentForLightbox = ref(null)

    function openAttachmentInLightbox(attachment) {
        reviewAttachmentForLightbox.value = attachment
    }

    function closeAttachmentLightbox() {
        reviewAttachmentForLightbox.value = null
    }

    return {
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
        openAttachmentInLightbox,
        closeAttachmentLightbox,
    }
}
