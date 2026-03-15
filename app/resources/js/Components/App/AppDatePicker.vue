<script setup>
import { computed, ref, watch, nextTick } from 'vue'

const MONTHS_LT = [
    'Sausis', 'Vasaris', 'Kovas', 'Balandis', 'Gegužė', 'Birželis',
    'Liepa', 'Rugpjūtis', 'Rugsėjis', 'Spalis', 'Lapkritis', 'Gruodis'
]

const props = defineProps({
    modelValue: {
        type: String,
        default: ''
    },
    label: {
        type: String,
        default: ''
    },
    placeholder: {
        type: String,
        default: 'Pasirinkite datą'
    },
    minDate: {
        type: String,
        default: null
    }
})

const emit = defineEmits(['update:modelValue'])

const isOpen = ref(false)
const triggerRef = ref(null)
const dropdownPosition = ref({ top: 0, left: 0 })
const viewYear = ref(new Date().getFullYear())
const viewMonth = ref(new Date().getMonth())

const DROPDOWN_Z_INDEX = 10050

const displayValue = computed(() => {
    if (!props.modelValue) return ''
    const d = parseDate(props.modelValue)
    if (!d) return props.modelValue
    return `${String(d.getDate()).padStart(2, '0')}.${String(d.getMonth() + 1).padStart(2, '0')}.${d.getFullYear()}`
})

function parseDate(str) {
    if (!str || str.length < 10) return null
    const [y, m, day] = str.slice(0, 10).split('-').map(Number)
    if (!y || !m || !day) return null
    const d = new Date(y, m - 1, day)
    return isNaN(d.getTime()) ? null : d
}

function toYmd(d) {
    if (!d) return ''
    const y = d.getFullYear()
    const m = String(d.getMonth() + 1).padStart(2, '0')
    const day = String(d.getDate()).padStart(2, '0')
    return `${y}-${m}-${day}`
}

const calendarDays = computed(() => {
    const first = new Date(viewYear.value, viewMonth.value, 1)
    const last = new Date(viewYear.value, viewMonth.value + 1, 0)
    const startPad = (first.getDay() + 6) % 7
    const daysInMonth = last.getDate()
    const rows = []
    let row = []
    for (let i = 0; i < startPad; i++) row.push(null)
    for (let d = 1; d <= daysInMonth; d++) {
        row.push(new Date(viewYear.value, viewMonth.value, d))
        if (row.length === 7) {
            rows.push(row)
            row = []
        }
    }
    if (row.length) {
        while (row.length < 7) row.push(null)
        rows.push(row)
    }
    return rows
})

const viewMonthLabel = computed(() => `${MONTHS_LT[viewMonth.value]} ${viewYear.value}`)

const minDateObj = computed(() => props.minDate ? parseDate(props.minDate) : null)

function isSelected(d) {
    if (!d || !props.modelValue) return false
    return toYmd(d) === props.modelValue
}

function isDisabled(d) {
    if (!d) return true
    if (minDateObj.value && d < minDateObj.value) return true
    return false
}

function selectDay(d) {
    if (!d || isDisabled(d)) return
    emit('update:modelValue', toYmd(d))
    isOpen.value = false
}

function prevMonth() {
    if (viewMonth.value === 0) {
        viewMonth.value = 11
        viewYear.value--
    } else {
        viewMonth.value--
    }
}

function nextMonth() {
    if (viewMonth.value === 11) {
        viewMonth.value = 0
        viewYear.value++
    } else {
        viewMonth.value++
    }
}

function open() {
    const d = parseDate(props.modelValue) || new Date()
    viewYear.value = d.getFullYear()
    viewMonth.value = d.getMonth()
    isOpen.value = true
    nextTick(() => updateDropdownPosition())
}

function updateDropdownPosition() {
    if (!triggerRef.value) return
    const rect = triggerRef.value.getBoundingClientRect()
    dropdownPosition.value = {
        top: rect.bottom + 6,
        left: rect.left,
    }
}

function close() {
    isOpen.value = false
}

watch(isOpen, (open) => {
    if (!open) return
    const d = parseDate(props.modelValue) || new Date()
    viewYear.value = d.getFullYear()
    viewMonth.value = d.getMonth()
    nextTick(() => updateDropdownPosition())
})

defineExpose({ open, close })
</script>

