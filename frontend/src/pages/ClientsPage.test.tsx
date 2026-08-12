import { render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { apiRequest } from '../lib/api';
import type { Client } from '../types/api';
import { ClientsPage } from './ClientsPage';

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

function client(id: number, name = `Cliente ${id}`): Client {
  return {
    id,
    nome: name,
    telefone: `(11) 99999-${String(id).padStart(4, '0')}`,
    created_at: '2026-08-12T12:00:00+00:00',
    updated_at: '2026-08-12T12:00:00+00:00',
  };
}

describe('ClientsPage', () => {
  beforeEach(() => {
    apiRequestMock.mockReset();
  });

  it('filtra clientes por nome e telefone', async () => {
    apiRequestMock.mockResolvedValue({
      clients: [client(1, 'Empresa Alfa'), client(2, 'Empresa Beta')],
    });
    const user = userEvent.setup();

    render(<ClientsPage />);

    await screen.findByText('Empresa Alfa');
    await user.type(
      screen.getByRole('searchbox', { name: 'Buscar clientes' }),
      'beta 0002',
    );

    expect(screen.getByText('Empresa Beta')).toBeInTheDocument();
    expect(screen.queryByText('Empresa Alfa')).not.toBeInTheDocument();
  });

  it('exibe dez clientes por pagina', async () => {
    apiRequestMock.mockResolvedValue({
      clients: Array.from({ length: 11 }, (_, index) => client(index + 1)),
    });
    const user = userEvent.setup();

    render(<ClientsPage />);

    await screen.findByText('Cliente 1', { exact: true });
    expect(screen.getByText('Cliente 10', { exact: true })).toBeInTheDocument();
    expect(screen.queryByText('Cliente 11', { exact: true })).not.toBeInTheDocument();
    expect(screen.getByText('1-10 de 11')).toBeInTheDocument();

    await user.click(screen.getByRole('button', { name: 'Proxima pagina' }));

    expect(await screen.findByText('Cliente 11', { exact: true }))
      .toBeInTheDocument();
    expect(screen.getByText('11-11 de 11')).toBeInTheDocument();
  });

  it('cadastra um cliente e recarrega a lista', async () => {
    apiRequestMock
      .mockResolvedValueOnce({ clients: [] })
      .mockResolvedValueOnce({ client: client(1, 'Cliente Novo') })
      .mockResolvedValueOnce({ clients: [client(1, 'Cliente Novo')] });
    const user = userEvent.setup();

    render(<ClientsPage />);

    await screen.findByText('Nenhum cliente cadastrado.');
    await user.click(screen.getByRole('button', { name: 'Novo cadastro' }));
    await user.type(screen.getByLabelText(/^Nome/), 'Cliente Novo');
    await user.type(screen.getByLabelText(/^Telefone/), '(11) 99999-0001');
    await user.click(screen.getByRole('button', { name: 'Salvar cliente' }));

    await waitFor(() => {
      expect(apiRequestMock).toHaveBeenCalledWith('/clientes', {
        method: 'POST',
        body: JSON.stringify({
          nome: 'Cliente Novo',
          telefone: '(11) 99999-0001',
        }),
      });
    });
    expect(await screen.findByText('Cliente Novo')).toBeInTheDocument();
  });

  it('edita um cliente existente', async () => {
    apiRequestMock
      .mockResolvedValueOnce({ clients: [client(1, 'Cliente Original')] })
      .mockResolvedValueOnce({ client: client(1, 'Cliente Atualizado') })
      .mockResolvedValueOnce({ clients: [client(1, 'Cliente Atualizado')] });
    const user = userEvent.setup();

    render(<ClientsPage />);

    await screen.findByText('Cliente Original');
    await user.click(
      screen.getByRole('button', { name: 'Editar Cliente Original' }),
    );
    const nameField = screen.getByLabelText(/^Nome/);
    await user.clear(nameField);
    await user.type(nameField, 'Cliente Atualizado');
    await user.click(screen.getByRole('button', { name: 'Salvar cliente' }));

    await waitFor(() => {
      expect(apiRequestMock).toHaveBeenCalledWith('/clientes/1', {
        method: 'PUT',
        body: JSON.stringify({
          nome: 'Cliente Atualizado',
          telefone: '(11) 99999-0001',
        }),
      });
    });
    expect(await screen.findByText('Cliente Atualizado')).toBeInTheDocument();
  });

  it('exclui um cliente somente depois da confirmacao', async () => {
    apiRequestMock
      .mockResolvedValueOnce({ clients: [client(1, 'Cliente Removido')] })
      .mockResolvedValueOnce(undefined)
      .mockResolvedValueOnce({ clients: [] });
    const user = userEvent.setup();

    render(<ClientsPage />);

    await screen.findByText('Cliente Removido');
    await user.click(
      screen.getByRole('button', { name: 'Excluir Cliente Removido' }),
    );
    expect(screen.getByRole('dialog', { name: 'Excluir cliente' }))
      .toHaveTextContent('Cliente Removido');

    await user.click(screen.getByRole('button', { name: 'Excluir' }));

    await waitFor(() => {
      expect(apiRequestMock).toHaveBeenCalledWith('/clientes/1', {
        method: 'DELETE',
      });
    });
    expect(await screen.findByText('Nenhum cliente cadastrado.'))
      .toBeInTheDocument();
  });
});
