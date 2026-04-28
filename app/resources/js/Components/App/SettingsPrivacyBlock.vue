<script setup>
import { Link } from '@inertiajs/vue3'
import AppSwitch from '@/Components/App/AppSwitch.vue'
import AppButton from '@/Components/App/AppButton.vue'
import { computed } from 'vue'

const props = defineProps({
    twoFactorEnabled: { type: Boolean, default: false },
    emailUpdatesInstitutionAdmin: { type: Boolean, default: false },
    emailNotificationsEnabled: { type: Boolean, default: false },
    reporterEmailOwnProblems: { type: Boolean, default: true },
    reporterEmailWatchedProblems: { type: Boolean, default: true },
    isInstitutionAdmin: { type: Boolean, default: false },
    isSystemAdmin: { type: Boolean, default: false },
    isProblemAdmin: { type: Boolean, default: false },
    isReporterOnly: { type: Boolean, default: false },
})

const showFiveFields = computed(() =>
    props.isSystemAdmin || props.isProblemAdmin || props.isInstitutionAdmin
)

const secondSwitchValue = computed(() =>
    props.isInstitutionAdmin ? props.emailUpdatesInstitutionAdmin : props.emailNotificationsEnabled
)

const emit = defineEmits([
    'update:twoFactorEnabled',
    'update:emailUpdatesInstitutionAdmin',
    'update:emailNotificationsEnabled',
    'update:reporterEmailOwnProblems',
    'update:reporterEmailWatchedProblems',
    'save-email-updates-admin',
    'save-email-notifications',
    'save-reporter-email-own',
    'save-reporter-email-watched',
    'delete-account',
])

function onSecondSwitchChange(v) {
    if (props.isInstitutionAdmin) {
        emit('update:emailUpdatesInstitutionAdmin', v)
        emit('save-email-updates-admin', v)
    } else {
        emit('update:emailNotificationsEnabled', v)
        emit('save-email-notifications', v)
    }
}
</script>

<template>
    <div class="privacy-block">
        <template v-if="showFiveFields">
            <div class="privacy-row privacy-row-switch">
                <span class="privacy-label">Gauti pranešimus apie įstaigai priskirtas problemas</span>
                <div class="privacy-row-right">
                    <AppSwitch
                        :modelValue="emailUpdatesInstitutionAdmin"
                        @update:modelValue="(v) => { emit('update:emailUpdatesInstitutionAdmin', v); emit('save-email-updates-admin', v) }"
                    />
                </div>
            </div>
            <div class="privacy-row privacy-row-switch">
                <span class="privacy-label">Gauti pranešimus apie man priskirtas problemas</span>
                <div class="privacy-row-right">
                    <AppSwitch
                        :modelValue="emailNotificationsEnabled"
                        @update:modelValue="(v) => { emit('update:emailNotificationsEnabled', v); emit('save-email-notifications', v) }"
                    />
                </div>
            </div>
            <div class="privacy-row privacy-row-switch">
                <span class="privacy-label">2 FA autentifikavimas</span>
                <div class="privacy-row-right">
                    <AppSwitch
                        :modelValue="twoFactorEnabled"
                        @update:modelValue="emit('update:twoFactorEnabled', $event)"
                    />
                </div>
            </div>
            <Link :href="route('privacy')" class="privacy-text-link">Privatumo taisyklės</Link>
            <AppButton
                text="Šalinti paskyrą"
                variant="link"
                as-button
                class="privacy-link"
                @click="emit('delete-account')"
            />
        </template>
        <template v-else-if="isReporterOnly">
            <div class="privacy-row privacy-row-switch privacy-row--reporter">
                <span class="privacy-label">2 FA autentifikavimas</span>
                <div class="privacy-row-right">
                    <AppSwitch
                        :modelValue="twoFactorEnabled"
                        @update:modelValue="emit('update:twoFactorEnabled', $event)"
                    />
                </div>
            </div>
            <div class="privacy-row privacy-row-switch privacy-row--reporter">
                <span class="privacy-label privacy-label--wrap">Informuoti el. paštu apie mano problemas</span>
                <div class="privacy-row-right">
                    <AppSwitch
                        :modelValue="reporterEmailOwnProblems"
                        @update:modelValue="(v) => { emit('update:reporterEmailOwnProblems', v); emit('save-reporter-email-own', v) }"
                    />
                </div>
            </div>
            <div class="privacy-row privacy-row-switch privacy-row--reporter">
                <span class="privacy-label privacy-label--wrap">Informuoti el. paštu apie mano stebimas problemas</span>
                <div class="privacy-row-right">
                    <AppSwitch
                        :modelValue="reporterEmailWatchedProblems"
                        @update:modelValue="(v) => { emit('update:reporterEmailWatchedProblems', v); emit('save-reporter-email-watched', v) }"
                    />
                </div>
            </div>
            <Link :href="route('privacy')" class="privacy-text-link">Privatumo taisyklės</Link>
            <AppButton
                text="Šalinti paskyrą"
                variant="link"
                as-button
                class="privacy-link"
                @click="emit('delete-account')"
            />
        </template>
        <template v-else>
            <div class="privacy-row privacy-row-switch">
                <span class="privacy-label">2 FA autentifikavimas</span>
                <div class="privacy-row-right">
                    <AppSwitch
                        :modelValue="twoFactorEnabled"
                        @update:modelValue="emit('update:twoFactorEnabled', $event)"
                    />
                </div>
            </div>
            <div class="privacy-row privacy-row-switch">
                <span class="privacy-label">Gauti pranešimus</span>
                <div class="privacy-row-right">
                    <AppSwitch
                        :modelValue="secondSwitchValue"
                        @update:modelValue="onSecondSwitchChange"
                    />
                </div>
            </div>
            <Link :href="route('privacy')" class="privacy-text-link">Privatumo taisyklės</Link>
            <AppButton
                text="Šalinti paskyrą"
                variant="link"
                as-button
                class="privacy-link"
                @click="emit('delete-account')"
            />
        </template>
    </div>
