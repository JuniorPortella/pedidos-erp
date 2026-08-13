import { act, render, screen, waitFor } from '@testing-library/react';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { apiRequest } from '../lib/api';
import { invalidateSession } from '../lib/session';
import type { AuthUser } from '../types/api';
import { AuthProvider, useAuth } from './AuthContext';

vi.mock('../lib/api', async (importOriginal) => {
  const actual = await importOriginal<typeof import('../lib/api')>();

  return {
    ...actual,
    apiRequest: vi.fn(),
  };
});

const apiRequestMock = vi.mocked(apiRequest);

const user: AuthUser = {
  id: 2,
  nome: 'Administrador',
  email: 'admin@example.com',
  usuario: 'admin',
  perfil: 'ADMIN',
};

function SessionStatus() {
  const { user: authenticatedUser, loading } = useAuth();

  if (loading) {
    return <div>Carregando</div>;
  }

  return <div>{authenticatedUser?.usuario ?? 'Sem sessao'}</div>;
}

describe('AuthProvider', () => {
  beforeEach(() => {
    apiRequestMock.mockReset();
    apiRequestMock.mockResolvedValue({ user });
  });

  it('limpa o usuario quando a sessao e invalidada', async () => {
    render(
      <AuthProvider>
        <SessionStatus />
      </AuthProvider>,
    );

    await waitFor(() => {
      expect(screen.getByText('admin')).toBeInTheDocument();
    });

    act(() => invalidateSession());

    expect(screen.getByText('Sem sessao')).toBeInTheDocument();
  });
});
