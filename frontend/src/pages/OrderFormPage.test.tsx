import { fireEvent, render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { MemoryRouter, Route, Routes } from 'react-router-dom';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { ApiError, apiRequest, jsonBody } from '../lib/api';
import type { Order } from '../types/api';
import { OrderFormPage } from './OrderFormPage';

vi.mock('../lib/api', async (importOriginal) => {
  const original = await importOriginal<typeof import('../lib/api')>();

  return {
    ...original,
    apiRequest: vi.fn(),
    jsonBody: vi.fn((data: unknown) => JSON.stringify(data)),
  };
});

const apiRequestMock = vi.mocked(apiRequest);
const jsonBodyMock = vi.mocked(jsonBody);

const existingOrder: Order = {
  id: 42,
  cliente_nome: 'Cliente existente',
  descricao: 'Descricao existente',
  status: 'EM_PROCESSAMENTO',
  criado_por: 1,
  created_at: '2026-08-12T12:00:00+00:00',
  updated_at: '2026-08-12T12:00:00+00:00',
};

function renderForm(path = '/pedidos/novo') {
  render(
    <MemoryRouter initialEntries={[path]}>
      <Routes>
        <Route path="/pedidos/novo" element={<OrderFormPage />} />
        <Route path="/pedidos/:id" element={<OrderFormPage />} />
        <Route path="/pedidos" element={<div>Lista de pedidos</div>} />
      </Routes>
    </MemoryRouter>,
  );
}

describe('OrderFormPage', () => {
  beforeEach(() => {
    apiRequestMock.mockReset();
    jsonBodyMock.mockClear();
  });

  it('valida os campos obrigatorios antes de chamar a API', async () => {
    renderForm();

    const form = screen
      .getByRole('button', { name: 'Salvar pedido' })
      .closest('form');

    expect(form).not.toBeNull();

    fireEvent.submit(form!);

    expect(await screen.findByText('Informe o nome do cliente.'))
      .toBeInTheDocument();
    expect(screen.getByText('Informe a descricao do pedido.'))
      .toBeInTheDocument();
    expect(apiRequestMock).not.toHaveBeenCalled();
  });

  it('cria um pedido com o status inicial pendente', async () => {
    apiRequestMock.mockResolvedValue({ order: existingOrder });
    const user = userEvent.setup();

    renderForm();

    await user.type(screen.getByLabelText(/^Cliente/), 'Cliente novo');
    await user.type(screen.getByLabelText(/^Descricao/), 'Dois produtos');
    await user.click(screen.getByRole('button', { name: 'Salvar pedido' }));

    await waitFor(() => {
      expect(apiRequestMock).toHaveBeenCalledWith('/pedidos', {
        method: 'POST',
        body: JSON.stringify({
          cliente_nome: 'Cliente novo',
          descricao: 'Dois produtos',
          status: 'PENDENTE',
        }),
      });
    });
    expect(screen.getByRole('alert')).toHaveTextContent(
      'Pedido criado com sucesso.',
    );
  });

  it('apresenta os erros de validacao devolvidos pela API', async () => {
    apiRequestMock.mockRejectedValue(
      new ApiError(422, 'Dados invalidos.', {
        cliente_nome: 'Cliente nao permitido.',
      }),
    );
    const user = userEvent.setup();

    renderForm();

    await user.type(screen.getByLabelText(/^Cliente/), 'Cliente novo');
    await user.type(screen.getByLabelText(/^Descricao/), 'Descricao');
    await user.click(screen.getByRole('button', { name: 'Salvar pedido' }));

    expect(await screen.findByRole('alert')).toHaveTextContent(
      'Dados invalidos.',
    );
    expect(screen.getByText('Cliente nao permitido.')).toBeInTheDocument();
  });

  it('carrega e atualiza um pedido existente', async () => {
    apiRequestMock
      .mockResolvedValueOnce({ order: existingOrder })
      .mockResolvedValueOnce({ order: existingOrder });
    const user = userEvent.setup();

    renderForm('/pedidos/42');

    const customer = await screen.findByDisplayValue('Cliente existente');

    await user.clear(customer);
    await user.type(customer, 'Cliente atualizado');
    await user.click(screen.getByRole('button', { name: 'Salvar pedido' }));

    await waitFor(() => {
      expect(apiRequestMock).toHaveBeenLastCalledWith('/pedidos/42', {
        method: 'PUT',
        body: JSON.stringify({
          cliente_nome: 'Cliente atualizado',
          descricao: 'Descricao existente',
          status: 'EM_PROCESSAMENTO',
        }),
      });
    });
    expect(screen.getByRole('alert')).toHaveTextContent(
      'Pedido atualizado com sucesso.',
    );
  });

  it('mostra erro quando nao consegue carregar a edicao', async () => {
    apiRequestMock.mockRejectedValue(
      new ApiError(404, 'Pedido nao encontrado.'),
    );

    renderForm('/pedidos/999');

    expect(await screen.findByRole('alert')).toHaveTextContent(
      'Pedido nao encontrado.',
    );
  });
});