</template>

<style scoped>
.privacy-block {
    display: flex;
    flex-direction: column;
    gap: 12px;
    margin-bottom: 24px;
    width: 100%;
    max-width: min(640px, 50%);
    box-sizing: border-box;
}

@media (max-width: 768px) {
    .privacy-block {
        max-width: 100%;
    }
}

.privacy-block > * {
    margin: 0;
    padding: 0;
    min-height: 32px;
    box-sizing: border-box;
}

.privacy-text-link {
    display: block;
    width: 100%;
    font-size: 18px;
    font-weight: 600;
    color: var(--color-text);
    line-height: 32px;
    text-decoration: none;
    box-sizing: border-box;
}

.privacy-text-link:hover,
.privacy-text-link:focus-visible {
    color: var(--color-text);
    text-decoration: none;
    opacity: 0.85;
}

.privacy-block .privacy-row {
    min-height: 32px;
}

.privacy-row.privacy-row-switch {
    display: grid;
    grid-template-columns: minmax(0, 1fr) auto;
    align-items: center;
    gap: 12px;
    width: 100%;
    min-width: 0;
    min-height: 32px;
    box-sizing: border-box;
}

.privacy-row--reporter.privacy-row-switch {
    align-items: start;
    gap: 16px;
}

.privacy-row--reporter .privacy-row-right {
    padding-top: 2px;
}

.privacy-label {
    min-width: 0;
    font-size: 18px;
    font-weight: 600;
    color: var(--color-text);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.privacy-label--wrap {
    white-space: normal;
    overflow: visible;
    text-overflow: clip;
    line-height: 1.35;
}

.privacy-row-right {
    display: flex;
    align-items: center;
    justify-content: flex-end;
    justify-self: end;
}

.privacy-link {
    width: 100%;
    margin: 0;
    padding: 0;
    font-size: 18px;
    font-weight: 600;
    color: var(--color-text);
    text-align: left;
    display: block;
    line-height: 32px;
    text-decoration: none !important;
    background: none;
    border: none;
    cursor: pointer;
}

.privacy-link:hover {
    text-decoration: none !important;
}
</style>
