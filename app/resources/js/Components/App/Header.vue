<script setup>
    import { computed } from 'vue'
    import { usePage, router } from '@inertiajs/vue3'

    const page = usePage()

    const user = computed(() => page.props.auth?.user ?? null)
    const isAuthenticated = computed(() => !!user.value)

    const logout = () => {
        router.post('/logout')
    }
</script>

<template>
    <div class="header">
        <div class="header-icons" v-if="isAuthenticated">
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
</style>
