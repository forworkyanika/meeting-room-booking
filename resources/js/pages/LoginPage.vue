<script setup>
import { reactive, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useAuthStore } from '@/stores/auth';
import { errorMessage, validationErrors } from '@/api/http';

const auth = useAuthStore();
const router = useRouter();
const route = useRoute();

const form = reactive({ email: '', password: '' });
const errors = ref({});
const message = ref('');
const loading = ref(false);

async function submit() {
    loading.value = true;
    errors.value = {};
    message.value = '';
    try {
        await auth.login(form);
        router.push(route.query.redirect || { name: 'calendar' });
    } catch (error) {
        errors.value = validationErrors(error);
        if (!Object.keys(errors.value).length) message.value = errorMessage(error);
    } finally {
        loading.value = false;
    }
}

function fillDemo(email) {
    form.email = email;
    form.password = 'password';
}
</script>

<template>
    <div class="min-vh-100 d-flex align-items-center justify-content-center p-3">
        <div class="card shadow-sm w-100 auth-card">
            <div class="card-body p-4">
                <h1 class="h4 mb-1 text-primary"><i class="bi bi-calendar2-week me-2"></i>RoomBook</h1>
                <p class="text-body-secondary mb-4">Sign in to book a meeting room.</p>

                <div v-if="message" class="alert alert-danger py-2">{{ message }}</div>

                <form novalidate @submit.prevent="submit">
                    <div class="mb-3">
                        <label class="form-label" for="email">Email</label>
                        <input id="email" v-model.trim="form.email" type="email" class="form-control" :class="{ 'is-invalid': errors.email }" autocomplete="username" required />
                        <div class="invalid-feedback">{{ errors.email }}</div>
                    </div>
                    <div class="mb-4">
                        <label class="form-label" for="password">Password</label>
                        <input id="password" v-model="form.password" type="password" class="form-control" :class="{ 'is-invalid': errors.password }" autocomplete="current-password" required />
                        <div class="invalid-feedback">{{ errors.password }}</div>
                    </div>
                    <button class="btn btn-primary w-100" :disabled="loading">
                        <span v-if="loading" class="spinner-border spinner-border-sm me-1"></span>Sign in
                    </button>
                </form>

                <p class="small text-center mt-3 mb-0">No account? <RouterLink :to="{ name: 'register' }">Create one</RouterLink></p>

                <hr />
                <p class="small text-body-secondary mb-2">Demo accounts (password: <code>password</code>)</p>
                <div class="d-flex gap-2">
                    <button type="button" class="btn btn-sm btn-outline-primary flex-fill" @click="fillDemo('user@example.com')">Employee</button>
                    <button type="button" class="btn btn-sm btn-outline-primary flex-fill" @click="fillDemo('admin@example.com')">Admin</button>
                </div>
            </div>
        </div>
    </div>
</template>
