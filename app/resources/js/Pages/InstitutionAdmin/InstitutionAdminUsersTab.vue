<script setup>
import { computed, onMounted, ref } from "vue";
import { router, usePage } from "@inertiajs/vue3";
import axios from "axios";
import AppButton from "@/Components/App/AppButton.vue";
import BaseModal from "@/Components/App/BaseModal.vue";
import SuccessModal from "@/Components/App/SuccessModal.vue";
import { invitationsApi } from "@/constants/api/invitationsApi";
import { institutionAdminApi } from "@/constants/api/institutionAdminApi";
import { useSuccessModal } from "@/composables/useSuccessModal";

const props = defineProps({
    activeInstitutionId: {
        type: Number,
        required: true,
    },
    institutionUsers: {
        type: Array,
        default: () => [],
    },
    inviteRoles: {
        type: Array,
        default: () => [],
    },
    institutionRecord: {
        type: Object,
        required: true,
    },
});

const page = usePage();
const canInvite = computed(() =>
    (page.props.auth?.permissions ?? []).includes("invitations.manage"),
);
const isSystemAdmin = computed(() =>
    (page.props.auth?.permissions ?? []).includes("admin.access"),
);
const canEditUsers = computed(() => {
    const p = page.props.auth?.permissions ?? [];
    return p.includes("teams.manage") || p.includes("invitations.manage");
});

const { showSuccessModal, successMessage, closeSuccessModal, showWithMessage } =
    useSuccessModal();

const invitationsLoading = ref(false);
const invitationsPage = ref(null);
const inviteOpen = ref(false);
const inviteSaving = ref(false);
const inviteError = ref("");
const inviteForm = ref({ email: "", role_id: "" });

const isEditOpen = ref(false);
const editSaving = ref(false);
const editError = ref("");
const resetTwoFactorBusyUserId = ref(null);
const editForm = ref({
    id: null,
    name: "",
    email: "",
    password: "",
    password_confirmation: "",
});
const isEmailOnlyInstitution = computed(
    () => !!props.institutionRecord?.is_email_only,
);

const invitationRows = computed(() => invitationsPage.value?.data ?? []);

function twoFaLabel(enabled) {
    return enabled ? "On" : "Off";
}

function institutionAdminParams() {
    const params = {};
    if (isSystemAdmin.value) {
        params.institution_id = props.activeInstitutionId;
    }
    return params;
}

async function loadInvitations() {
    if (!canInvite.value) {
        return;
    }
    invitationsLoading.value = true;
    try {
        const params = { page: 1 };
        if (isSystemAdmin.value) {
            params.institution_id = props.activeInstitutionId;
        }
        const { data } = await axios.get(institutionAdminApi.invitations, {
            params,
        });
        invitationsPage.value = data;
    } catch {
        invitationsPage.value = null;
    } finally {
        invitationsLoading.value = false;
    }
}

onMounted(() => {
    loadInvitations();
});

function openInvite() {
    inviteForm.value = { email: "", role_id: "" };
    inviteError.value = "";
    inviteOpen.value = true;
}

async function sendInvite() {
    inviteError.value = "";
    if (isEmailOnlyInstitution.value) {
        inviteError.value =
            "Ši įstaiga pažymėta kaip tik el. pašto integracija, pakvietimai negalimi.";
        return;
    }
    inviteSaving.value = true;
    try {
        await axios.post(invitationsApi.store, {
            email: inviteForm.value.email,
            institution_id: props.activeInstitutionId,
            role_id: inviteForm.value.role_id,
        });
        inviteOpen.value = false;
        inviteForm.value = { email: "", role_id: "" };
        showWithMessage("Pakvietimas išsiųstas.");
        await loadInvitations();
        router.reload({ only: ["institutionUsers"] });
    } catch (e) {
        const msg = e.response?.data?.message;
        const errs = e.response?.data?.errors;
        if (errs && typeof errs === "object") {
            inviteError.value = Object.values(errs).flat().join(" ");
        } else {
            inviteError.value = msg ?? "Nepavyko išsiųsti pakvietimo.";
        }
    } finally {
        inviteSaving.value = false;
    }
}

function openEdit(u) {
    editForm.value = {
        id: u.id,
        name: u.name ?? "",
        email: u.email ?? "",
        password: "",
        password_confirmation: "",
    };
    editError.value = "";
    isEditOpen.value = true;
}

function closeEdit() {
    isEditOpen.value = false;
}

