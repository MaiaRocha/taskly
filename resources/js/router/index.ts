import { createRouter, createWebHistory } from 'vue-router';
import { useAuthStore } from '../stores/auth';

export const router = createRouter({
    history: createWebHistory(),
    routes: [
        {
            path: '/',
            redirect: { name: 'projects.index' },
        },
        {
            path: '/projects',
            component: () => import('../components/app/AppShell.vue'),
            meta: { requiresAuth: true },
            children: [
                {
                    path: '',
                    name: 'projects.index',
                    component: () => import('../pages/projects/ProjectsIndexPage.vue'),
                },
                {
                    path: ':projectId',
                    name: 'projects.show',
                    component: () => import('../pages/projects/ProjectDetailPage.vue'),
                },
            ],
        },
        {
            path: '/login',
            name: 'login',
            component: () => import('../pages/auth/LoginPage.vue'),
            meta: { guestOnly: true },
        },
        {
            path: '/register',
            name: 'register',
            component: () => import('../pages/auth/RegisterPage.vue'),
            meta: { guestOnly: true },
        },
    ],
});

router.beforeEach((to) => {
    const auth = useAuthStore();

    // Only a confirmed guest is redirected. 'error' means we couldn't
    // determine the session — App.vue keeps protected content hidden
    // until that resolves, instead of guessing here.
    if (to.meta.requiresAuth && auth.status === 'guest') {
        return { name: 'login' };
    }

    if (to.meta.guestOnly && auth.status === 'authenticated') {
        return { name: 'projects.index' };
    }

    return true;
});
