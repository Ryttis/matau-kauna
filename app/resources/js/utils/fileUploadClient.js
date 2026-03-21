export function attachmentsToUploadBaseline(attachments = []) {
    let photos = 0
    let videos = 0
    let totalKb = 0
    for (const a of attachments) {
        totalKb += (Number(a.size_bytes) || 0) / 1024
        if (a.kind === 'photo') {
            photos += 1
        } else {
            videos += 1
        }
    }
    return { photos, videos, totalKb }
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

export function formatDetailedFileUploadHint(uploadRules, options = {}) {
    const includeExistingInTotal = options.includeExistingInTotal === true
    const u = uploadRules
    const parts = []
    const maxPhoto = Number(u.max_photo_files_per_problem) || 0
    const maxVideo = Number(u.max_video_files_per_problem) || 0

    if (u.allow_photo && maxPhoto > 0) {
        parts.push(
            `nuotr.: iki ${maxPhoto} vnt., po ${u.max_photo_size_kb} KB (JPEG, PNG)`,
        )
    }
    if (u.allow_video && maxVideo > 0) {
        parts.push(
            maxVideo === 1
                ? `1 vaizdas iki ${u.max_video_size_kb} KB`
                : `vaizdai: iki ${maxVideo} vnt., po ${u.max_video_size_kb} KB`,
        )
    }
    let totalLine = `bendra suma iki ${u.max_total_upload_kb} KB`
    if (includeExistingInTotal) {
        totalLine += ' (įskaitant jau įkeltus)'
    }
    parts.push(totalLine)
    return parts.join('; ')
}

export function formatShortFileUploadLimitsLine(uploadRules) {
    const u = uploadRules
    const maxPhoto = Number(u.max_photo_files_per_problem) || 0
    const maxVideo = Number(u.max_video_files_per_problem) || 0
    const segments = []
    if (u.allow_photo && maxPhoto > 0) {
        segments.push(`Nuotraukos ${maxPhoto} max.`)
    }
    if (u.allow_video && maxVideo > 0) {
        segments.push(`Video ${maxVideo} max.`)
    }
    return segments.join(' ')
}