async function resetTwoFactor(user) {
    if (!user || resetTwoFactorBusyUserId.value !== null) return;
    if (
        !window.confirm(
            `Atkurti 2FA naudotojui „${user.name || user.email}"? Naudotojas turės iš naujo pasidaryti autentifikatorių.`,
        )
    ) {
        return;
    }
    resetTwoFactorBusyUserId.value = user.id;
    try {
        const params = institutionAdminParams();
        await axios.post(
            institutionAdminApi.userResetTwoFactor(user.id),
            {},
            { params },
        );
        showWithMessage(
            "2FA atstatyta. Naudotojas turi nusistatyti autentifikatorių iš naujo.",
        );
        router.reload({ only: ["institutionUsers"] });
    } catch (e) {
        const msg = e.response?.data?.message ?? "Nepavyko atkurti 2FA.";
        window.alert(msg);
    } finally {
        resetTwoFactorBusyUserId.value = null;
    }
}

async function saveEdit() {
    editError.value = "";
    const f = editForm.value;
    if (f.password && f.password !== f.password_confirmation) {
        editError.value = "Slaptažodžių laukai nesutampa.";
        return;
    }
    editSaving.value = true;
    try {
        const params = institutionAdminParams();
        const payload = {
            name: f.name,
            email: f.email,
        };
        if (f.password) {
            payload.password = f.password;
            payload.password_confirmation = f.password_confirmation;
        }
        await axios.put(institutionAdminApi.user(f.id), payload, { params });

        isEditOpen.value = false;
        editForm.value.password = "";
        editForm.value.password_confirmation = "";
        router.reload({ only: ["institutionUsers"] });
        showWithMessage("Naudotojas atnaujintas.");
    } catch (e) {
        const msg = e.response?.data?.message;
        const errs = e.response?.data?.errors;
        if (errs && typeof errs === "object") {
            editError.value = Object.values(errs).flat().join(" ");
        } else {
            editError.value = msg ?? "Nepavyko išsaugoti.";
        }
    } finally {
        editSaving.value = false;
    }
}
</script>

