<script setup>
import GuestLayout from '@/Layouts/GuestLayout.vue'
import { Head, router } from '@inertiajs/vue3'

defineProps({
    roles: Array,
})

const selectRole = (roleId) => {
    router.post(route('role.select.store'), { role_id: roleId })
}
</script>

<template>
    <GuestLayout>
        <Head title="Pasirinkite rolę" />

        <div class="role-select">
            <h1 class="role-select-title">Pasirinkite rolę</h1>
            <p class="role-select-hint">Su kuriomis teisėmis norite dirbti šią sesiją?</p>
            <ul class="role-list">
                <li v-for="r in roles" :key="r.id" class="role-item">
                    <button type="button" class="role-btn" @click="selectRole(r.id)">
                        {{ r.name }}
                    </button>
                </li>
            </ul>
        </div>
    </GuestLayout>
</template>

<style scoped>
.role-select {
    width: 400px;
    margin: 0 auto;
    text-align: center;
}

.role-select-title {
    font-size: 22px;
    margin-bottom: 8px;
}

.role-select-hint {
    color: #6b7280;
    font-size: 14px;
    margin-bottom: 24px;
}

.role-list {
    list-style: none;
    padding: 0;
    margin: 0;
}

.role-item {
    margin-bottom: 12px;
}

.role-btn {
    width: 100%;
    padding: 14px 20px;
    background: var(--color-primary, #1946A0);
    color: #fff;
    border: none;
    border-radius: 10px;
    font-size: 16px;
    font-weight: 500;
    cursor: pointer;
}

.role-btn:hover {
    opacity: 0.9;
}
</style>
