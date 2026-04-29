<script setup>
import { ref, computed } from 'vue'
import ContentEditorTab from '@/Components/Admin/ContentEditorTab.vue'
import EmailNotificationEditor from '@/Components/Admin/EmailNotificationEditor.vue'
import { ADMIN_RULES_SUB_TABS } from '@/constants/admin/adminRulesContentTabs'

const activeTabKey = ref(ADMIN_RULES_SUB_TABS[0].key)

const activeTab = computed(() => ADMIN_RULES_SUB_TABS.find((t) => t.key === activeTabKey.value) ?? null)
</script>

<template>
    <div class="admin-rules">
        <div class="admin-tab-intro">
            <h2 class="admin-tab-intro__title">Taisyklės ir turinys</h2>
            <p class="admin-tab-intro__desc">
                Kurkite ir skelbkite taisykles, DUK bei kitą viešą turinį ir tvarkykite el. pašto šablonus.
            </p>
        </div>
        <div class="subtabs">
            <button
                v-for="tab in ADMIN_RULES_SUB_TABS"
                :key="tab.key"
                type="button"
                class="subtab"
                :class="{ active: activeTabKey === tab.key }"
                @click="activeTabKey = tab.key"
            >
                {{ tab.tabLabel }}
            </button>
        </div>
        <div class="panel">
            <ContentEditorTab
                v-if="activeTab?.kind === 'contentVersion'"
                :type="activeTab.contentType"
                :label="activeTab.editorLabel"
            />
            <EmailNotificationEditor v-else-if="activeTab?.kind === 'emailNotifications'" />
        </div>
    </div>
</template>

<style scoped>
.admin-rules {
    padding: 0;
}
.subtabs {
    display: flex;
    gap: 0;
    margin-bottom: 8px;
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
    background: transparent;
    border-radius: 0;
    padding: 0;
    border: 0;
}
</style>
