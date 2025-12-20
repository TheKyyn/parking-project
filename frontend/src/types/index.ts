// User types
export interface User {
  userId: string;
  email: string;
  name?: string;
  firstName?: string;
  lastName?: string;
  createdAt?: string;
}

export interface Owner {
  ownerId: string;
  email: string;
  firstName: string;
  lastName: string;
  createdAt?: string;
}

// Auth types
export interface LoginRequest {
  email: string;
  password: string;
}

export interface RegisterUserRequest {
  email: string;
  password: string;
  firstName: string;
  lastName: string;
}

export interface RegisterOwnerRequest {
  email: string;
  password: string;
  firstName: string;
  lastName: string;
}

export interface AuthResponse {
  token: string;
  userId?: string;
  ownerId?: string;
  email: string;
  name?: string;
  firstName?: string;
  lastName?: string;
  expiresIn: number;
}

// Parking types
export interface Parking {
  id: string;
  ownerId: string;
  name: string;
  address: string;
  location: {
    latitude: number;
    longitude: number;
  };
  hourlyRate: number;
  totalSpots: number;
  availableSpots: number;
  createdAt?: string;
}

// Reservation types
export interface Reservation {
  id: string;
  userId: string;
  parkingId: string;
  parking?: {
    id: string;
    name: string;
    address: string;
    hourlyRate: number;
  };
  user?: {
    id: string;
    firstName: string;
    lastName: string;
    email: string;
  };
  startTime: string;
  endTime: string;
  totalAmount: number;
  status: 'pending' | 'confirmed' | 'active' | 'completed' | 'cancelled';
  createdAt?: string;
}

// Session types
export interface Session {
  id: string;
  reservationId: string;
  userId: string;
  parkingId: string;
  startTime: string;
  endTime?: string;
  estimatedCost?: number;
  actualCost?: number;
  totalAmount?: number;
  status: 'active' | 'completed' | 'overstayed';
  createdAt?: string;
}

// API response types
export interface ApiResponse<T> {
  success: boolean;
  message: string;
  data?: T;
  errors?: Record<string, string[]>;
}
