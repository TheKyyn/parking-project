import axios from 'axios';
import type { AxiosError, AxiosRequestConfig } from 'axios';
import type { ApiResponse, Parking, Reservation, Session } from '@/types';

const API_BASE_URL = import.meta.env.VITE_API_URL || 'http://localhost:8000';

// Create axios instance
const apiClient = axios.create({
  baseURL: API_BASE_URL,
  headers: {
    'Content-Type': 'application/json',
  },
});

// Request interceptor (add auth token)
apiClient.interceptors.request.use(
  (config) => {
    const token = localStorage.getItem('token');
    if (token) {
      config.headers.Authorization = `Bearer ${token}`;
    }
    return config;
  },
  (error) => {
    return Promise.reject(error);
  }
);

// Response interceptor (handle errors)
apiClient.interceptors.response.use(
  (response) => response,
  (error: AxiosError<ApiResponse<unknown>>) => {
    if (error.response?.status === 401) {
      // Unauthorized - clear token and redirect to login
      localStorage.removeItem('token');
      localStorage.removeItem('userType');
      window.location.href = '/login';
    }
    return Promise.reject(error);
  }
);

// Generic API methods
export const api = {
  async get<T>(url: string, config?: AxiosRequestConfig): Promise<ApiResponse<T>> {
    const response = await apiClient.get<ApiResponse<T>>(url, config);
    return response.data;
  },

  async post<T>(url: string, data?: unknown, config?: AxiosRequestConfig): Promise<ApiResponse<T>> {
    const response = await apiClient.post<ApiResponse<T>>(url, data, config);
    return response.data;
  },

  async put<T>(url: string, data?: unknown, config?: AxiosRequestConfig): Promise<ApiResponse<T>> {
    const response = await apiClient.put<ApiResponse<T>>(url, data, config);
    return response.data;
  },

  async delete<T>(url: string, config?: AxiosRequestConfig): Promise<ApiResponse<T>> {
    const response = await apiClient.delete<ApiResponse<T>>(url, config);
    return response.data;
  },
};

// Auth API
export const authApi = {
  loginUser: (data: { email: string; password: string }) =>
    api.post('/api/auth/login', data),

  loginOwner: (data: { email: string; password: string }) =>
    api.post('/api/owners/login', data),

  registerUser: (data: { email: string; password: string; firstName: string; lastName: string }) =>
    api.post('/api/users', data),

  registerOwner: (data: { email: string; password: string; firstName: string; lastName: string }) =>
    api.post('/api/owners', data),

  getUserProfile: () => api.get('/api/users/profile'),

  getOwnerProfile: () => api.get('/api/owners/profile'),
};

// Parking API
export const parkingApi = {
  getAll: (params?: { latitude?: number; longitude?: number; maxDistance?: number }) =>
    api.get<Parking[]>('/api/parkings', { params }),

  getById: (id: string) => api.get<Parking>(`/api/parkings/${id}`),

  create: (data: Omit<Parking, 'id' | 'ownerId' | 'createdAt'>) => {
    // Transform nested location to flat structure for backend
    const payload = {
      name: data.name,
      address: data.address,
      latitude: data.location.latitude,
      longitude: data.location.longitude,
      hourlyRate: data.hourlyRate,
      totalSpots: data.totalSpots,
    };
    return api.post<Parking>('/api/parkings', payload);
  },

  update: (id: string, data: Partial<Omit<Parking, 'id' | 'ownerId' | 'createdAt'>>) => {
    // Transform nested location to flat structure for backend
    const payload: Record<string, any> = {
      name: data.name,
      address: data.address,
      hourlyRate: data.hourlyRate,
      totalSpots: data.totalSpots,
    };
    if (data.location) {
      payload.latitude = data.location.latitude;
      payload.longitude = data.location.longitude;
    }
    return api.put<Parking>(`/api/parkings/${id}`, payload);
  },

  delete: (id: string) => api.delete(`/api/parkings/${id}`),
};

// Reservation API
export const reservationApi = {
  getAll: () => api.get<Reservation[]>('/api/reservations'),

  getById: (id: string) => api.get<Reservation>(`/api/reservations/${id}`),

  create: (data: { parkingId: string; startTime: string; endTime: string }) =>
    api.post<Reservation>('/api/reservations', data),

  cancel: (id: string) => api.delete(`/api/reservations/${id}`),
};

// Session API
export const sessionApi = {
  getAll: () => api.get<Session[]>('/api/sessions'),

  getById: (id: string) => api.get<Session>(`/api/sessions/${id}`),

  start: (data: { parkingId: string }) =>
    api.post<Session>('/api/sessions', data),

  end: (id: string) => api.put<Session>(`/api/sessions/${id}/end`),
};
