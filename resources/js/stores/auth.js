import { defineStore } from 'pinia';
import { computed, ref } from 'vue';
import http, { tokenStorage } from '@/api/http';

export const useAuthStore = defineStore('auth', () => {
    const user = ref(null);

    const isLoggedIn = computed(() => user.value !== null);
    const isAdmin = computed(() => user.value?.is_admin === true);

    function startSession({ token, user: signedIn }) {
        tokenStorage.set(token);
        user.value = signedIn;
    }

    async function login(credentials) {
        const { data } = await http.post('/login', credentials);
        startSession(data);
    }

    async function register(payload) {
        const { data } = await http.post('/register', payload);
        startSession(data);
    }

    async function logout() {
        try {
            await http.post('/logout');
        } finally {
            tokenStorage.clear();
            user.value = null;
        }
    }

    async function restore() {
        if (!tokenStorage.get()) return;
        try {
            const { data } = await http.get('/me');
            user.value = data;
        } catch {
            tokenStorage.clear();
        }
    }

    return { user, isLoggedIn, isAdmin, login, register, logout, restore };
});
