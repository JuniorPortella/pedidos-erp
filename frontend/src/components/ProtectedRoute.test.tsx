import { render, screen } from '@testing-library/react';
import {
  MemoryRouter,
  Outlet,
  Route,
  Routes,
  useLocation,
} from 'react-router-dom';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import type { AuthUser } from '../types/api';
import { AdminRoute, ProtectedRoute } from './ProtectedRoute';

const { useAuthMock } = vi.hoisted(() => ({
  useAuthMock: vi.fn(),
}));

vi.mock('../contexts/AuthContext', () => ({
  useAuth: () => useAuthMock(),
}));

const admin: AuthUser = {
  id: 1,
  nome: 'Vagner Admin',
  email: 'admin@example.com',
  usuario: 'vagner',
  perfil: 'ADMIN',
};

function LoginDestination() {
  const location = useLocation();
  const state = location.state as { from?: { pathname?: string } } | null;

  return <div>Login vindo de {state?.from?.pathname ?? 'nenhuma rota'}</div>;
}

function renderProtectedRoute() {
  render(
    <MemoryRouter initialEntries={['/pedidos']}>
      <Routes>
        <Route path="/login" element={<LoginDestination />} />
        <Route element={<ProtectedRoute />}>
          <Route path="/pedidos" element={<div>Pedidos protegidos</div>} />
        </Route>
      </Routes>
    </MemoryRouter>,
  );
}

function renderAdminRoute(user: AuthUser) {
  useAuthMock.mockReturnValue({ user });

  render(
    <MemoryRouter initialEntries={['/acessos']}>
      <Routes>
        <Route path="/" element={<div>Inicio</div>} />
        <Route element={<AdminRoute />}>
          <Route path="/acessos" element={<div>Administrar acessos</div>} />
        </Route>
      </Routes>
    </MemoryRouter>,
  );
}

describe('ProtectedRoute', () => {
  beforeEach(() => {
    useAuthMock.mockReset();
  });

  it('exibe carregamento enquanto verifica a sessao', () => {
    useAuthMock.mockReturnValue({
      user: null,
      loading: true,
      sessionError: null,
    });

    renderProtectedRoute();

    expect(screen.getByRole('status')).toHaveTextContent(
      'Verificando sua sessao...',
    );
  });

  it('preserva a rota de origem ao redirecionar para o login', () => {
    useAuthMock.mockReturnValue({
      user: null,
      loading: false,
      sessionError: null,
    });

    renderProtectedRoute();

    expect(screen.getByText('Login vindo de /pedidos')).toBeInTheDocument();
  });

  it('mostra erro de conexao sem redirecionar', () => {
    useAuthMock.mockReturnValue({
      user: null,
      loading: false,
      sessionError: 'Nao foi possivel conectar com a API.',
    });

    renderProtectedRoute();

    expect(screen.getByRole('alert')).toHaveTextContent(
      'Nao foi possivel conectar com a API.',
    );
    expect(screen.getByRole('button', { name: 'Tentar novamente' }))
      .toBeInTheDocument();
  });

  it('renderiza a rota para usuario autenticado', () => {
    useAuthMock.mockReturnValue({
      user: admin,
      loading: false,
      sessionError: null,
    });

    renderProtectedRoute();

    expect(screen.getByText('Pedidos protegidos')).toBeInTheDocument();
  });
});

describe('AdminRoute', () => {
  beforeEach(() => {
    useAuthMock.mockReset();
  });

  it('permite acesso para administrador', () => {
    renderAdminRoute(admin);

    expect(screen.getByText('Administrar acessos')).toBeInTheDocument();
  });

  it('redireciona operador para o inicio', () => {
    renderAdminRoute({ ...admin, perfil: 'OPERADOR' });

    expect(screen.getByText('Inicio')).toBeInTheDocument();
    expect(screen.queryByText('Administrar acessos')).not.toBeInTheDocument();
  });
});
