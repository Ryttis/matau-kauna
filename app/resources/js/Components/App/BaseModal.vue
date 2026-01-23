<script setup>
const props = defineProps({
    title: {
        type: String,
        default: ''
    },
    open: {
        type: Boolean,
        default: false
    },
    width: {
        type: String,
        default: '520px'
    }
})

const emit = defineEmits(['close'])
</script>

<template>
    <div v-if="open" class="modal-overlay" @click.self="emit('close')">
        <div class="modal" :style="{ width }">
            <div class="modal-header">
                <div class="modal-title">{{ title }}</div>
                <button type="button" class="x-btn" @click="emit('close')">×</button>
            </div>

            <div class="modal-body">
                <slot />
            </div>

            <div v-if="$slots.footer" class="modal-footer">
                <slot name="footer" />
            </div>
        </div>
    </div>
</template>

<style scoped>
.modal-overlay {
    position: fixed;
    inset: 0;
    background: rgba(0, 0, 0, 0.35);
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 18px;
    z-index: 9999;
}

.modal {
    max-width: 100%;
    background: #ffffff;
    border-radius: 14px;
    overflow: hidden;
    box-shadow: 0 18px 40px rgba(0, 0, 0, 0.25);
}

.modal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 14px 16px;
    border-bottom: 1px solid #eef0f3;
}

.modal-title {
    font-size: 16px;
    font-weight: 700;
}

.x-btn {
    border: none;
    background: transparent;
    cursor: pointer;
    font-size: 22px;
    line-height: 1;
    padding: 0 4px;
    color: #6b7280;
}

.modal-body {
    padding: 16px;
}

.modal-footer {
    display: flex;
    justify-content: flex-end;
    gap: 10px;
    padding: 14px 16px;
    border-top: 1px solid #eef0f3;
}
</style>
