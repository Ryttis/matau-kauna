<script setup>
const props = defineProps({
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
    width: 100%;
    background: #ffffff;
    overflow: hidden;
}

.app-tab-navigation .nav-item {
    flex: 1;
    padding: 14px 10px;
    text-align: center;
    background: transparent;
    border: none;
    cursor: pointer;
    color: #1946A0;
    font-weight: 500;
}

.app-tab-navigation .nav-item:hover {
    background: #cccccc;
}

.app-tab-navigation .nav-item.active {
    background-color: #1946A0;
    color: #ffffff;
}
</style>