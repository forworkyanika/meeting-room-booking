import axios from 'axios';

const TOKEN_KEY = 'mrb_token';

export const tokenStorage = {
    get: () => localStorage.getItem(TOKEN_KEY),
    set: (token) => localStorage.setItem(TOKEN_KEY, token),
    clear: () => localStorage.removeItem(TOKEN_KEY),
};

const http = axios.create({
    baseURL: '/api',
    headers: { Accept: 'application/json' },
});

// Attach the Sanctum token to every request.
http.interceptors.request.use((config) => {
    const token = tokenStorage.get();
    if (token) config.headers.Authorization = `Bearer ${token}`;
    return config;
});

// A 401 means the token is missing, expired or revoked: drop it and go to login.
http.interceptors.response.use(
    (response) => response,
    (error) => {
        if (error.response?.status === 401 && tokenStorage.get()) {
            tokenStorage.clear();
            window.location.assign('/login');
        }
        return Promise.reject(error);
    },
);

/** Pull a human-readable message out of a Laravel error response. */
export function errorMessage(error, fallback = 'Something went wrong. Please try again.') {
    return error.response?.data?.message ?? fallback;
}

/** Laravel 422 responses carry { errors: { field: [messages] } }. */
export function validationErrors(error) {
    if (error.response?.status !== 422) return {};
    return Object.fromEntries(
        Object.entries(error.response.data.errors ?? {}).map(([field, messages]) => [field, messages[0]]),
    );
}

export default http;
