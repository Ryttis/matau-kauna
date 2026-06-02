<script setup>
import { Head, Link } from '@inertiajs/vue3'
</script>

<template>
    <Head title="Reikia prisijungti" />

    <div class="login-required">
        <div class="login-required-card">
            <div class="login-required-icon">
                <svg width="28" height="28" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path d="M6 10V7a6 6 0 1 1 12 0v3" stroke="#1946A0" stroke-width="2" stroke-linecap="round" />
                    <rect x="4" y="10" width="16" height="10" rx="2" stroke="#1946A0" stroke-width="2" />
                </svg>
            </div>
            <h1 class="login-required-title">Reikia prisijungti</h1>
            <p class="login-required-text">
                Šis pranešimas nėra viešas. Norėdami jį peržiūrėti, prisijunkite.
            </p>
            <Link :href="route('login', { role: 'darbuotojas' })" class="login-required-button">Prisijungti</Link>
            <Link href="/" class="login-required-link">Į pradžią</Link>
        </div>
    </div>
</template>

<style scoped>
.login-required {
    min-height: 100vh;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 24px;
    background: #f3f4f6;
    font-family: 'Open Sans', sans-serif;
}

.login-required-card {
    width: 100%;
    max-width: 420px;
    background: #fff;
    border-radius: 16px;
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.08);
    padding: 40px 32px;
    text-align: center;
}

.login-required-icon {
    width: 64px;
    height: 64px;
    margin: 0 auto 20px;
    border-radius: 50%;
    background: #eef2fb;
    display: flex;
    align-items: center;
    justify-content: center;
}

.login-required-title {
    margin: 0 0 8px;
    font-size: 22px;
    font-weight: 700;
    color: #1f2937;
}

.login-required-text {
    margin: 0 0 24px;
    font-size: 15px;
    line-height: 1.5;
    color: #6b7280;
}

.login-required-button {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 100%;
    height: 45px;
    border-radius: 15px;
    background: #1946a0;
    color: #fff;
    font-size: 16px;
    font-weight: 600;
    text-decoration: none;
}

.login-required-button:hover {
    background: #143a87;
}

.login-required-link {
    display: inline-block;
    margin-top: 16px;
    font-size: 14px;
    color: #1946a0;
    text-decoration: none;
}

.login-required-link:hover {
    text-decoration: none;
}
</style>
