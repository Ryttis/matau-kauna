<script setup>

defineProps({
    modelValue: {
        type: String,
        required: true,
    },
    id: {
        type: String,
        required: true,
    },
    type: {
        type: String,
        required: true,
    },
    placeholder: {
        type: String,
        required: true,
    },
    error: {
        type: String,
        default: '',
    },
    readonly: {
        type: Boolean,
        default: false,
    },
});

defineEmits(['update:modelValue']);
</script>

<template>
    <div class="form-input-wrp">
        <input
            class="form-input"
            :id="id"
            :type="type"
            :placeholder="placeholder"
            :value="modelValue"
            :readonly="readonly"
            required
            autofocus
            @input="$emit('update:modelValue', $event.target.value)"
        />
    </div>
</template>

<style scoped>
.form-input-wrp {
    margin: 0 40px;
}

.form-input {
    width: 100%;
    padding: 10px;
    border: 1px solid #D9D9D9;
    border-radius: 5px;
    margin-bottom: 10px;
    font-size: 18px;
}
</style>
