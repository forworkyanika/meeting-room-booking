<script setup>
import { useRouter } from 'vue-router';
import { useAuthStore } from '@/stores/auth';

const auth = useAuthStore();
const router = useRouter();

async function logout() {
    await auth.logout();
    router.push({ name: 'login' });
}
</script>

<template>
    <nav class="navbar navbar-expand-lg bg-white border-bottom sticky-top">
        <div class="container-fluid">
            <RouterLink class="navbar-brand text-primary" to="/"><i class="bi bi-calendar2-week me-2"></i>RoomBook</RouterLink>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNav" aria-controls="mainNav" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>

            <div id="mainNav" class="collapse navbar-collapse">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item"><RouterLink class="nav-link" :to="{ name: 'calendar' }" exact-active-class="active"><i class="bi bi-calendar3 me-1"></i>Calendar</RouterLink></li>
                    <li class="nav-item"><RouterLink class="nav-link" :to="{ name: 'rooms' }"><i class="bi bi-door-open me-1"></i>Rooms</RouterLink></li>
                    <li class="nav-item"><RouterLink class="nav-link" :to="{ name: 'my-bookings' }"><i class="bi bi-bookmark-check me-1"></i>My bookings</RouterLink></li>
                    <template v-if="auth.isAdmin">
                        <li class="nav-item"><RouterLink class="nav-link" :to="{ name: 'admin-bookings' }"><i class="bi bi-clipboard-check me-1"></i>Approvals</RouterLink></li>
                        <li class="nav-item"><RouterLink class="nav-link" :to="{ name: 'admin-rooms' }"><i class="bi bi-gear me-1"></i>Manage rooms</RouterLink></li>
                    </template>
                </ul>

                <div class="d-flex align-items-center gap-3">
                    <span class="small text-body-secondary">
                        <i class="bi bi-person-circle me-1"></i>{{ auth.user?.name }}
                        <span v-if="auth.isAdmin" class="badge text-bg-primary ms-1">Admin</span>
                    </span>
                    <button class="btn btn-outline-secondary btn-sm" @click="logout"><i class="bi bi-box-arrow-right me-1"></i>Log out</button>
                </div>
            </div>
        </div>
    </nav>
</template>
