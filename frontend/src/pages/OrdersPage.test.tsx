import { render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { MemoryRouter } from 'react-router-dom';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { apiRequest } from '../lib/api';
import type { Order } from '../types/api';
import { OrdersPage } from './OrdersPage';

vi.mock('../lib/api', () => ({
  ApiError: class ApiError extends Error {},
  apiRequest: vi.fn(),
}));

const apiRequestMock = vi.mocked(apiRequest);

function order(id: number, customerName = `Cliente ${id}`): Order {
  return {
    id,
    cliente_nome: customerName,
    descricao: `Descricao do pedido ${id}`,
    status: id % 2 === 0 ? 'CONCLUIDO' : 'PENDENTE',
    criado_por: 1,
    created_at: '2026-08-12T12:00:00+00:00',
    updated_at: '2026-08-12T12:00:00+00:00',
  };
}

function renderPage() {
  render(
    <MemoryRouter>
      <OrdersPage />
    </MemoryRouter>,
  );
}

describe('OrdersPage', () => {
  beforeEach(() => {
    apiRequestMock.mockReset();
  });

  it('filtra os pedidos no frontend sem diferenciar acentos', async () => {
    apiRequestMock.mockResolvedValue({
      orders: [order(1, 'João da Silva'), order(2, 'Maria Souza')],
    });
    const user = userEvent.setup();

    renderPage();

    await screen.findByText('João da Silva');
    await user.type(screen.getByRole('searchbox', { name: 'Buscar pedidos' }), 'joao');

    expect(screen.getByText('João da Silva')).toBeInTheDocument();
    expect(screen.queryByText('Maria Souza')).not.toBeInTheDocument();
  });

  it('exibe dez pedidos por pagina', async () => {
    apiRequestMock.mockResolvedValue({
      orders: Array.from({ length: 11 }, (_, index) => order(index + 1)),
    });
    const user = userEvent.setup();

    renderPage();

    await screen.findByText('Cliente 1', { exact: true });
    expect(screen.getByText('Cliente 10', { exact: true })).toBeInTheDocument();
    expect(screen.queryByText('Cliente 11', { exact: true })).not.toBeInTheDocument();
    expect(screen.getByText('1-10 de 11')).toBeInTheDocument();

    await user.click(screen.getByRole('button', { name: 'Proxima pagina' }));

    await waitFor(() => {
      expect(screen.getByText('Cliente 11', { exact: true })).toBeInTheDocument();
    });
    expect(screen.queryByText('Cliente 1', { exact: true })).not.toBeInTheDocument();
    expect(screen.getByText('11-11 de 11')).toBeInTheDocument();
  });
});
