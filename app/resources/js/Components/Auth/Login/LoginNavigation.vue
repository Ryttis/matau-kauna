<script setup>
const TABS = [
    { id: 'pareiskejas', label: 'Pareiškėjas' },
    { id: 'darbuotojas', label: 'Darbuotojas' },
]

defineProps({
    activeTab: { type: String, default: 'darbuotojas' },
})

const emit = defineEmits(['select'])
</script>

<template>
    <div class="login-form-navigation">
        <button
            v-for="tab in TABS"
            :key="tab.id"
            type="button"
            class="login-nav-item"
            :class="{ active: activeTab === tab.id }"
            @click="emit('select', tab.id)"
        >
            {{ tab.label }}
        </button>
    </div>
</template>

<style scoped>
.login-form-navigation {
    display: flex;
    justify-content: space-between;
    margin-bottom: 24px;
    padding: 0 40px;
}

.login-nav-item {
    padding: 0.5rem 0;
    border: none;
    background: none;
    font-size: 1rem;
    font-family: inherit;
    color: #6b7280;
    cursor: pointer;
    text-decoration: none;
    font-weight: 400;
    transition: color 0.2s, border-color 0.2s, font-weight 0.2s;
}

.login-nav-item:hover {
    color: #374151;
}

.login-nav-item.active {
    color: #1946A0;
    font-weight: 600;
    border-bottom: 2px solid #1946A0;
    padding-bottom: 0.5rem;
}
</style>
