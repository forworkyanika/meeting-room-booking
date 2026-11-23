<script setup>
import { reactive, ref } from 'vue';
import { useRouter } from 'vue-router';
import { useAuthStore } from '@/stores/auth';
import { errorMessage, validationErrors } from '@/api/http';

const auth = useAuthStore();
const router = useRouter();

const form = reactive({ name: '', email: '', department: '', password: '', password_confirmation: '' });
const errors = ref({});
const message = ref('');
const loading = ref(false);

async function submit() {
    loading.value = true;
    errors.value = {};
    message.value = '';
    try {
        await auth.register(form);
        router.push({ name: 'calendar' });
    } catch (error) {
        errors.value = validationErrors(error);
        if (!Object.keys(errors.value).length) message.value = errorMessage(error);
    } finally {
        loading.value = false;
    }
}
</script>

<template>
    <div class="min-vh-100 d-flex align-items-center justify-content-center p-3">
        <div class="card shadow-sm w-100 auth-card">
            <div class="card-body p-4">
                <h1 class="h4 mb-4">Create an account</h1>
                <div v-if="message" class="alert alert-danger py-2">{{ message }}</div>

                <form novalidate @submit.prevent="submit">
                    <div class="mb-3">
                        <label class="form-label" for="name">Full name</label>
                        <input id="name" v-model.trim="form.name" class="form-control" :class="{ 'is-invalid': errors.name }" autocomplete="name" required />
                        <div class="invalid-feedback">{{ errors.name }}</div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="email">Work email</label>
                        <input id="email" v-model.trim="form.email" type="email" class="form-control" :class="{ 'is-invalid': errors.email }" autocomplete="email" required />
                        <div class="invalid-feedback">{{ errors.email }}</div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="department">Department <span class="text-body-secondary">(optional)</span></label>
                        <input id="department" v-model.trim="form.department" class="form-control" />
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="password">Password</label>
                        <input id="password" v-model="form.password" type="password" class="form-control" :class="{ 'is-invalid': errors.password }" autocomplete="new-password" required />
                        <div class="invalid-feedback">{{ errors.password }}</div>
                        <div class="form-text">At least 8 characters.</div>
                    </div>
                    <div class="mb-4">
                        <label class="form-label" for="password2">Confirm password</label>
                        <input id="password2" v-model="form.password_confirmation" type="password" class="form-control" autocomplete="new-password" required />
                    </div>
                    <button class="btn btn-primary w-100" :disabled="loading">
                        <span v-if="loading" class="spinner-border spinner-border-sm me-1"></span>Create account
                    </button>
                </form>
                <p class="small text-center mt-3 mb-0">Already registered? <RouterLink :to="{ name: 'login' }">Sign in</RouterLink></p>
            </div>
        </div>
    </div>
</template>
