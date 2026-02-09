<script setup>
defineProps({
    activeTab: {
        type: String,
        default: 'darbuotojas',
    },
})

const emit = defineEmits(['select'])

function select(tab) {
    emit('select', tab)
}
</script>

<template>
    <div class="login-form-navigation">
        <button
            type="button"
            class="login-nav-item"
            :class="{ active: activeTab === 'pareiskejas' }"
            @click="select('pareiskejas')"
        >
            Pareiškėjas
        </button>
        <button
            type="button"
            class="login-nav-item"
            :class="{ active: activeTab === 'darbuotojas' }"
            @click="select('darbuotojas')"
        >
            Darbuotojas
        </button>
    </div>
</template>

<style scoped>
.login-form-navigation {
    display: flex;
    justify-content: space-between;
    margin-bottom: 20px;
    gap: 1rem;
}

.login-nav-item {
    flex: 1;
    padding: 0.5rem 0;
    border: none;
    background: none;
    font-size: inherit;
    font-family: inherit;
    color: inherit;
    cursor: pointer;
    text-decoration: none;
    border-bottom: 3px solid transparent;
    transition: border-color 0.2s;
}

.login-nav-item.active {
    border-bottom-color: #1946A0;
}
</style>
