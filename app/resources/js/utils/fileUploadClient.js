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
    if (!uploadRules?.allow_photo && !uploadRules?.allow_video) {
        return ''
    }
    return 'Pridėkite failą'
}

function formatLabels(formats, fallback) {
    const labels = Array.isArray(formats)
        ? formats.map((format) => String(format?.label ?? '').trim()).filter(Boolean)
        : []

    return labels.length ? labels.join(', ') : fallback
}

export function formatDetailedFileUploadHint(uploadRules) {
    const hintParts = []
    const maxPhotoFiles = Number(uploadRules.max_photo_files_per_problem) || 0
    const maxVideoFiles = Number(uploadRules.max_video_files_per_problem) || 0

    if (uploadRules.allow_photo && maxPhotoFiles > 0) {
        hintParts.push(
            `nuotr.: iki ${maxPhotoFiles} vnt., po ${uploadRules.max_photo_size_kb} KB (${formatLabels(uploadRules.photo_formats, 'JPEG, PNG')})`,
        )
    }
    if (uploadRules.allow_video && maxVideoFiles > 0) {
        const maxSec = Math.min(60, Math.max(1, Number(uploadRules.max_video_duration_seconds) || 60))
        const durLabel = maxSec >= 60 ? '1 min. trukmės' : `${maxSec} s trukmės`
        const formats = formatLabels(uploadRules.video_formats, 'MP4, WebM, MOV')
        const sizeKb = Number(uploadRules.max_video_size_kb) || 51200
        hintParts.push(
            maxVideoFiles === 1
                ? `1 vaizdas iki ${durLabel}, po ${sizeKb} KB (${formats})`
                : `vaizdai: iki ${maxVideoFiles} vnt., iki ${durLabel}, po ${sizeKb} KB (${formats})`,
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
