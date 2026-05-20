import { ref, computed, unref } from 'vue'

export function defaultFileUploadSettings() {
    return {
        allow_photo: true,
        allow_video: false,
        max_photo_size_kb: 5120,
        max_photo_files_per_problem: 5,
        max_video_files_per_problem: 1,
        max_video_duration_seconds: 60,
        description_max_chars: 65535,
        accept_attr: 'image/jpeg,image/png',
    }
}

export function useFileUploadQueue(uploadRulesRef, options = {}) {
    const { appendBaseline } = options

    const photoMimes = ['image/jpeg', 'image/png']
    const videoMimes = ['video/mp4', 'video/webm', 'video/quicktime']
    const photoExtensions = ['jpg', 'jpeg', 'png']
    const videoExtensions = ['mp4', 'webm', 'mov']

    const selectedFiles = ref([])
    const fileHint = ref('')
    const fileInputRef = ref(null)

    function extensionFor(file) {
        const name = String(file?.name ?? '')
        const dotIndex = name.lastIndexOf('.')
        return dotIndex === -1 ? '' : name.slice(dotIndex + 1).toLowerCase()
    }

    function fileKind(file) {
        const rules = unref(uploadRulesRef)
        const mimeType = String(file?.type ?? '').toLowerCase()
        const extension = extensionFor(file)

        if ((photoMimes.includes(mimeType) || (!mimeType && photoExtensions.includes(extension))) && rules.allow_photo) {
            return 'photo'
        }
        if ((videoMimes.includes(mimeType) || (!mimeType && videoExtensions.includes(extension))) && rules.allow_video) {
            return 'video'
        }
        return null
    }

    function extensionAllowedForKind(file, kind) {
        const extension = extensionFor(file)
        if (kind === 'photo') return photoExtensions.includes(extension)
        if (kind === 'video') return videoExtensions.includes(extension)
        return false
    }

    function formatKb(kb) {
        return `${kb} KB`
    }

    function countByKind(files, kind) {
        return files.filter((file) => fileKind(file) === kind).length
    }

    function probeImageFile(file) {
        if (typeof window !== 'undefined' && typeof window.createImageBitmap === 'function') {
            return window.createImageBitmap(file).then((bitmap) => {
                bitmap.close?.()
            })
        }

        return new Promise((resolve, reject) => {
            const url = URL.createObjectURL(file)
            const image = new Image()
            const cleanup = () => URL.revokeObjectURL(url)
            image.onload = () => {
                cleanup()
                resolve()
            }
            image.onerror = () => {
                cleanup()
                reject(new Error('image'))
            }
            image.src = url
        })
    }

    function probeVideoDurationSeconds(file) {
        return new Promise((resolve, reject) => {
            const url = URL.createObjectURL(file)
            const video = document.createElement('video')
            video.preload = 'metadata'
            const cleanup = () => {
                URL.revokeObjectURL(url)
            }
            video.onloadedmetadata = () => {
                const d = video.duration
                cleanup()
                if (!Number.isFinite(d) || d <= 0) {
                    reject(new Error('duration'))
                } else {
                    resolve(d)
                }
            }
            video.onerror = () => {
                cleanup()
                reject(new Error('load'))
            }
            video.src = url
        })
    }

    function baseline() {
        const b = appendBaseline ? unref(appendBaseline) : { photos: 0, videos: 0 }
        return {
            photos: Number(b.photos) || 0,
            videos: Number(b.videos) || 0,
        }
    }

    async function handleFileChange(event) {
        fileHint.value = ''
        const input = event.target
        const picked = input.files ? Array.from(input.files) : []
        input.value = ''

        if (!picked.length) return

        const rules = unref(uploadRulesRef)
        const maxPhoto = Math.max(0, Number(rules.max_photo_files_per_problem) || 0)
        const maxVideo = Math.max(0, Number(rules.max_video_files_per_problem) || 0)
        const base = baseline()
        const existing = selectedFiles.value.slice()
        const next = [...existing]

        for (const file of picked) {
            const kind = fileKind(file)
            if (!kind) {
                fileHint.value = rules.allow_video
                    ? 'Leidžiami tik JPEG, PNG arba vaizdo failai.'
                    : 'Leidžiamos tik JPEG arba PNG nuotraukos.'
                break
            }
            if (!extensionAllowedForKind(file, kind)) {
                fileHint.value =
                    kind === 'photo'
                        ? 'Leidžiamos tik JPEG arba PNG nuotraukos.'
                        : 'Netinkamas vaizdo failo formatas.'
                break
            }
            const photos = countByKind(next, 'photo')
            const videos = countByKind(next, 'video')
            if (kind === 'photo' && base.photos + photos >= maxPhoto) {
                fileHint.value = `Galima pridėti ne daugiau kaip ${maxPhoto} nuotraukų.`
                break
            }
            if (kind === 'video' && base.videos + videos >= maxVideo) {
                fileHint.value = 'Per daug vaizdo failų.'
                break
            }
            if (kind === 'photo') {
                const maxPhotoKb = Number(rules.max_photo_size_kb) || 5120
                if (file.size / 1024 > maxPhotoKb) {
                    fileHint.value = `Failas per didelis. Didžiausias dydis - ${formatKb(maxPhotoKb)}.`
                    break
                }
                try {
                    await probeImageFile(file)
                } catch {
                    fileHint.value = 'Nuotraukos nepavyko atidaryti. Pasirinkite JPEG arba PNG failą.'
                    break
                }
            }
            if (kind === 'video') {
                const maxVideoSec = Math.min(
                    60,
                    Math.max(1, Number(rules.max_video_duration_seconds) || 60),
                )
                try {
                    const durationSec = await probeVideoDurationSeconds(file)
                    if (Math.round(durationSec * 10) / 10 > maxVideoSec) {
                        fileHint.value =
                            maxVideoSec >= 60
                                ? 'Vaizdo įrašas turi būti ne ilgesnis kaip 1 min.'
                                : `Vaizdo įrašas turi būti ne ilgesnis kaip ${maxVideoSec} s.`
                        break
                    }
                } catch {
                    fileHint.value = 'Nepavyko patikrinti vaizdo įrašo trukmės.'
                    break
                }
            }
            next.push(file)
        }

        selectedFiles.value = next
    }

    function removeFile(index) {
        fileHint.value = ''
        selectedFiles.value = selectedFiles.value.filter((file, fileIndex) => fileIndex !== index)
    }

    function clearQueue() {
        selectedFiles.value = []
        fileHint.value = ''
    }

    function triggerPicker() {
        fileHint.value = ''
        fileInputRef.value?.click()
    }

    const showFileSection = computed(() => {
        const rules = unref(uploadRulesRef)
        const maxP = Number(rules.max_photo_files_per_problem) || 0
        const maxV = Number(rules.max_video_files_per_problem) || 0
        return (maxP > 0 && rules.allow_photo) || (maxV > 0 && rules.allow_video)
    })

    return {
        selectedFiles,
        fileHint,
        fileInputRef,
        handleFileChange,
        removeFile,
        clearQueue,
        triggerPicker,
        showFileSection,
    }
}
