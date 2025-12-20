import React, { createContext, useContext, useState, useEffect } from 'react';
import type { ReactNode } from 'react';
import type { User, Owner } from '@/types';

type UserType = 'user' | 'owner';

interface AuthContextType {
  user: User | Owner | null;
  userType: UserType | null;
  isAuthenticated: boolean;
  isLoading: boolean;
  login: (token: string, userType: UserType, userData: User | Owner) => void;
  logout: () => void;
}

const AuthContext = createContext<AuthContextType | undefined>(undefined);

export const AuthProvider: React.FC<{ children: ReactNode }> = ({ children }) => {
  const [user, setUser] = useState<User | Owner | null>(null);
  const [userType, setUserType] = useState<UserType | null>(null);
  const [isLoading, setIsLoading] = useState(true);

  useEffect(() => {
    // Check if user is already logged in
    const token = localStorage.getItem('token');
    const savedUserType = localStorage.getItem('userType') as UserType | null;
    const savedUser = localStorage.getItem('user');

    if (token && savedUserType && savedUser) {
      setUser(JSON.parse(savedUser));
      setUserType(savedUserType);
    }

    setIsLoading(false);
  }, []);

  const login = (token: string, type: UserType, userData: User | Owner) => {
    localStorage.setItem('token', token);
    localStorage.setItem('userType', type);
    localStorage.setItem('user', JSON.stringify(userData));
    setUser(userData);
    setUserType(type);
  };

  const logout = () => {
    localStorage.removeItem('token');
    localStorage.removeItem('userType');
    localStorage.removeItem('user');
    setUser(null);
    setUserType(null);
  };

  return (
    <AuthContext.Provider
      value={{
        user,
        userType,
        isAuthenticated: !!user,
        isLoading,
        login,
        logout,
      }}
    >
      {children}
    </AuthContext.Provider>
  );
};

export const useAuth = () => {
  const context = useContext(AuthContext);
  if (context === undefined) {
    throw new Error('useAuth must be used within an AuthProvider');
  }
  return context;
};
