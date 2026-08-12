import { render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { apiRequest } from '../lib/api';
import type { User } from '../types/api';
import { AccessPage } from './AccessPage';

vi.mock('../lib/api', () => ({
  ApiError: class ApiError extends Error {
    public constructor(
      public readonly status: number,
      message: string,
      public readonly fields: Record<string, string> = {},
    ) {
      super(message);
    }
  },
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

  it('cadastra um novo acesso e recarrega a listagem', async () => {
    apiRequestMock
      .mockResolvedValueOnce({ users: [] })
      .mockResolvedValueOnce({ user: access(1, 'Novo Usuario') })
      .mockResolvedValueOnce({ users: [access(1, 'Novo Usuario')] });
    const user = userEvent.setup();

    render(<AccessPage />);

    await screen.findByText('Nenhum acesso cadastrado.');
    await user.click(screen.getByRole('button', { name: 'Novo cadastro' }));
    await user.type(screen.getByLabelText(/^Nome/), 'Novo Usuario');
    await user.type(screen.getByLabelText(/^E-mail/), 'novo@example.com');
    await user.type(screen.getByLabelText(/^Usuario/), 'novo_usuario');
    await user.type(screen.getByLabelText(/^Senha/), 'Senha123!');
    await user.click(screen.getByRole('button', { name: 'Salvar acesso' }));

    await waitFor(() => {
      expect(apiRequestMock).toHaveBeenCalledWith('/auth/register', {
        method: 'POST',
        body: JSON.stringify({
          nome: 'Novo Usuario',
          email: 'novo@example.com',
          usuario: 'novo_usuario',
          senha: 'Senha123!',
          perfil: 'OPERADOR',
        }),
      });
    });
    expect(await screen.findByText('Novo Usuario')).toBeInTheDocument();
  });

  it('exclui um acesso somente depois da confirmacao', async () => {
    apiRequestMock
      .mockResolvedValueOnce({ users: [access(1, 'Usuario removido')] })
      .mockResolvedValueOnce(undefined)
      .mockResolvedValueOnce({ users: [] });
    const user = userEvent.setup();

    render(<AccessPage />);

    await screen.findByText('Usuario removido');
    await user.click(
      screen.getByRole('button', { name: 'Excluir Usuario removido' }),
    );

    expect(screen.getByRole('dialog', { name: 'Excluir acesso' }))
      .toHaveTextContent('Usuario removido');

    await user.click(screen.getByRole('button', { name: 'Excluir' }));

    await waitFor(() => {
      expect(apiRequestMock).toHaveBeenCalledWith('/usuarios/1', {
        method: 'DELETE',
      });
    });
    expect(await screen.findByText('Nenhum acesso cadastrado.'))
      .toBeInTheDocument();
  });

  it('mostra mensagem amigavel quando a listagem falha', async () => {
    const { ApiError } = await import('../lib/api');

    apiRequestMock.mockRejectedValue(
      new ApiError(500, 'Nao foi possivel carregar os acessos.'),
    );

    render(<AccessPage />);

    expect(await screen.findByRole('alert')).toHaveTextContent(
      'Nao foi possivel carregar os acessos.',
    );
  });
});