<template>
    <div class="app-date-picker">
        <label v-if="label" class="app-date-picker-label">{{ label }}</label>
        <div class="app-date-picker-trigger-wrap">
            <button
                ref="triggerRef"
                type="button"
                class="app-date-picker-trigger"
                :class="{ open: isOpen }"
                @click="open"
            >
                <span class="app-date-picker-value" :class="{ 'is-placeholder': !modelValue }">{{ displayValue || placeholder }}</span>
                <span class="app-date-picker-icon" aria-hidden="true">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <rect x="3" y="4" width="18" height="18" rx="2" ry="2"/>
                        <line x1="16" y1="2" x2="16" y2="6"/>
                        <line x1="8" y1="2" x2="8" y2="6"/>
                        <line x1="3" y1="10" x2="21" y2="10"/>
                    </svg>
                </span>
            </button>
            <Teleport to="body">
                <Transition name="calendar">
                    <div
                        v-if="isOpen"
                        class="app-date-picker-dropdown"
                        role="dialog"
                        aria-label="Kalendorius"
                        :style="{
                            position: 'fixed',
                            top: `${dropdownPosition.top}px`,
                            left: `${dropdownPosition.left}px`,
                            zIndex: DROPDOWN_Z_INDEX,
                        }"
                    >
                        <div class="app-date-picker-nav">
                            <button type="button" class="app-date-picker-nav-btn" aria-label="Ankstesnis mėnuo" @click="prevMonth">‹</button>
                            <span class="app-date-picker-nav-title">{{ viewMonthLabel }}</span>
                            <button type="button" class="app-date-picker-nav-btn" aria-label="Kitas mėnuo" @click="nextMonth">›</button>
                        </div>
                        <div class="app-date-picker-weekdays">
                            <span v-for="w in ['Pr', 'A', 'T', 'K', 'Pn', 'Š', 'S']" :key="w" class="app-date-picker-weekday">{{ w }}</span>
                        </div>
                        <div class="app-date-picker-grid">
                            <template v-for="(row, ri) in calendarDays" :key="ri">
                                <button
                                    v-for="(d, di) in row"
                                    :key="di"
                                    type="button"
                                    class="app-date-picker-day"
                                    :class="{
                                        empty: !d,
                                        selected: d && isSelected(d),
                                        disabled: d && isDisabled(d)
                                    }"
                                    :disabled="!d || isDisabled(d)"
                                    @click="selectDay(d)"
                                >
                                    {{ d ? d.getDate() : '' }}
                                </button>
                            </template>
                        </div>
                    </div>
                </Transition>
                <div
                    v-if="isOpen"
                    class="app-date-picker-backdrop"
                    aria-hidden="true"
                    :style="{ zIndex: DROPDOWN_Z_INDEX - 1 }"
                    @click="close"
                />
            </Teleport>
        </div>
    </div>
</template>

<style scoped>
.app-date-picker {
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.app-date-picker-label {
    font-size: 16px;
    font-weight: 600;
    color: #333;
    font-family: 'Inter', sans-serif;
}

.app-date-picker-trigger-wrap {
    position: relative;
    width: 100%;
    max-width: 290px;
    display: inline-block;
}

.app-date-picker-trigger {
    width: 100%;
    height: 40px;
    padding: 0 40px 0 16px;
    border: 1px solid #e0e0e0;
    border-radius: 15px;
    font-size: 16px;
    font-family: 'Inter', sans-serif;
    color: #333;
    background-color: #f9f9f9;
    box-sizing: border-box;
    display: flex;
    align-items: center;
    cursor: pointer;
    text-align: left;
}

.app-date-picker-trigger:hover {
    background-color: #fff;
    border-color: #ccc;
}

.app-date-picker-trigger.open,
.app-date-picker-trigger:focus {
    outline: none;
    border-color: #1946A0;
    background-color: #fff;
}

.app-date-picker-value {
    flex: 1;
}

.app-date-picker-trigger .app-date-picker-value:empty::before {
    content: attr(data-placeholder);
    color: #999;
}

.app-date-picker-icon {
    position: absolute;
    right: 12px;
    top: 50%;
    transform: translateY(-50%);
    pointer-events: none;
    width: 18px;
    height: 18px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #666;
}

.app-date-picker-icon svg {
    width: 100%;
    height: 100%;
}

.app-date-picker-backdrop {
    position: fixed;
    inset: 0;
    background: transparent;
}

.app-date-picker-dropdown {
    background: #fff;
    border: 1px solid #e0e0e0;
    border-radius: 15px;
    box-shadow: 0 4px 16px rgba(0, 0, 0, 0.12);
    padding: 12px;
    min-width: 240px;
    font-family: 'Inter', sans-serif;
}

.app-date-picker-nav {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 10px;
}

.app-date-picker-nav-title {
    font-size: 14px;
    font-weight: 600;
    color: #333;
}

.app-date-picker-nav-btn {
    width: 28px;
    height: 28px;
    border: none;
    border-radius: 15px;
    background: #f0f0f0;
    color: #333;
    font-size: 18px;
    line-height: 1;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
}

.app-date-picker-nav-btn:hover {
    background: #e0e0e0;
}

.app-date-picker-weekdays {
    display: grid;
    grid-template-columns: repeat(7, 1fr);
    gap: 2px;
    margin-bottom: 6px;
}

.app-date-picker-weekday {
    font-size: 11px;
    color: #666;
    text-align: center;
}

.app-date-picker-grid {
    display: grid;
    grid-template-columns: repeat(7, 1fr);
    gap: 2px;
}

.app-date-picker-day {
    width: 28px;
    height: 28px;
    margin: 0 auto;
    border: none;
    border-radius: 5px;
    background: transparent;
    font-size: 13px;
    color: #333;
    cursor: pointer;
}

.app-date-picker-day.empty {
    cursor: default;
}

.app-date-picker-day:not(.empty):hover:not(.disabled) {
    background: #e8eef7;
}

.app-date-picker-day.selected {
    background: #1946A0;
    color: #fff;
}

.app-date-picker-day.disabled {
    color: #bbb;
    cursor: not-allowed;
}

.calendar-enter-active,
.calendar-leave-active {
    transition: opacity 0.15s ease, transform 0.15s ease;
}

.calendar-enter-from,
.calendar-leave-to {
    opacity: 0;
    transform: scale(0.96);
}
</style>
