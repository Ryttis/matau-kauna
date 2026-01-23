<script setup>
</script>

<template>
    <div class="login-form-navigation">
        <span class="active">Pareiškėjas</span>
        <span>Darbuotojas</span>
    </div>
</template>

<style scoped>
.login-form-navigation {
    display: flex;
    justify-content: space-between;
    margin-bottom: 20px;
}

.login-form-navigation span {
    text-decoration: underline;
}

.login-form-navigation span.active {
    text-decoration: none;
}
</style>
