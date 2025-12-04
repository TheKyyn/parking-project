import { BrowserRouter, Routes, Route, Navigate } from 'react-router-dom';
import { AuthProvider, useAuth } from '@/contexts/AuthContext';
import { Navbar } from '@/components/layout/Navbar';
import { Footer } from '@/components/layout/Footer';
import { ProtectedRoute } from '@/components/ProtectedRoute';
import { Landing } from '@/pages/Landing';
import { Login } from '@/pages/Login';
import { Register } from '@/pages/Register';
import { UserDashboard } from '@/pages/UserDashboard';
import { OwnerDashboard } from '@/pages/OwnerDashboard';
import { NotFound } from '@/pages/NotFound';

// Home redirect based on auth status
const HomeRedirect = () => {
  const { isAuthenticated, userType, isLoading } = useAuth();

  if (isLoading) {
    return null; // or a loading spinner
  }

  if (isAuthenticated) {
    return <Navigate to={userType === 'owner' ? '/owner/dashboard' : '/user/dashboard'} replace />;
  }

  return <Landing />;
};

function App() {
  return (
    <AuthProvider>
      <BrowserRouter>
        <div className="flex flex-col min-h-screen">
          <Navbar />
          <main className="flex-1">
            <Routes>
              {/* Public routes */}
              <Route path="/" element={<HomeRedirect />} />
              <Route path="/login" element={<Login />} />
              <Route path="/register" element={<Register />} />

              {/* Protected routes - User */}
              <Route
                path="/user/dashboard"
                element={
                  <ProtectedRoute requiredUserType="user">
                    <UserDashboard />
                  </ProtectedRoute>
                }
              />

              {/* Protected routes - Owner */}
              <Route
                path="/owner/dashboard"
                element={
                  <ProtectedRoute requiredUserType="owner">
                    <OwnerDashboard />
                  </ProtectedRoute>
                }
              />

              {/* More routes will be added in next tickets */}
              <Route path="*" element={<NotFound />} />
            </Routes>
          </main>
          <Footer />
        </div>
      </BrowserRouter>
    </AuthProvider>
  );
}

export default App;
