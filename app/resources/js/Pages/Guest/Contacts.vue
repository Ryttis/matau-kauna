<script setup>
import GuestContentPage from '@/Components/Guest/GuestContentPage.vue'

defineProps({
    title: { type: String, default: 'Kontaktai' },
    body: { type: String, default: '' }
})
</script>

<template>
    <GuestContentPage :title="title" :body="body" />
</template>
