export const DVS_TRANSFER_SEND_STATUS = {
    PENDING: 'pending',
    SENT: 'sent',
    FAILED: 'failed',
    RESOLVED: 'resolved',
}

export const DVS_TRANSFER_SEND_STATUS_LABELS = {
    [DVS_TRANSFER_SEND_STATUS.PENDING]: 'Ruošiama',
    [DVS_TRANSFER_SEND_STATUS.SENT]: 'Išsiųsta',
    [DVS_TRANSFER_SEND_STATUS.FAILED]: 'Klaida',
    [DVS_TRANSFER_SEND_STATUS.RESOLVED]: 'Atsakyta',
}

export const DVS_COMPLAINT_API_LABELS = {
    gaunami_dokumentai: 'GaunamiDokumentai',
    pilieciu_laiskai: 'PilieciuLaiskai',
}

export function dvsSendStatusLabel(status) {
    if (status == null || status === '') {
        return 'Nėra'
    }
    return DVS_TRANSFER_SEND_STATUS_LABELS[status] ?? String(status)
}

export function dvsComplaintApiLabel(api) {
    if (api == null || api === '') {
        return '-'
    }
    return DVS_COMPLAINT_API_LABELS[api] ?? String(api)
}
