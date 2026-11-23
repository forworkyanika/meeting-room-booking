<script setup>
import NavBar from '@/components/NavBar.vue';
import ToastStack from '@/components/ToastStack.vue';
import { useAuthStore } from '@/stores/auth';

const auth = useAuthStore();
</script>

<template>
    <NavBar v-if="auth.isLoggedIn" />
    <main :class="auth.isLoggedIn ? 'container-xxl py-4' : ''">
        <RouterView />
    </main>
    <ToastStack />
</template>
