<script setup>
import { computed, ref, watch, onUnmounted } from 'vue'
import { ICON_ARROW } from '@/constants/ui/icons'
import { truncateDisplay, DEFAULT_DISPLAY_MAX_LENGTH } from '@/utils/textDisplay'
import { textMatchesQuery } from '@/utils/searchMatch'

const props = defineProps({
    modelValue: {
        type: String,
        default: '',
    },
    options: {
        type: Array,
        default: () => [],
    },
    label: {
        type: String,
        default: '',
    },
    labelPosition: {
        type: String,
        default: 'top',
        validator: (v) => ['top', 'left'].includes(v),
    },
    emptyOptionLabel: {
        type: String,
        default: 'Visi adresai',
    },
    searchPlaceholder: {
        type: String,
        default: 'Ieškoti adreso…',
    },
    maxDisplayLength: {
        type: Number,
        default: DEFAULT_DISPLAY_MAX_LENGTH,
    },
    disabled: {
        type: Boolean,
        default: false,
    },
    noResultsText: {
        type: String,
        default: 'Nieko nerasta.',
    },
    showEmptyOption: {
        type: Boolean,
        default: true,
    },
    listMaxHeight: {
        type: String,
        default: '280px',
    },
})

const emit = defineEmits(['update:modelValue'])

const rootRef = ref(null)
const inputRef = ref(null)
const isOpen = ref(false)
const query = ref('')
let blurTimer = null

const selectedOption = computed(() => {
    const v = props.modelValue
    if (v === '' || v == null) return null
    return props.options.find((o) => String(o.value) === String(v)) ?? null
})

const closedDisplay = computed(() => {
    if (!selectedOption.value) return props.emptyOptionLabel
    return truncateDisplay(selectedOption.value.label, props.maxDisplayLength)
})

const closedInputTitle = computed(() => {
    if (isOpen.value || !selectedOption.value) return ''
    return selectedOption.value.label
})

const filteredOptions = computed(() =>
    props.options.filter(
        (o) =>
            textMatchesQuery(o.label, query.value)
            || textMatchesQuery(String(o.value ?? ''), query.value),
    ),
)

function clearBlurTimer() {
    if (blurTimer != null) {
        clearTimeout(blurTimer)
        blurTimer = null
    }
}

function close() {
    clearBlurTimer()
    isOpen.value = false
    query.value = ''
}

function open() {
    if (props.disabled) return
    clearBlurTimer()
    isOpen.value = true
    query.value = ''
}

function onRootClickOutside(event) {
    if (rootRef.value && !rootRef.value.contains(event.target)) {
        close()
    }
}

watch(isOpen, (openVal) => {
    if (openVal) {
        document.addEventListener('click', onRootClickOutside)
    } else {
        document.removeEventListener('click', onRootClickOutside)
    }
})

onUnmounted(() => {
    document.removeEventListener('click', onRootClickOutside)
    clearBlurTimer()
})

function onFocus() {
    if (props.disabled) return
    open()
}

function onInput(e) {
    if (!isOpen.value) return
    query.value = e.target.value
}

function onBlur() {
    blurTimer = window.setTimeout(() => {
        close()
        blurTimer = null
    }, 180)
}

function onListMouseDown(e) {
    e.preventDefault()
    clearBlurTimer()
}

function selectValue(value) {
    emit('update:modelValue', value)
    close()
    inputRef.value?.blur()
}

function selectAll() {
    selectValue('')
}

function onKeydown(e) {
    if (e.key === 'Escape') {
        e.preventDefault()
        close()
        inputRef.value?.blur()
    }
}

function clearSelection(e) {
    e.stopPropagation()
    emit('update:modelValue', '')
    query.value = ''
}

watch(
    () => props.modelValue,
    () => {
        if (!isOpen.value) query.value = ''
    },
)
</script>

