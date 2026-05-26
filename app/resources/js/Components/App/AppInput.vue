<script setup>
import { computed, ref } from 'vue'
import { ICON_PENCIL } from '@/constants/ui/icons'

const datalistId = ref(`datalist-${Math.random().toString(36).slice(2, 11)}`)

const props = defineProps({
    modelValue: {
        type: [String, Number],
        default: ''
    },
    type: {
        type: String,
        default: 'text'
    },
    placeholder: {
        type: String,
        default: ''
    },
    label: {
        type: String,
        default: ''
    },
    showIcon: {
        type: Boolean,
        default: false
    },
    iconSrc: {
        type: String,
        default: ICON_PENCIL
    },
    iconAlt: {
        type: String,
        default: 'Icon'
    },
    variant: {
        type: String,
        default: 'default',
        validator: (value) => ['default', 'filter'].includes(value)
    },
    options: {
        type: Array,
        default: () => []
    },
    asTextarea: {
        type: Boolean,
        default: false
    },
    rows: {
        type: Number,
        default: 3
    },
    readonly: {
        type: Boolean,
        default: false
    },
    autocomplete: {
        type: String,
        default: undefined,
    },
    required: {
        type: Boolean,
        default: false,
    },
})

const emit = defineEmits(['update:modelValue'])

const value = computed({
    get: () => props.modelValue,
    set: (val) => emit('update:modelValue', val)
})

const isPassword = computed(() => props.type === 'password')
const isPasswordVisible = ref(false)
const effectiveType = computed(() =>
    isPassword.value ? (isPasswordVisible.value ? 'text' : 'password') : props.type
)
const togglePasswordVisibility = () => {
    isPasswordVisible.value = !isPasswordVisible.value
}

const inputClass = computed(() => {
    return props.variant === 'filter' ? 'app-input-filter' : 'app-input-edit'
})

const wrapperClass = computed(() => {
    const isFilter = props.variant === 'filter'
    const withIcon = isFilter && props.showIcon

    const classes = [
        isFilter ? 'app-input-wrapper-filter' : 'app-input-wrapper-edit',
        withIcon ? 'app-input-wrapper-filter--with-icon' : '',
    ]

    return classes.filter(Boolean).join(' ')
})
</script>

<template>
    <div class="app-input-group">
        <label v-if="label" class="app-input-label">
            {{ label }}<span v-if="required" class="app-input-required"> *</span>
        </label>

        <div v-if="!asTextarea" :class="wrapperClass">
            <template v-if="variant === 'filter' && showIcon">
                <input
                    v-model="value"
                    :type="type"
                    :placeholder="placeholder"
                    :class="inputClass"
                    :list="options.length > 0 ? datalistId : undefined"
                    :readonly="readonly"
                    :autocomplete="autocomplete"
                    class="app-input-filter-field"
                />
                <datalist v-if="options.length > 0" :id="datalistId">
                    <option v-for="option in options" :key="option" :value="option" />
                </datalist>
                <div class="app-input-icon-slot">
                    <span class="app-input-icon-box">
                        <img :src="iconSrc" :alt="iconAlt" class="app-input-icon" />
                    </span>
                </div>
            </template>
            <template v-else>
                <input
                    v-model="value"
                    :type="effectiveType"
                    :placeholder="placeholder"
                    :class="inputClass"
                    :list="options.length > 0 ? datalistId : undefined"
                    :readonly="readonly"
                    :autocomplete="autocomplete"
                />
                <datalist v-if="options.length > 0" :id="datalistId">
                    <option v-for="option in options" :key="option" :value="option" />
                </datalist>
                <button
                    v-if="isPassword"
                    type="button"
                    class="app-input-eye-button"
                    :aria-label="isPasswordVisible ? 'Slėpti slaptažodį' : 'Rodyti slaptažodį'"
                    @click="togglePasswordVisibility"
                >
                    <svg
                        v-if="!isPasswordVisible"
                        xmlns="http://www.w3.org/2000/svg"
                        width="18"
                        height="18"
                        viewBox="0 0 24 24"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="2"
                        stroke-linecap="round"
                        stroke-linejoin="round"
                    >
                        <path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7-10-7-10-7Z" />
                        <circle cx="12" cy="12" r="3" />
                    </svg>
                    <svg
                        v-else
                        xmlns="http://www.w3.org/2000/svg"
                        width="18"
                        height="18"
                        viewBox="0 0 24 24"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="2"
                        stroke-linecap="round"
                        stroke-linejoin="round"
                    >
                        <path d="M9.88 9.88a3 3 0 1 0 4.24 4.24" />
                        <path d="M10.73 5.08A10.43 10.43 0 0 1 12 5c7 0 10 7 10 7a13.16 13.16 0 0 1-1.67 2.68" />
                        <path d="M6.61 6.61A13.526 13.526 0 0 0 2 12s3 7 10 7a9.74 9.74 0 0 0 5.39-1.61" />
                        <line x1="2" y1="2" x2="22" y2="22" />
                    </svg>
                </button>
                <img v-else-if="showIcon" :src="iconSrc" :alt="iconAlt" class="app-input-icon" />
            </template>
        </div>

        <div v-else-if="asTextarea" :class="wrapperClass">
            <textarea
                v-model="value"
                :placeholder="placeholder"
                :rows="rows"
                :class="inputClass"
                :readonly="readonly"
                :autocomplete="autocomplete"
            ></textarea>
            <img v-if="showIcon" :src="iconSrc" :alt="iconAlt" class="app-input-icon app-input-icon-textarea" />
        </div>
    </div>
