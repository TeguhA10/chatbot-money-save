import axios from 'axios';

const api = axios.create({
    baseURL: '/api/v1',
    headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
    },
});

// Request interceptor to attach Sanctum token
api.interceptors.request.use(
    (config) => {
        const token = localStorage.getItem('token');
        if (token) {
            config.headers.Authorization = `Bearer ${token}`;
        }
        return config;
    },
    (error) => Promise.reject(error)
);

// Response interceptor to handle unauthenticated 401s
api.interceptors.response.use(
    (response) => response,
    (error) => {
        if (error.response && error.response.status === 401) {
            const currentPath = typeof window !== 'undefined' ? window.location.pathname : '';
            const isPublicRoute = currentPath === '/' || currentPath.startsWith('/login') || currentPath.startsWith('/pay') || currentPath.startsWith('/docs') || currentPath.startsWith('/dokumentasi') || currentPath.startsWith('/privasi');

            // Only clear credentials if the request actually carried an Authorization header
            if (error.config?.headers?.Authorization) {
                localStorage.removeItem('token');
                localStorage.removeItem('user');
                if (!isPublicRoute) {
                    window.location.href = '/login';
                }
            }
        }
        return Promise.reject(error);
    }
);

export default api;
