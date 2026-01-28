<script setup>
import { computed, ref } from 'vue'

const datalistId = ref(`datalist-${Math.random().toString(36).substr(2, 9)}`)

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
        default: '/icons/pencil-icon.png'
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
    }
})

const emit = defineEmits(['update:modelValue'])

const value = computed({
    get: () => props.modelValue,
    set: (val) => emit('update:modelValue', val)
})

const inputClass = computed(() => {
    return props.variant === 'filter' ? 'app-input-filter' : 'app-input-edit'
})

const wrapperClass = computed(() => {
    return props.variant === 'filter' ? 'app-input-wrapper-filter' : 'app-input-wrapper-edit'
})
</script>

<template>
    <div class="app-input-group">
        <label v-if="label" class="app-input-label">{{ label }}</label>

        <div v-if="!asTextarea" :class="wrapperClass">
            <input
                v-model="value"
                :type="type"
                :placeholder="placeholder"
                :class="inputClass"
                :list="options.length > 0 ? datalistId : undefined"
                :readonly="readonly"
            />
            <datalist v-if="options.length > 0" :id="datalistId">
                <option v-for="option in options" :key="option" :value="option" />
            </datalist>
            <img v-if="showIcon" :src="iconSrc" :alt="iconAlt" class="app-input-icon" />
        </div>

        <div v-else-if="asTextarea" :class="wrapperClass">
            <textarea
                v-model="value"
                :placeholder="placeholder"
                :rows="rows"
                :class="inputClass"
                :readonly="readonly"
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
    font-family: 'Inter', sans-serif;
}

.app-input-wrapper-edit {
    position: relative;
}

.app-input-wrapper-filter {
    position: relative;
}

.app-input-edit {
    width: 100%;
    height: 40px;
    padding: 0 40px 0 16px;
    border: 1px solid #e0e0e0;
    border-radius: 5px;
    font-size: 16px;
    font-family: 'Inter', sans-serif;
    color: #333;
    background-color: #f9f9f9;
    box-sizing: border-box;
    -webkit-appearance: none;
    -moz-appearance: none;
    appearance: none;
}

.app-input-edit:focus {
    outline: none;
    border-color: #1946A0;
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
    height: 40px;
    padding: 0 15px;
    border: 1px solid #e0e0e0;
    border-radius: 5px;
    font-size: 16px;
    font-family: 'Inter', sans-serif;
    color: #333333;
    background-color: #f5f5f5;
    box-sizing: border-box;
}

.app-input-filter:focus {
    outline: none;
    border-color: #1946A0;
    background-color: #ffffff;
}

.app-input-filter::placeholder {
    color: #999999;
    font-style: italic;
}

.app-input-icon {
    position: absolute;
    right: 12px;
    top: 50%;
    transform: translateY(-50%);
    pointer-events: none;
    width: 16px;
    height: 16px;
    display: block;
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
