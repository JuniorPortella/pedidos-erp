import { lazy, Suspense } from 'react';
import { Navigate, Route, Routes } from 'react-router-dom';
import { AppShell } from '../components/AppShell';
import { LoadingScreen } from '../components/LoadingScreen';
import { AdminRoute, ProtectedRoute } from '../components/ProtectedRoute';

const LoginPage = lazy(() =>
  import('../pages/LoginPage').then((module) => ({
    default: module.LoginPage,
  })),
);
const HomePage = lazy(() =>
  import('../pages/HomePage').then((module) => ({
    default: module.HomePage,
  })),
);
const OrdersPage = lazy(() =>
  import('../pages/OrdersPage').then((module) => ({
    default: module.OrdersPage,
  })),
);
const OrderFormPage = lazy(() =>
  import('../pages/OrderFormPage').then((module) => ({
    default: module.OrderFormPage,
  })),
);
const AccessPage = lazy(() =>
  import('../pages/AccessPage').then((module) => ({
    default: module.AccessPage,
  })),
);

export function App() {
  return (
    <Suspense fallback={<LoadingScreen />}>
      <Routes>
        <Route path="/login" element={<LoginPage />} />

        <Route element={<ProtectedRoute />}>
          <Route element={<AppShell />}>
            <Route index element={<HomePage />} />
            <Route path="pedidos" element={<OrdersPage />} />
            <Route path="pedidos/novo" element={<OrderFormPage />} />
            <Route path="pedidos/:id" element={<OrderFormPage />} />

            <Route element={<AdminRoute />}>
              <Route path="acessos" element={<AccessPage />} />
            </Route>
          </Route>
        </Route>

        <Route path="*" element={<Navigate to="/" replace />} />
      </Routes>
    </Suspense>
  );
}
