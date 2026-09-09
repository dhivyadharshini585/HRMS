import axios from 'axios';
import { toast } from 'react-toastify';
import store from '../store';
import { logout } from '../store/authSlice';

const api = axios.create({
  baseURL: import.meta.env.VITE_API_BASE_URL || 'http://localhost:8000/api',
  headers: {
    'Content-Type': 'application/json',
    'Accept': 'application/json',
  },
});

api.interceptors.request.use(
  (config) => {
    // Get token from local storage directly to ensure it's always the latest
    const token = localStorage.getItem('token');
    if (token) {
      config.headers.Authorization = `Bearer ${token}`;
    }
    return config;
  },
  (error) => Promise.reject(error)
);

api.interceptors.response.use(
  (response) => response,
  (error) => {
    // Handle 401 Unauthorized globally
    if (error.response && error.response.status === 401) {
      toast.error('Session expired. Please log in again.');
      store.dispatch(logout()); // This clears state and localStorage
    } else if (error.response && error.response.data && error.response.data.message) {
      // Toast other API errors
      toast.error(error.response.data.message);
    } else {
      toast.error('An unexpected error occurred.');
    }
    
    return Promise.reject(error);
  }
);

export default api;