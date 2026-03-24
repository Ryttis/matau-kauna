<script setup>
import { ref } from 'vue'
import ContentEditorTab from '@/Components/Admin/ContentEditorTab.vue'
import EmailNotificationEditor from '@/Components/Admin/EmailNotificationEditor.vue'

const activeSubTab = ref('terms')

const subTabs = [
    { key: 'terms', label: 'Taisyklės', type: 'terms' },
    { key: 'faq', label: 'DUK', type: 'faq' },
    { key: 'privacy', label: 'Privatumo', type: 'privacy' },
    { key: 'cookies', label: 'Slapukai', type: 'cookies' },
    { key: 'notifications', label: 'Pranešimai' },
]
</script>

<template>
    <div class="admin-rules">
        <div class="subtabs">
            <button
                v-for="t in subTabs"
                :key="t.key"
                type="button"
                class="subtab"
                :class="{ active: activeSubTab === t.key }"
                @click="activeSubTab = t.key"
            >
                {{ t.label }}
            </button>
        </div>
        <div class="panel">
            <ContentEditorTab
                v-if="activeSubTab === 'terms'"
                type="terms"
                label="Taisyklės"
            />
            <ContentEditorTab
                v-else-if="activeSubTab === 'faq'"
                type="faq"
                label="DUK"
            />
            <ContentEditorTab
                v-else-if="activeSubTab === 'privacy'"
                type="privacy"
                label="Privatumo"
            />
            <ContentEditorTab
                v-else-if="activeSubTab === 'cookies'"
                type="cookies"
                label="Slapukų informacija"
            />
            <EmailNotificationEditor v-else-if="activeSubTab === 'notifications'" />
        </div>
    </div>
</template>

<style scoped>
.admin-rules {
    padding: 0;
}
.title {
    font-size: 18px;
    font-weight: 700;
    margin: 0 0 14px 0;
}
.subtabs {
    display: flex;
    gap: 0;
    margin-bottom: 12px;
}
.subtab {
    padding: 12px 16px;
    background: transparent;
    cursor: pointer;
    color: #666;
    font-size: 14px;
    font-weight: 500;
    border: none;
    border-bottom: 3px solid transparent;
}
.subtab:hover {
    color: var(--color-primary, #1946A0);
}
.subtab.active {
    color: var(--color-primary, #1946A0);
    border-bottom-color: var(--color-primary, #1946A0);
}
.panel {
    background: #ffffff;
    border-radius: 14px;
    padding: 20px;
    border: 1px solid #e5e7eb;
}
.placeholder {
    color: #666;
    font-size: 14px;
    margin: 0;
}
</style>
