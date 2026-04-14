import { truncateDisplay, DEFAULT_DISPLAY_MAX_LENGTH } from '@/utils/textDisplay'

export const MAP_POPUP_TEXT_MAX = DEFAULT_DISPLAY_MAX_LENGTH

export function truncateMapPopupText(text, maxLen = MAP_POPUP_TEXT_MAX) {
    return truncateDisplay(text, maxLen)
}
