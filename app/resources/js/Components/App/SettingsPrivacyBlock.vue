<script setup>
import AppSwitch from '@/Components/App/AppSwitch.vue'
import AppButton from '@/Components/App/AppButton.vue'
import { computed } from 'vue'

const props = defineProps({
    twoFactorEnabled: { type: Boolean, default: false },
    emailUpdatesInstitutionAdmin: { type: Boolean, default: false },
    emailNotificationsEnabled: { type: Boolean, default: false },
    isInstitutionAdmin: { type: Boolean, default: false },
    isSystemAdmin: { type: Boolean, default: false },
    isProblemAdmin: { type: Boolean, default: false },
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
    'save-email-updates-admin',
    'save-email-notifications',
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
            <span class="privacy-text">Privatumo taisyklės</span>
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
            <span class="privacy-text">Privatumo taisyklės</span>
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
    box-sizing: border-box;
}

.privacy-block > * {
    margin: 0;
    padding: 0;
    min-height: 32px;
    box-sizing: border-box;
}

.privacy-row.privacy-row-switch {
    display: grid;
    grid-template-columns: 1fr auto;
    align-items: center;
    gap: 12px;
    width: 100%;
    min-height: 32px;
    box-sizing: border-box;
}

.privacy-label {
    font-size: 18px;
    font-weight: 600;
    color: var(--color-text);
    min-width: 0;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.privacy-row-right {
    display: flex;
    align-items: center;
    justify-content: flex-end;
    justify-self: end;
}

.privacy-text {
    font-size: 18px;
    font-weight: 600;
    color: var(--color-text);
    line-height: 32px;
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
