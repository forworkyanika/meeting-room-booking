import { createRouter, createWebHistory } from 'vue-router';
import { useAuthStore } from '@/stores/auth';

const routes = [
    { path: '/login', name: 'login', component: () => import('@/pages/LoginPage.vue'), meta: { guest: true } },
    { path: '/register', name: 'register', component: () => import('@/pages/RegisterPage.vue'), meta: { guest: true } },

    { path: '/', name: 'calendar', component: () => import('@/pages/CalendarPage.vue'), meta: { auth: true } },
    { path: '/rooms', name: 'rooms', component: () => import('@/pages/RoomsPage.vue'), meta: { auth: true } },
    { path: '/my-bookings', name: 'my-bookings', component: () => import('@/pages/MyBookingsPage.vue'), meta: { auth: true } },

    { path: '/admin/bookings', name: 'admin-bookings', component: () => import('@/pages/admin/AdminBookingsPage.vue'), meta: { auth: true, admin: true } },
    { path: '/admin/rooms', name: 'admin-rooms', component: () => import('@/pages/admin/AdminRoomsPage.vue'), meta: { auth: true, admin: true } },

    { path: '/:pathMatch(.*)*', redirect: '/' },
];

const router = createRouter({
    history: createWebHistory(),
    routes,
    linkActiveClass: 'active',
});

// Route guard: runs before every navigation.
router.beforeEach((to) => {
    const auth = useAuthStore();

    if (to.meta.auth && !auth.isLoggedIn) return { name: 'login', query: { redirect: to.fullPath } };
    if (to.meta.guest && auth.isLoggedIn) return { name: 'calendar' };
    if (to.meta.admin && !auth.isAdmin) return { name: 'calendar' };
    return true;
});

export default router;
