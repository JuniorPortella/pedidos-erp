import { Alert, Box, Button } from '@mui/material';
import { Navigate, Outlet, useLocation } from 'react-router-dom';
import { useAuth } from '../contexts/AuthContext';
import { LoadingScreen } from './LoadingScreen';

export function ProtectedRoute() {
  const { user, loading, sessionError } = useAuth();
  const location = useLocation();

  if (loading) {
    return <LoadingScreen label="Verificando sua sessao..." />;
  }

  if (sessionError) {
    return (
      <Box sx={{ maxWidth: 560, mx: 'auto', mt: 8, px: 2 }}>
        <Alert
          severity="error"
          action={
            <Button color="inherit" onClick={() => window.location.reload()}>
              Tentar novamente
            </Button>
          }
        >
          {sessionError}
        </Alert>
      </Box>
    );
  }

  if (!user) {
    return <Navigate to="/login" replace state={{ from: location }} />;
  }

  return <Outlet />;
}

export function AdminRoute() {
  const { user } = useAuth();

  if (user?.perfil !== 'ADMIN') {
    return <Navigate to="/" replace />;
  }

  return <Outlet />;
}
