export const GIS_TRANSFER_SEND_STATUS = {
    PENDING: 'pending',
    FEATURE_CREATED: 'feature_created',
    SENT: 'sent',
    ATTACHMENT_FAILED: 'attachment_failed',
    FAILED: 'failed',
}

export const GIS_TRANSFER_SEND_STATUS_LABELS = {
    [GIS_TRANSFER_SEND_STATUS.PENDING]: 'Ruošiama',
    [GIS_TRANSFER_SEND_STATUS.FEATURE_CREATED]: 'Objektas sukurtas',
    [GIS_TRANSFER_SEND_STATUS.SENT]: 'Išsiųsta',
    [GIS_TRANSFER_SEND_STATUS.ATTACHMENT_FAILED]: 'Priedų klaida',
    [GIS_TRANSFER_SEND_STATUS.FAILED]: 'Klaida',
}

export function gisSendStatusLabel(status) {
    if (status == null || status === '') {
        return 'Nėra'
    }
    return GIS_TRANSFER_SEND_STATUS_LABELS[status] ?? String(status)
}
