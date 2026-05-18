<script setup>
import { ref } from 'vue'
import { Head } from '@inertiajs/vue3'
import GuestHeader from '@/Components/Landing/GuestHeader.vue'
import LandingFooter from '@/Components/Landing/LandingFooter.vue'

const props = defineProps({
    title: { type: String, default: 'DUK' },
    body: { type: String, default: '' },
    items: { type: Array, default: () => [] },
})

const openIndex = ref(null)
const toggle = (index) => {
    openIndex.value = openIndex.value === index ? null : index
}

const hasItems = () => Array.isArray(props.items) && props.items.length > 0
</script>

<template>
    <Head :title="title + ' – Kauno problemos'" />
    <div class="landing guest-content-page">
        <GuestHeader />
        <main class="guest-content-main">
            <div class="guest-content-container">
                <h1 class="guest-content-title">{{ title }}</h1>

                <ul v-if="hasItems()" class="faq-list">
                    <li
                        v-for="(item, index) in items"
                        :key="index"
                        class="faq-item"
                        :class="{ 'faq-item--open': openIndex === index }"
                    >
                        <button
                            type="button"
                            class="faq-question"
                            :aria-expanded="openIndex === index"
                            @click="toggle(index)"
                        >
                            <span class="faq-question-text">{{ item.question }}</span>
                            <span class="faq-chevron" aria-hidden="true">▾</span>
                        </button>
                        <div v-show="openIndex === index" class="faq-answer">
                            <p>{{ item.answer }}</p>
                        </div>
                    </li>
                </ul>

                <div v-else-if="body" class="guest-content-body content-html" v-html="body"></div>
                <p v-else class="guest-content-empty">Turinys dar neįkeltas.</p>
            </div>
        </main>
        <LandingFooter />
    </div>
</template>

<style scoped>
.landing {
    min-height: 100vh;
    display: flex;
    flex-direction: column;
    background: #fff;
    font-family: var(--font-family-base, 'Open Sans', sans-serif);
}

.landing :deep(.landing-container),
.landing :deep(.landing-container--wide) {
    max-width: 1200px;
    margin: 0 auto;
    width: 100%;
    padding: 0 1rem;
    box-sizing: border-box;
}

.guest-content-main {
    flex: 1;
    padding: 2rem 0 3rem;
}

.guest-content-container {
    max-width: 1200px;
    margin: 0 auto;
    padding: 0 1rem;
}

.guest-content-title {
    font-size: clamp(1.5rem, 4vw, 1.75rem);
    font-weight: 700;
    color: #000;
    margin: 0 0 1rem 0;
}

.guest-content-body {
    line-height: 1.6;
    color: #333;
    white-space: pre-line;
}

.guest-content-body :deep(p),
.guest-content-body :deep(ul),
.guest-content-body :deep(ol),
.guest-content-body :deep(h2) {
    white-space: normal;
}

.guest-content-body :deep(p) { margin: 0 0 0.75rem 0; }
.guest-content-body :deep(ul), .guest-content-body :deep(ol) { margin: 0 0 0.75rem 1.25rem; }
.guest-content-body :deep(li) { margin: 0; }
.guest-content-body :deep(h2) { font-size: 1.15rem; margin: 1rem 0 0.5rem 0; }
.guest-content-empty { color: #666; margin: 0; }

.faq-list {
    list-style: none;
    margin: 0;
    padding: 0;
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}

.faq-item {
    border: 1px solid #e5e7eb;
    border-radius: 8px;
    background: #fff;
    overflow: hidden;
}

.faq-item--open {
    border-color: #1d3aa6;
}

.faq-question {
    width: 100%;
    background: transparent;
    border: none;
    padding: 0.9rem 1rem;
    text-align: left;
    font: inherit;
    font-weight: 600;
    color: #111;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
}

.faq-question:hover {
    background: #f8fafc;
}

.faq-question-text {
    flex: 1;
}

.faq-chevron {
    transition: transform 0.15s ease;
    color: #1d3aa6;
    font-size: 1rem;
}

.faq-item--open .faq-chevron {
    transform: rotate(180deg);
}

.faq-answer {
    padding: 0 1rem 0.9rem 1rem;
    color: #333;
    line-height: 1.55;
}

.faq-answer p {
    margin: 0;
    white-space: pre-line;
}
</style>
