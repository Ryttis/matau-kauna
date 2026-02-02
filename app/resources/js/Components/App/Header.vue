<script setup>
    import { computed, ref } from 'vue'
    import { usePage, router } from '@inertiajs/vue3'

    const page = usePage()
    const roleDropdownOpen = ref(false)

    const user = computed(() => page.props.auth?.user ?? null)
    const roles = computed(() => page.props.auth?.roles ?? [])
    const activeRole = computed(() => page.props.auth?.activeRole ?? null)
    const isAuthenticated = computed(() => !!user.value)
    const showRoleSwitcher = computed(() => roles.value.length > 1)

    const logout = () => {
        router.post('/logout')
    }

    const switchRole = (roleId) => {
        roleDropdownOpen.value = false
        router.post(route('role.select.store'), { role_id: roleId }, {
            preserveState: false,
            onFinish: () => {},
        })
    }
</script>

<template>
    <div class="header">
        <div class="header-icons" v-if="isAuthenticated">
            <div v-if="showRoleSwitcher" class="role-switcher-wrp">
                <button
                    type="button"
                    class="role-switcher-btn"
                    @click="roleDropdownOpen = !roleDropdownOpen"
                    title="Keisti rolę"
                >
                    {{ activeRole?.name ?? 'Rolė' }}
                </button>
                <div v-if="roleDropdownOpen" class="role-dropdown">
                    <button
                        v-for="r in roles"
                        :key="r.id"
                        type="button"
                        class="role-dropdown-item"
                        :class="{ active: activeRole?.id === r.id }"
                        @click="switchRole(r.id)"
                    >
                        {{ r.name }}
                    </button>
                </div>
            </div>
            <div class="icon-text-wrp">
                <div class="active-img-wrp">
                    <img class="active" src="/icons/user-icon.png" />
                </div>
                <span>{{ user?.name }}</span>
            </div>
            <img src="/icons/bell-icon.png" />
            <button
                class="header-icon-button"
                type="button"
                @click="logout"
                title="Atsijungti"
            >
                <img src="/icons/logout-icon.png" />
            </button>
        </div>
    </div>
</template>

<style scoped>
    .header {
        height: 66px;
        background-color: #1946A0;
        width: 100%;
        display: flex;
    }

    .header-icons {
        color: #fff;
        display: flex;
        gap: 36px;
        margin: auto 100px auto auto;
    }

    .header-icons img {
        width: 16px;
        height: 16px;
    }

    .header-icons .active-img-wrp {
        width: 24px;
        height: 24px;
        background-color: #fff;
        border-radius: 50%;
        padding: 4px;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .header-icons img.active {
        width: 16px;
        height: 16px;
    }

    .icon-text-wrp {
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .header-icon-button {
        border: none;
        background-color: transparent;
        cursor: pointer;
    }

    .role-switcher-wrp {
        position: relative;
    }

    .role-switcher-btn {
        padding: 6px 12px;
        background: rgba(255,255,255,0.2);
        color: #fff;
        border: 1px solid rgba(255,255,255,0.4);
        border-radius: 8px;
        font-size: 14px;
        cursor: pointer;
    }

    .role-switcher-btn:hover {
        background: rgba(255,255,255,0.3);
    }

    .role-dropdown {
        position: absolute;
        top: 100%;
        left: 0;
        margin-top: 4px;
        min-width: 180px;
        background: #fff;
        border: 1px solid #e0e0e0;
        border-radius: 8px;
        box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        z-index: 100;
    }

    .role-dropdown-item {
        display: block;
        width: 100%;
        padding: 10px 14px;
        text-align: left;
        border: none;
        background: none;
        color: #333;
        font-size: 14px;
        cursor: pointer;
    }

    .role-dropdown-item:hover {
        background: #f5f5f5;
    }

    .role-dropdown-item.active {
        background: #e8eef7;
        color: #1946a0;
    }
</style>