<template>
    <div class="admin-tab">
        <SuccessModal
            :show="showSuccessModal"
            :message="successMessage"
            @close="closeSuccessModal"
        />

        <div class="admin-tab-intro">
            <h3 class="admin-tab-intro__title">Naudotojai</h3>
            <p class="admin-tab-intro__desc">
                Tvarkykite įstaigos paskyras ir pakvietimus. Čia matote
                naudotojus, priskirtus šiai įstaigai.
            </p>
        </div>

        <div class="header-row">
            <h4 class="subsection-title">Įstaigos naudotojai</h4>
            <div v-if="canInvite" class="header-actions">
                <AppButton
                    text="Pakviesti"
                    variant="secondary"
                    as-button
                    class="btn-header"
                    @click="openInvite"
                />
            </div>
        </div>

        <div
            v-if="institutionUsers.length"
            class="table-body-scroll table-body-scroll--8-rows"
        >
            <table class="table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Vardas</th>
                        <th>Paštas</th>
                        <th>Rolės</th>
                        <th>2FA</th>
                        <th v-if="canEditUsers">Veiksmai</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="u in institutionUsers" :key="u.id">
                        <td>{{ u.id }}</td>
                        <td>{{ u.name }}</td>
                        <td>{{ u.email }}</td>
                        <td>
                            <span
                                v-for="r in u.roles ?? []"
                                :key="r.id"
                                class="pill"
                            >
                                {{ r.name }}
                            </span>
                            <span v-if="!(u.roles && u.roles.length)">-</span>
                        </td>
                        <td class="two-fa-cell">
                            {{ twoFaLabel(!!u.two_factor_enabled) }}
                        </td>
                        <td v-if="canEditUsers" class="actions">
                            <span class="actions-row">
                                <button
                                    type="button"
                                    class="link-btn"
                                    @click="openEdit(u)"
                                >
                                    Redaguoti
                                </button>
                                <template v-if="u.two_factor_enabled">
                                    <span class="actions-sep">|</span>
                                    <button
                                        type="button"
                                        class="link-btn"
                                        :disabled="
                                            resetTwoFactorBusyUserId === u.id
                                        "
                                        @click="resetTwoFactor(u)"
                                    >
                                        {{
                                            resetTwoFactorBusyUserId === u.id
                                                ? "Atkuriama…"
                                                : "Atkurti 2FA"
                                        }}
                                    </button>
                                </template>
                            </span>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
        <p v-else class="empty-hint">
            Nėra naudotojų, priskirtų šiai įstaigai.
        </p>

        <template v-if="canInvite">
            <h4 class="subsection-title subsection-title--spaced">
                Pakvietimai
            </h4>
            <p v-if="invitationsLoading" class="empty-hint">Kraunama…</p>
            <div
                v-else-if="invitationRows.length"
                class="table-body-scroll table-body-scroll--8-rows"
            >
                <table class="table">
                    <thead>
                        <tr>
                            <th>El. paštas</th>
                            <th>Rolė</th>
                            <th>Sukurta</th>
                            <th>Galioja iki</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="inv in invitationRows" :key="inv.id">
                            <td>{{ inv.email }}</td>
                            <td>{{ inv.role?.name ?? "-" }}</td>
                            <td>
                                {{
                                    inv.created_at
                                        ? String(inv.created_at)
                                              .slice(0, 16)
                                              .replace("T", " ")
                                        : "-"
                                }}
                            </td>
                            <td>
                                {{
                                    inv.expires_at
                                        ? String(inv.expires_at)
                                              .slice(0, 16)
                                              .replace("T", " ")
                                        : "-"
                                }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <p v-else class="empty-hint">Aktyvių pakvietimų sąrašas tuščias.</p>
        </template>

        <BaseModal
            :open="inviteOpen"
            title="Pakviesti naudotoją"
            @close="inviteOpen = false"
        >
            <div v-if="inviteError" class="error">{{ inviteError }}</div>
            <label class="field">
                <span>El. paštas</span>
                <input
                    v-model="inviteForm.email"
                    class="input"
                    type="email"
                    autocomplete="off"
                />
            </label>
            <div v-if="isEmailOnlyInstitution" class="hint">
                Ši įstaiga yra tik el. pašto integracija, todėl pakvietimai
                nesiunčiami.
            </div>
            <label class="field">
                <span>Rolė</span>
                <select v-model="inviteForm.role_id" class="input">
                    <option value="">- Pasirinkite</option>
                    <option v-for="r in inviteRoles" :key="r.id" :value="r.id">
                        {{ r.name }}
                    </option>
                </select>
            </label>
            <template #footer>
                <AppButton
                    text="Atšaukti"
                    variant="secondary"
                    as-button
                    @click="inviteOpen = false"
                />
                <AppButton
                    :text="inviteSaving ? 'Siunčiama…' : 'Siųsti pakvietimą'"
                    variant="primary"
                    as-button
                    :disabled="
                        inviteSaving ||
                        !inviteForm.email ||
                        !inviteForm.role_id ||
                        isEmailOnlyInstitution
                    "
                    @click="sendInvite"
                />
            </template>
        </BaseModal>

        <BaseModal
            :open="isEditOpen"
            title="Redaguoti naudotoją"
            @close="closeEdit"
        >
            <div v-if="editError" class="error">{{ editError }}</div>
            <label class="field">
                <span>Vardas</span>
                <input
                    v-model="editForm.name"
                    class="input"
                    type="text"
                    autocomplete="name"
                />
            </label>
            <label class="field">
                <span>Paštas</span>
                <input
                    v-model="editForm.email"
                    class="input"
                    type="email"
                    autocomplete="email"
                />
            </label>
            <div class="field">
                <span>Įstaiga</span>
                <div class="inst-readonly">{{ institutionRecord.name }}</div>
            </div>
            <label class="field">
                <span>Naujas slaptažodis (nebūtina)</span>
                <input
                    v-model="editForm.password"
                    class="input"
                    type="password"
                    autocomplete="new-password"
                    placeholder="Palikite tuščią, jei nekeičiate"
                />
            </label>
            <label v-if="editForm.password" class="field">
                <span>Pakartokite slaptažodį</span>
                <input
                    v-model="editForm.password_confirmation"
                    class="input"
                    type="password"
                    autocomplete="new-password"
                />
            </label>

            <template #footer>
                <AppButton
                    text="Atšaukti"
                    variant="secondary"
                    as-button
                    @click="closeEdit"
                />
                <AppButton
                    text="Išsaugoti"
                    variant="primary"
                    as-button
                    :disabled="editSaving"
                    @click="saveEdit"
                />
            </template>
        </BaseModal>
    </div>
</template>

<style scoped>
.table-body-scroll--8-rows {
    --inst-admin-scroll-row: 48px;
    --inst-admin-scroll-head: 44px;
    max-height: calc(
        var(--inst-admin-scroll-head) + 8 * var(--inst-admin-scroll-row)
    );
    overflow-y: auto;
    overflow-x: auto;
    -webkit-overflow-scrolling: touch;
    min-width: 0;
}

.table-body-scroll--8-rows :deep(thead th) {
    position: sticky;
    top: 0;
    z-index: 2;
    background: var(--table-header-bg);
    color: var(--table-header-text);
    box-shadow: 0 1px 0 var(--color-border, #e5e5e5);
}

.two-fa-cell {
    font-weight: 600;
    white-space: nowrap;
}

.actions-row {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    flex-wrap: wrap;
}

.actions-sep {
    color: #9ca3af;
}

.subsection-title {
    font-size: 16px;
    font-weight: 700;
    margin: 0;
    color: #1a1a1a;
}
.subsection-title--spaced {
    margin-top: 28px;
}
.empty-hint {
    font-size: 14px;
    color: #666;
    margin: 8px 0 0;
}

.inst-readonly {
    min-height: 40px;
    padding: 8px 12px;
    border: 1px solid var(--color-border-light, #e5e7eb);
    border-radius: 10px;
    font-size: 14px;
    color: #374151;
    background: #f9fafb;
}
</style>
