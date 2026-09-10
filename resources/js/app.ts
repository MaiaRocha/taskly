import { createApp } from 'vue';
import { createPinia } from 'pinia';
import App from './App.vue';
import { onSessionInvalid } from './lib/http';
import { router } from './router';
import { useAuthStore } from './stores/auth';

const pinia = createPinia();
const app = createApp(App);

app.use(pinia);

const auth = useAuthStore();

// A 401/419 only means "session really expired" if we thought we were
// authenticated; a guest's very first request (e.g. bootstrap() below)
// also gets a 401 and must not force a navigation on its own.
onSessionInvalid(() => {
    const wasAuthenticated = auth.status === 'authenticated';

    auth.clearSession();

    if (wasAuthenticated) {
        // replace, not push: the expired session shouldn't leave a protected
        // route one "back" tap away from the freshly-cleared login screen.
        router.replace({ name: 'login' });
    }
});

await auth.bootstrap();

app.use(router);
app.mount('#app');
