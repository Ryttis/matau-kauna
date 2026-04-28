<script setup>
defineProps({
    tabs: {
        type: Array,
        required: true,
    },
    activeTab: {
        type: String,
        required: true,
    },
})

const emit = defineEmits(['set-tab'])

const setTab = (key) => {
    emit('set-tab', key)
}
</script>

<template>
    <div class="app-tab-navigation">
        <button
            v-for="tab in tabs"
            :key="tab.key"
            type="button"
            class="nav-item"
            :class="{ active: activeTab === tab.key }"
            @click="setTab(tab.key)"
        >
            {{ tab.label }}
        </button>
    </div>
</template>

<style scoped>
.app-tab-navigation {
    display: flex;
    flex-wrap: nowrap;
    width: 100%;
    max-width: 100%;
    background: #ffffff;
    overflow-x: auto;
    overflow-y: hidden;
    -webkit-overflow-scrolling: touch;
    gap: 0;
    scrollbar-width: thin;
}

.app-tab-navigation .nav-item {
    flex: 1 0 auto;
    min-width: min(100%, max-content);
    padding: var(--admin-spacing, 12px) 16px;
    text-align: center;
    white-space: nowrap;
    background: transparent;
    border: none;
    cursor: pointer;
    color: #1946A0;
    font-size: var(--admin-font-size, 14px);
    font-family: inherit;
    font-weight: 400;
    line-height: 1.35;
    text-decoration: none;
}

.app-tab-navigation .nav-item:hover {
    background: #cccccc;
    text-decoration: none;
}

.app-tab-navigation .nav-item.active {
    background-color: #1946A0;
    color: #ffffff;
    font-weight: 400;
}

@media (min-width: 1024px) {
    .app-tab-navigation {
        overflow-x: hidden;
    }

    .app-tab-navigation .nav-item {
        flex: 1;
        min-width: 0;
        white-space: normal;
    }
}
</style>
