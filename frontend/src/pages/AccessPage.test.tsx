import { render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { apiRequest } from '../lib/api';
import type { User } from '../types/api';
import { AccessPage } from './AccessPage';

vi.mock('../lib/api', () => ({
  ApiError: class ApiError extends Error {},
  apiRequest: vi.fn(),
  jsonBody: vi.fn((data: unknown) => JSON.stringify(data)),
}));

const apiRequestMock = vi.mocked(apiRequest);

function access(id: number, name = `Pessoa ${id}`): User {
  return {
    id,
    nome: name,
    email: `pessoa${id}@example.com`,
    usuario: `pessoa_${id}`,
    perfil: id % 2 === 0 ? 'ADMIN' : 'OPERADOR',
    ativo: true,
    criado_em: '2026-08-12T12:00:00+00:00',
    atualizado_em: '2026-08-12T12:00:00+00:00',
  };
}

describe('AccessPage', () => {
  beforeEach(() => {
    apiRequestMock.mockReset();
  });

  it('filtra os acessos por nome, usuario e perfil', async () => {
    apiRequestMock.mockResolvedValue({
      users: [access(1, 'João Operador'), access(2, 'Maria Administradora')],
    });
    const user = userEvent.setup();

    render(<AccessPage />);

    await screen.findByText('João Operador');
    await user.type(
      screen.getByRole('searchbox', { name: 'Buscar acessos' }),
      'maria admin',
    );

    expect(screen.getByText('Maria Administradora')).toBeInTheDocument();
    expect(screen.queryByText('João Operador')).not.toBeInTheDocument();
  });

  it('exibe dez acessos por pagina', async () => {
    apiRequestMock.mockResolvedValue({
      users: Array.from({ length: 11 }, (_, index) => access(index + 1)),
    });
    const user = userEvent.setup();

    render(<AccessPage />);

    await screen.findByText('Pessoa 1', { exact: true });
    expect(screen.getByText('Pessoa 10', { exact: true })).toBeInTheDocument();
    expect(screen.queryByText('Pessoa 11', { exact: true })).not.toBeInTheDocument();
    expect(screen.getByText('1-10 de 11')).toBeInTheDocument();

    await user.click(screen.getByRole('button', { name: 'Proxima pagina' }));

    await waitFor(() => {
      expect(screen.getByText('Pessoa 11', { exact: true })).toBeInTheDocument();
    });
    expect(screen.queryByText('Pessoa 1', { exact: true })).not.toBeInTheDocument();
    expect(screen.getByText('11-11 de 11')).toBeInTheDocument();
  });
});
