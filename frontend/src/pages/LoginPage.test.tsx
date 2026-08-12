import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { MemoryRouter, Route, Routes } from 'react-router-dom';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { ApiError } from '../lib/api';
import type { AuthUser } from '../types/api';
import { LoginPage } from './LoginPage';

const { loginMock, useAuthMock } = vi.hoisted(() => ({
  loginMock: vi.fn(),
  useAuthMock: vi.fn(),
}));

vi.mock('../contexts/AuthContext', () => ({
  useAuth: () => useAuthMock(),
}));

const authenticatedUser: AuthUser = {
  id: 1,
  nome: 'Vagner Admin',
  email: 'admin@example.com',
  usuario: 'vagner',
  perfil: 'ADMIN',
};

function renderLogin(
  entry: string | { pathname: string; state?: unknown } = '/login',
) {
  render(
    <MemoryRouter initialEntries={[entry]}>
      <Routes>
        <Route path="/login" element={<LoginPage />} />
        <Route path="/" element={<div>Pagina inicial</div>} />
        <Route path="/pedidos" element={<div>Lista de pedidos</div>} />
      </Routes>
    </MemoryRouter>,
  );
}

describe('LoginPage', () => {
  beforeEach(() => {
    loginMock.mockReset();
    useAuthMock.mockReturnValue({
      user: null,
      loading: false,
      login: loginMock,
    });
  });

  it('envia as credenciais e retorna para a rota protegida', async () => {
    loginMock.mockResolvedValue(undefined);
    const user = userEvent.setup();

    renderLogin({
      pathname: '/login',
      state: { from: { pathname: '/pedidos' } },
    });

    await user.type(screen.getByLabelText(/^Usuario/), 'vagner');
    await user.type(screen.getByLabelText(/^Senha/), 'Senha123!');
    await user.click(screen.getByRole('button', { name: 'Entrar' }));

    expect(loginMock).toHaveBeenCalledWith('vagner', 'Senha123!');
    expect(await screen.findByText('Lista de pedidos')).toBeInTheDocument();
  });

  it('apresenta a mensagem devolvida pela API', async () => {
    loginMock.mockRejectedValue(
      new ApiError(401, 'Usuario ou senha invalidos.'),
    );
    const user = userEvent.setup();

    renderLogin();

    await user.type(screen.getByLabelText(/^Usuario/), 'vagner');
    await user.type(screen.getByLabelText(/^Senha/), 'incorreta');
    await user.click(screen.getByRole('button', { name: 'Entrar' }));

    expect(await screen.findByRole('alert')).toHaveTextContent(
      'Usuario ou senha invalidos.',
    );
  });

  it('alterna a visibilidade da senha', async () => {
    const user = userEvent.setup();

    renderLogin();

    const password = screen.getByLabelText(/^Senha/);

    expect(password).toHaveAttribute('type', 'password');

    await user.click(screen.getByRole('button', { name: 'Mostrar senha' }));

    expect(password).toHaveAttribute('type', 'text');
    expect(screen.getByRole('button', { name: 'Ocultar senha' }))
      .toBeInTheDocument();
  });

  it('redireciona usuario que ja esta autenticado', () => {
    useAuthMock.mockReturnValue({
      user: authenticatedUser,
      loading: false,
      login: loginMock,
    });

    renderLogin();

    expect(screen.getByText('Pagina inicial')).toBeInTheDocument();
  });
});
