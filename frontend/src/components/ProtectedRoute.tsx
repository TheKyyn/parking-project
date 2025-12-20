import { Navigate } from 'react-router-dom';
import { useAuth } from '@/contexts/AuthContext';
import { Loader2 } from 'lucide-react';

interface ProtectedRouteProps {
  children: React.ReactNode;
  requiredUserType?: 'user' | 'owner';
}

export const ProtectedRoute = ({ children, requiredUserType }: ProtectedRouteProps) => {
  const { isAuthenticated, userType, isLoading } = useAuth();

  // Show loading spinner while checking auth
  if (isLoading) {
    return (
      <div className="flex items-center justify-center min-h-[60vh]">
        <Loader2 className="h-8 w-8 animate-spin text-primary" />
      </div>
    );
  }

  // Not authenticated → redirect to login
  if (!isAuthenticated) {
    return <Navigate to="/login" replace />;
  }

  // Authenticated but wrong user type → redirect to appropriate dashboard
  if (requiredUserType && userType !== requiredUserType) {
    const redirectTo = userType === 'owner' ? '/owner/dashboard' : '/user/dashboard';
    return <Navigate to={redirectTo} replace />;
  }

  // Authenticated and correct user type → render children
  return <>{children}</>;
};
