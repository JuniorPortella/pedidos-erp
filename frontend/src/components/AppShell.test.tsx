import { render, screen, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { MemoryRouter, Route, Routes } from 'react-router-dom';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import type { AuthUser } from '../types/api';
import { AppShell } from './AppShell';

const { logoutMock, useAuthMock } = vi.hoisted(() => ({
  logoutMock: vi.fn(),
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

function renderShell(path = '/pedidos') {
  render(
    <MemoryRouter initialEntries={[path]}>
      <Routes>
        <Route path="/login" element={<div>Tela de login</div>} />
        <Route element={<AppShell />}>
          <Route path="/" element={<div>Inicio do sistema</div>} />
          <Route path="/pedidos" element={<div>Lista de pedidos</div>} />
          <Route path="/acessos" element={<div>Lista de acessos</div>} />
        </Route>
      </Routes>
    </MemoryRouter>,
  );
}

describe('AppShell', () => {
  beforeEach(() => {
    logoutMock.mockReset();
    logoutMock.mockResolvedValue(undefined);
    useAuthMock.mockReturnValue({ user: admin, logout: logoutMock });
  });

  it('mantem somente a entrada unificada de pedidos no menu', () => {
    renderShell();

    const ordersItem = screen
      .getByText('Pedidos')
      .closest('.MuiListItemButton-root');

    expect(ordersItem).toHaveClass('Mui-selected');
    expect(screen.queryByText('Novo pedido')).not.toBeInTheDocument();
  });

  it('oculta a administracao de acessos para operador', () => {
    useAuthMock.mockReturnValue({
      user: { ...admin, perfil: 'OPERADOR' },
      logout: logoutMock,
    });

    renderShell('/');

    expect(screen.queryByText('Acessos')).not.toBeInTheDocument();
    expect(screen.getByText('Operador')).toBeInTheDocument();
  });

  it('pede confirmacao e permite cancelar o logout', async () => {
    const user = userEvent.setup();

    renderShell('/');

    await user.click(screen.getByText('Sair'));

    const dialog = screen.getByRole('dialog', { name: 'Sair do sistema' });

    expect(dialog).toHaveTextContent(
      'Deseja realmente encerrar sua sessao?',
    );

    await user.click(within(dialog).getByRole('button', { name: 'Cancelar' }));

    await waitFor(() => {
      expect(screen.queryByRole('dialog')).not.toBeInTheDocument();
    });
    expect(logoutMock).not.toHaveBeenCalled();
  });

  it('encerra a sessao somente depois da confirmacao', async () => {
    const user = userEvent.setup();

    renderShell('/');

    await user.click(screen.getByText('Sair'));

    const dialog = screen.getByRole('dialog', { name: 'Sair do sistema' });
    await user.click(within(dialog).getByRole('button', { name: 'Sair' }));

    await waitFor(() => {
      expect(logoutMock).toHaveBeenCalledTimes(1);
    });
    expect(screen.getByText('Tela de login')).toBeInTheDocument();
  });
});
