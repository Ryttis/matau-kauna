export function attachmentsToUploadBaseline(attachments = []) {
    let photos = 0
    let videos = 0
    for (const attachment of attachments) {
        if (attachment.kind === 'photo') {
            photos += 1
        } else {
            videos += 1
        }
    }
    return { photos, videos }
}

export function fileUploadPickerLabel(uploadRules) {
    const allowPhoto = uploadRules.allow_photo
    const allowVideo = uploadRules.allow_video
    if (allowPhoto && allowVideo) {
        return 'Pridėkite nuotrauką arba vaizdo įrašą'
    }
    if (allowVideo) {
        return 'Pridėkite vaizdo įrašą'
    }
    return 'Pridėkite nuotrauką'
}

export function formatDetailedFileUploadHint(uploadRules) {
    const hintParts = []
    const maxPhotoFiles = Number(uploadRules.max_photo_files_per_problem) || 0
    const maxVideoFiles = Number(uploadRules.max_video_files_per_problem) || 0

    if (uploadRules.allow_photo && maxPhotoFiles > 0) {
        hintParts.push(`nuotr.: iki ${maxPhotoFiles} vnt., po ${uploadRules.max_photo_size_kb} KB (JPEG, PNG)`)
    }
    if (uploadRules.allow_video && maxVideoFiles > 0) {
        const maxSec = Math.min(60, Math.max(1, Number(uploadRules.max_video_duration_seconds) || 60))
        const durLabel = maxSec >= 60 ? '1 min. trukmės' : `${maxSec} s trukmės`
        hintParts.push(
            maxVideoFiles === 1
                ? `1 vaizdas iki ${durLabel}`
                : `vaizdai: iki ${maxVideoFiles} vnt., iki ${durLabel}`,
        )
    }
    if (!hintParts.length) {
        return ''
    }

    return hintParts.join('; ')
}

export function formatShortFileUploadLimitsLine(uploadRules) {
    const maxPhotoFiles = Number(uploadRules.max_photo_files_per_problem) || 0
    const maxVideoFiles = Number(uploadRules.max_video_files_per_problem) || 0
    const limitSegments = []
    if (uploadRules.allow_photo && maxPhotoFiles > 0) {
        limitSegments.push(`Nuotraukos ${maxPhotoFiles} max.`)
    }
    if (uploadRules.allow_video && maxVideoFiles > 0) {
        limitSegments.push(`Video ${maxVideoFiles} max.`)
    }
    return limitSegments.join(' ')
}