<template>
    <div
        ref="rootRef"
        class="searchable-select"
        :class="[`searchable-select--label-${labelPosition}`, { 'searchable-select--disabled': disabled }]"
    >
        <label v-if="label" class="searchable-select__label">{{ label }}</label>
        <div class="searchable-select__control">
            <div class="searchable-select__input-wrap">
                <input
                    ref="inputRef"
                    class="searchable-select__input"
                    :class="{ 'searchable-select__input--with-clear': !disabled && modelValue && !isOpen }"
                    type="text"
                    :value="isOpen ? query : closedDisplay"
                    :placeholder="isOpen ? searchPlaceholder : emptyOptionLabel"
                    :title="closedInputTitle"
                    :disabled="disabled"
                    autocomplete="off"
                    role="combobox"
                    :aria-expanded="isOpen"
                    aria-autocomplete="list"
                    @focus="onFocus"
                    @blur="onBlur"
                    @input="onInput"
                    @keydown="onKeydown"
                />
                <button
                    v-if="!disabled && modelValue && !isOpen && showEmptyOption"
                    type="button"
                    class="searchable-select__clear"
                    aria-label="Išvalyti"
                    tabindex="-1"
                    @mousedown.prevent
                    @click="clearSelection"
                >
                    ×
                </button>
                <span class="searchable-select__chevron" aria-hidden="true">
                    <img :src="ICON_ARROW" alt="" class="searchable-select__chevron-img" />
                </span>
            </div>
            <div
                v-show="isOpen"
                class="searchable-select__dropdown"
                role="listbox"
                :style="{ maxHeight: listMaxHeight }"
                @mousedown="onListMouseDown"
            >
                <button
                    v-if="showEmptyOption"
                    type="button"
                    class="searchable-select__option searchable-select__option--all"
                    :class="{ 'searchable-select__option--active': modelValue === '' }"
                    role="option"
                    :aria-selected="modelValue === ''"
                    @click="selectAll"
                >
                    {{ emptyOptionLabel }}
                </button>
                <div v-if="filteredOptions.length === 0" class="searchable-select__empty">
                    {{ noResultsText }}
                </div>
                <template v-else>
                    <button
                        v-for="opt in filteredOptions"
                        :key="opt.value"
                        type="button"
                        class="searchable-select__option"
                        :class="{ 'searchable-select__option--active': String(modelValue) === String(opt.value) }"
                        role="option"
                        :aria-selected="String(modelValue) === String(opt.value)"
                        :title="opt.label"
                        @click="selectValue(String(opt.value))"
                    >
                        {{ truncateDisplay(opt.label, maxDisplayLength) }}
                    </button>
                </template>
            </div>
        </div>
    </div>
</template>

<style scoped>
.searchable-select {
    display: flex;
    flex-direction: column;
    gap: 8px;
    min-width: 0;
    flex: 1;
}

.searchable-select--label-left {
    flex-direction: row;
    align-items: center;
    gap: 12px;
}

.searchable-select--label-left .searchable-select__label {
    margin-bottom: 0;
    white-space: nowrap;
}

.searchable-select--label-left .searchable-select__control {
    flex: 1;
    min-width: 0;
}

.searchable-select__label {
    font-size: 16px;
    font-weight: 600;
    color: #333333;
    font-family: var(--font-family-base, 'Open Sans', sans-serif);
    line-height: 1.5;
    margin: 0;
}

.searchable-select__control {
    position: relative;
}

.searchable-select__input-wrap {
    position: relative;
    display: flex;
    align-items: center;
}

.searchable-select__input {
    width: 100%;
    box-sizing: border-box;
    padding: 10px 44px 10px 14px;
    font-size: 14px;
    font-family: var(--font-family-base, 'Open Sans', sans-serif);
    border: 1px solid var(--color-border, #e0e0e0);
    border-radius: 5px;
    background: #fff;
    color: var(--color-text, #333);
    min-height: 44px;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.searchable-select__input--with-clear {
    padding-right: 78px;
}

.searchable-select__input:focus {
    outline: none;
    border-color: var(--color-primary, #1946a0);
    box-shadow: 0 0 0 1px var(--color-primary, #1946a0);
}

.searchable-select__input::placeholder {
    color: #888;
}

.searchable-select--disabled .searchable-select__input {
    opacity: 0.65;
    cursor: not-allowed;
}

.searchable-select__clear {
    position: absolute;
    right: 36px;
    top: 50%;
    transform: translateY(-50%);
    width: 28px;
    height: 28px;
    padding: 0;
    border: none;
    background: transparent;
    color: #666;
    font-size: 22px;
    line-height: 1;
    cursor: pointer;
    border-radius: 4px;
}

.searchable-select__clear:hover {
    background: #f0f0f0;
    color: #333;
}

.searchable-select__chevron {
    position: absolute;
    right: 12px;
    top: 50%;
    transform: translateY(-50%);
    pointer-events: none;
    display: flex;
    align-items: center;
}

.searchable-select__chevron-img {
    width: 14px;
    height: 14px;
    display: block;
    opacity: 0.55;
}

.searchable-select__dropdown {
    position: absolute;
    left: 0;
    right: 0;
    top: calc(100% + 4px);
    overflow-y: auto;
    z-index: 1200;
    background: #fff;
    border: 1px solid var(--color-border, #e0e0e0);
    border-radius: 5px;
    box-shadow: 0 8px 20px rgba(0, 0, 0, 0.12);
}

.searchable-select__option {
    display: block;
    width: 100%;
    text-align: left;
    padding: 10px 14px;
    font-size: 14px;
    font-family: var(--font-family-base, 'Open Sans', sans-serif);
    color: var(--color-text, #333);
    background: #fff;
    border: none;
    border-bottom: 1px solid #f0f0f0;
    cursor: pointer;
    line-height: 1.4;
    word-break: break-word;
}

.searchable-select__option:last-child {
    border-bottom: none;
}

.searchable-select__option:hover,
.searchable-select__option:focus-visible {
    background: #f6f7f9;
    outline: none;
}

.searchable-select__option--active {
    font-weight: 600;
    background: #e8eef7;
}

.searchable-select__option--all {
    font-weight: 500;
}

.searchable-select__empty {
    padding: 12px 14px;
    font-size: 14px;
    color: #666;
}
</style>