</template>

<style scoped>
.app-input-group {
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.app-input-label {
    font-size: 16px;
    font-weight: 600;
    color: #333;
    font-family: var(--font-family-base, 'Open Sans', sans-serif);
}

.app-input-required {
    color: var(--color-danger, #b42318);
}

.app-input-wrapper-edit {
    position: relative;
}

.app-input-wrapper-filter {
    position: relative;
}

.app-input-wrapper-filter--with-icon {
    display: flex;
    align-items: center;
    border-radius: var(--control-radius, 15px);
    border: 1px solid var(--color-border, #e5e5e5);
    overflow: visible;
    background: #ffffff;
    box-sizing: border-box;
    min-height: var(--control-height, 45px);
}

.app-input-wrapper-filter--with-icon:focus-within {
    border-color: var(--color-primary, #1946A0);
    outline: none;
}

.app-input-wrapper-filter--with-icon .app-input-filter-field {
    flex: 1;
    min-width: 0;
    border: none;
    border-radius: var(--control-radius, 15px) 0 0 var(--control-radius, 15px);
    background: #ffffff;
    padding: 0 12px 0 16px;
    height: var(--control-height, 45px);
    font-size: 16px;
    font-family: 'Open Sans', sans-serif;
    color: #333333;
    box-sizing: border-box;
}

.app-input-wrapper-filter--with-icon .app-input-filter-field::placeholder {
    color: #999999;
    font-style: italic;
}

.app-input-wrapper-filter--with-icon .app-input-filter-field:focus {
    outline: none;
}

.app-input-wrapper-filter--with-icon .app-input-icon-slot {
    flex-shrink: 0;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 6px 6px 6px 0;
    background: transparent;
}

.app-input-wrapper-filter--with-icon .app-input-icon-box {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 48px;
    height: 48px;
    border-radius: 0;
    background: transparent;
    flex-shrink: 0;
    border: none;
    box-shadow: none;
}

.app-input-wrapper-filter--with-icon .app-input-icon-slot .app-input-icon {
    position: static;
    transform: none;
    width: 15px;
    height: 15px;
    display: block;
    object-fit: contain;
    background: transparent;
    border: none;
}

.app-input-edit {
    width: 100%;
    height: var(--control-height, 45px);
    padding: 0 40px 0 16px;
    border: 1px solid var(--color-border, #e0e0e0);
    border-radius: var(--control-radius, 15px);
    font-size: 16px;
    font-family: 'Open Sans', sans-serif;
    color: #333;
    background-color: #f9f9f9;
    box-sizing: border-box;
    -webkit-appearance: none;
    -moz-appearance: none;
    appearance: none;
}

.app-input-edit:focus {
    outline: none;
    border-color: var(--color-primary, #1946A0);
    background-color: #fff;
}

.app-input-edit::-webkit-calendar-picker-indicator {
    display: none;
    -webkit-appearance: none;
}

.app-input-edit::-webkit-list-button,
.app-input-edit::-webkit-calendar-picker-indicator {
    display: none !important;
    -webkit-appearance: none !important;
}

input[list]::-webkit-calendar-picker-indicator {
    display: none !important;
}

input[list] {
    -webkit-appearance: none;
    -moz-appearance: none;
    appearance: none;
}

.app-input-filter {
    width: 100%;
    height: var(--control-height, 45px);
    padding: 0 16px;
    border: 1px solid var(--color-border, #e5e5e5);
    border-radius: var(--control-radius, 15px);
    font-size: 16px;
    font-family: 'Open Sans', sans-serif;
    color: #333333;
    background-color: #ffffff;
    box-sizing: border-box;
}

.app-input-filter:focus {
    outline: none;
    border-color: var(--color-primary, #1946A0);
    background-color: #ffffff;
}

.app-input-filter::placeholder {
    color: #999999;
    font-style: italic;
}

.app-input-icon {
    position: absolute;
    right: 10px;
    top: 50%;
    transform: translateY(-50%);
    pointer-events: none;
    width: 15px;
    height: 15px;
    display: block;
    object-fit: contain;
}

.app-input-eye-button {
    position: absolute;
    right: 6px;
    top: 50%;
    transform: translateY(-50%);
    background: transparent;
    border: none;
    padding: 6px;
    cursor: pointer;
    color: #888;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 8px;
}

.app-input-eye-button:hover {
    color: #333;
    background: rgba(0, 0, 0, 0.04);
}

.app-input-eye-button:focus {
    outline: 2px solid var(--color-primary, #1946A0);
    outline-offset: 1px;
}

.app-input-wrapper-edit .app-input-icon {
    width: 15px !important;
    height: 15px !important;
}

.app-input-icon-textarea {
    top: 12px;
    transform: none;
}

textarea.app-input-edit {
    height: 120px;
    min-height: 120px;
    max-height: 120px;
    padding: 12px 40px 12px 16px;
    resize: none;
    overflow-y: auto;
}
</style>
