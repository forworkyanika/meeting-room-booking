import 'bootstrap';
import { createApp } from 'vue';
import { createPinia } from 'pinia';
import App from './App.vue';
import router from './router';
import { useAuthStore } from './stores/auth';

const app = createApp(App);
app.use(createPinia());

// Restore the session (if a token is saved) before the first route guard runs.
useAuthStore()
    .restore()
    .then(() => {
        app.use(router);
        app.mount('#app');
    });
