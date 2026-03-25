<script setup>
import { Head } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import InstitutionTeamsManager from '@/Components/InstitutionTeams/InstitutionTeamsManager.vue'
import { SETTINGS_PAGE_STYLE } from '@/constants/theme'

defineProps({
    initialInstitutionId: {
        type: [Number, String],
        default: null,
    },
})
</script>

<template>
    <Head title="Įstaigos administratorius" />

    <AuthenticatedLayout>
        <div class="settings-container settings-page" :style="SETTINGS_PAGE_STYLE">
            <div class="settings-content">
                <div class="settings-inner">
                    <InstitutionTeamsManager :initial-institution-id="initialInstitutionId" />
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

<style scoped>
.settings-container {
    width: 100%;
    max-width: var(--settings-container-max);
    margin: 0;
    background: var(--color-white, #fff);
    padding: 24px;
    box-sizing: border-box;
}

.settings-content {
    width: 100%;
    box-sizing: border-box;
}

.settings-inner {
    width: 100%;
    max-width: var(--settings-inner-max);
    margin: 0;
    box-sizing: border-box;
}
</style>

