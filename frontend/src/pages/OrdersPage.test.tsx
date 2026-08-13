import { fireEvent, render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { MemoryRouter, Route, Routes } from 'react-router-dom';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { ApiError, apiRequest, jsonBody } from '../lib/api';
import { downloadSalesReport } from '../lib/salesReport';
import type { AuthUser, Client, Order } from '../types/api';
import { OrdersPage } from './OrdersPage';

const { useAuthMock } = vi.hoisted(() => ({
  useAuthMock: vi.fn(),
}));

vi.mock('../lib/api', async (importOriginal) => {
  const original = await importOriginal<typeof import('../lib/api')>();

  return {
    ...original,
    apiRequest: vi.fn(),
    jsonBody: vi.fn((data: unknown) => JSON.stringify(data)),
  };
});

vi.mock('../contexts/AuthContext', () => ({
  useAuth: () => useAuthMock(),
}));

vi.mock('../lib/salesReport', async (importOriginal) => ({
  ...await importOriginal<typeof import('../lib/salesReport')>(),
  downloadSalesReport: vi.fn(),
}));

const apiRequestMock = vi.mocked(apiRequest);
const jsonBodyMock = vi.mocked(jsonBody);
const downloadSalesReportMock = vi.mocked(downloadSalesReport);

const admin: AuthUser = {
  id: 1,
  nome: 'Vagner Admin',
  email: 'admin@example.com',
  usuario: 'vagner',
  perfil: 'ADMIN',
};

function order(id: number): Order {
  return {
    id,
    cliente_id: id,
    descricao: `Descricao do pedido ${id}`,
    valor_total: `${id * 10}.00`,
    status: id % 2 === 0 ? 'CONCLUIDO' : 'PENDENTE',
    criado_por: 1,
    created_at: '2026-08-12T12:00:00+00:00',
    updated_at: '2026-08-12T12:00:00+00:00',
  };
}

function mockInitialData(orders: Order[], clients: Client[] = []) {
  apiRequestMock
    .mockResolvedValueOnce({ orders })
    .mockResolvedValueOnce({ clients });
}

function client(id: number, name = `Cliente ${id}`): Client {
  return {
    id,
    nome: name,
    telefone: `(11) 99999-${String(id).padStart(4, '0')}`,
    created_at: '2026-08-12T12:00:00+00:00',
    updated_at: '2026-08-12T12:00:00+00:00',
  };
}

function renderPage(state?: { openNewOrder: boolean }) {
  render(
    <MemoryRouter initialEntries={[{ pathname: '/pedidos', state }]}>
      <Routes>
        <Route path="/pedidos" element={<OrdersPage />} />
      </Routes>
    </MemoryRouter>,
  );
}

describe('OrdersPage', () => {
  beforeEach(() => {
    apiRequestMock.mockReset();
    jsonBodyMock.mockClear();
    downloadSalesReportMock.mockReset();
    downloadSalesReportMock.mockResolvedValue(undefined);
    useAuthMock.mockReturnValue({ user: admin });
  });

  it('mostra o PDF para administradores', async () => {
    mockInitialData([order(1)], [client(1)]);

    renderPage();

    expect(await screen.findByRole('button', { name: 'Baixar PDF' }))
      .toBeEnabled();
  });

  it('oculta o PDF para operadores', async () => {
    useAuthMock.mockReturnValue({
      user: { ...admin, perfil: 'OPERADOR' },
    });
    mockInitialData([order(1)], [client(1)]);

    renderPage();

    await screen.findByText('Cliente 1');
    expect(screen.queryByRole('button', { name: 'Baixar PDF' }))
      .not.toBeInTheDocument();
  });

  it('desabilita o PDF quando nao existem pedidos', async () => {
    mockInitialData([], []);

    renderPage();

    await screen.findByText('Nenhum pedido cadastrado.');
    expect(screen.getByRole('button', { name: 'Baixar PDF' })).toBeDisabled();
  });

  it('envia todos os resultados filtrados ao PDF', async () => {
    const orders = Array.from({ length: 12 }, (_, index) => order(index + 1));
    const clients = orders.map((item) => client(item.cliente_id));
    mockInitialData(orders, clients);
    const user = userEvent.setup();

    renderPage();

    await screen.findByText('1-10 de 12');
    await user.type(
      screen.getByRole('searchbox', { name: 'Buscar pedidos' }),
      'Concluido',
    );
    await user.click(screen.getByRole('button', { name: 'Baixar PDF' }));

    expect(downloadSalesReportMock).toHaveBeenCalledWith(
      orders.filter((item) => item.status === 'CONCLUIDO'),
      clients,
    );
  });

  it('gera o PDF com todas as paginas da lista', async () => {
    const orders = Array.from({ length: 11 }, (_, index) => order(index + 1));
    const clients = orders.map((item) => client(item.cliente_id));
    mockInitialData(orders, clients);
    const user = userEvent.setup();

    renderPage();

    await screen.findByText('1-10 de 11');
    await user.click(screen.getByRole('button', { name: 'Baixar PDF' }));

    expect(downloadSalesReportMock).toHaveBeenCalledWith(orders, clients);
  });

  it('apresenta erro quando nao consegue gerar o PDF', async () => {
    mockInitialData([order(1)], [client(1)]);
    downloadSalesReportMock.mockRejectedValueOnce(new Error('Falha no PDF'));
    const user = userEvent.setup();

    renderPage();

    await user.click(await screen.findByRole('button', { name: 'Baixar PDF' }));

    expect(await screen.findByRole('alert')).toHaveTextContent(
      'Nao foi possivel gerar o PDF.',
    );
  });

  it('filtra os pedidos no frontend sem diferenciar acentos', async () => {
    mockInitialData(
      [order(1), order(2)],
      [client(1, 'Joao da Silva'), client(2, 'Maria Souza')],
    );
    const user = userEvent.setup();

    renderPage();

    await screen.findByText('Joao da Silva');
    expect(apiRequestMock).toHaveBeenNthCalledWith(1, '/pedidos');
    expect(apiRequestMock).toHaveBeenNthCalledWith(2, '/clientes');
    await user.type(screen.getByRole('searchbox', { name: 'Buscar pedidos' }), 'joao');

    expect(screen.getByText('Joao da Silva')).toBeInTheDocument();
    expect(screen.queryByText('Maria Souza')).not.toBeInTheDocument();
  });

  it('exibe dez pedidos por pagina', async () => {
    const orders = Array.from({ length: 11 }, (_, index) => order(index + 1));
    mockInitialData(
      orders,
      orders.map((item) => client(item.cliente_id)),
    );
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

  it('exibe mensagem quando nao existem pedidos', async () => {
    mockInitialData([], []);

    renderPage();

    expect(await screen.findByText('Nenhum pedido cadastrado.'))
      .toBeInTheDocument();
  });

  it('mostra o erro e permite tentar novamente', async () => {
    apiRequestMock
      .mockRejectedValueOnce(
        new ApiError(500, 'Nao foi possivel carregar os pedidos.'),
      )
      .mockResolvedValueOnce({ clients: [client(1)] })
      .mockResolvedValueOnce({ orders: [order(1)] });
    const user = userEvent.setup();

    renderPage();

    expect(await screen.findByRole('alert')).toHaveTextContent(
      'Nao foi possivel carregar os pedidos.',
    );

    await user.click(screen.getByRole('button', { name: 'Tentar novamente' }));

    expect(await screen.findByText('Cliente 1')).toBeInTheDocument();
  });

  it('abre e fecha o formulario na mesma pagina', async () => {
    mockInitialData([], []);
    const user = userEvent.setup();

    renderPage();

    await screen.findByText('Nenhum pedido cadastrado.');
    await user.click(screen.getByRole('button', { name: 'Novo pedido' }));

    expect(screen.getByRole('heading', { name: 'Novo pedido' }))
      .toBeInTheDocument();
    expect(screen.getByText('Nenhum pedido cadastrado.')).toBeInTheDocument();

    await user.click(screen.getByRole('button', { name: 'Cancelar' }));

    expect(screen.queryByRole('heading', { name: 'Novo pedido' }))
      .not.toBeInTheDocument();
  });

  it('abre o formulario pelo atalho recebido na navegacao', async () => {
    mockInitialData([], []);

    renderPage({ openNewOrder: true });

    expect(await screen.findByRole('heading', { name: 'Novo pedido' }))
      .toBeInTheDocument();
  });

  it('valida os campos obrigatorios antes de chamar a API', async () => {
    mockInitialData([], []);
    const user = userEvent.setup();

    renderPage();

    await screen.findByText('Nenhum pedido cadastrado.');
    await user.click(screen.getByRole('button', { name: 'Novo pedido' }));

    const form = screen
      .getByRole('button', { name: 'Salvar pedido' })
      .closest('form');

    expect(form).not.toBeNull();
    fireEvent.submit(form!);

    expect(await screen.findByText('Selecione um cliente.'))
      .toBeInTheDocument();
    expect(screen.getByText('Informe a descricao do pedido.'))
      .toBeInTheDocument();
    expect(screen.getByText('Informe um valor maior que zero.'))
      .toBeInTheDocument();
    expect(apiRequestMock).toHaveBeenCalledTimes(2);
  });

  it('cria o pedido e atualiza a lista na mesma pagina', async () => {
    const createdOrder = order(12);
    createdOrder.cliente_id = 1;

    apiRequestMock
      .mockResolvedValueOnce({ orders: [] })
      .mockResolvedValueOnce({ clients: [client(1, 'Cliente novo')] })
      .mockResolvedValueOnce({ order: createdOrder })
      .mockResolvedValueOnce({ orders: [createdOrder] });
    const user = userEvent.setup();

    renderPage();

    await screen.findByText('Nenhum pedido cadastrado.');
    await user.click(screen.getByRole('button', { name: 'Novo pedido' }));
    await user.click(screen.getByLabelText(/^Cliente/));
    await user.click(screen.getByRole('option', {
      name: /Cliente novo/,
    }));
    await user.type(screen.getByLabelText(/^Descricao/), 'Dois produtos');
    await user.type(screen.getByLabelText(/^Valor total/), '149,90');
    await user.click(screen.getByRole('button', { name: 'Salvar pedido' }));

    await waitFor(() => {
      expect(apiRequestMock).toHaveBeenNthCalledWith(3, '/pedidos', {
        method: 'POST',
        body: JSON.stringify({
          cliente_id: 1,
          descricao: 'Dois produtos',
          valor_total: '149.90',
          status: 'PENDENTE',
        }),
      });
    });
    expect(await screen.findByText('Pedido criado com sucesso.'))
      .toBeInTheDocument();
    expect(screen.getByText('Cliente novo')).toBeInTheDocument();
    expect(screen.queryByRole('heading', { name: 'Novo pedido' }))
      .not.toBeInTheDocument();
  });

  it('apresenta os erros de validacao devolvidos pela API', async () => {
    apiRequestMock
      .mockResolvedValueOnce({ orders: [] })
      .mockResolvedValueOnce({ clients: [client(1, 'Cliente novo')] })
      .mockRejectedValueOnce(
        new ApiError(422, 'Dados invalidos.', {
          cliente_id: 'Cliente nao permitido.',
        }),
      );
    const user = userEvent.setup();

    renderPage();

    await screen.findByText('Nenhum pedido cadastrado.');
    await user.click(screen.getByRole('button', { name: 'Novo pedido' }));
    await user.click(screen.getByLabelText(/^Cliente/));
    await user.click(screen.getByRole('option', {
      name: /Cliente novo/,
    }));
    await user.type(screen.getByLabelText(/^Descricao/), 'Descricao');
    await user.type(screen.getByLabelText(/^Valor total/), '10');
    await user.click(screen.getByRole('button', { name: 'Salvar pedido' }));

    expect(await screen.findByRole('alert')).toHaveTextContent('Dados invalidos.');
    expect(screen.getByText('Cliente nao permitido.')).toBeInTheDocument();
  });

  it('preenche e atualiza um pedido pela lista', async () => {
    const existingOrder = order(42);
    existingOrder.cliente_id = 1;
    existingOrder.descricao = 'Descricao existente';
    existingOrder.valor_total = '100.00';
    existingOrder.status = 'EM_PROCESSAMENTO';

    const updatedOrder = {
      ...existingOrder,
      cliente_id: 2,
      valor_total: '150.50',
    };

    apiRequestMock
      .mockResolvedValueOnce({ orders: [existingOrder] })
      .mockResolvedValueOnce({
        clients: [
          client(1, 'Cliente existente'),
          client(2, 'Cliente atualizado'),
        ],
      })
      .mockResolvedValueOnce({ order: updatedOrder })
      .mockResolvedValueOnce({ orders: [updatedOrder] });
    const user = userEvent.setup();

    renderPage();

    await screen.findByText('Cliente existente');
    await user.click(screen.getByRole('button', { name: 'Editar pedido 42' }));

    const customer = screen.getByLabelText(/^Cliente/);
    expect(screen.getByDisplayValue('Descricao existente')).toBeInTheDocument();
    expect(screen.getByDisplayValue('100,00')).toBeInTheDocument();
    expect(screen.getByRole('heading', { name: 'Editar pedido #42' }))
      .toBeInTheDocument();

    await user.click(customer);
    await user.click(screen.getByRole('option', {
      name: /Cliente atualizado/,
    }));
    await user.clear(screen.getByLabelText(/^Valor total/));
    await user.type(screen.getByLabelText(/^Valor total/), '150,50');
    await user.click(screen.getByRole('button', { name: 'Salvar pedido' }));

    await waitFor(() => {
      expect(apiRequestMock).toHaveBeenNthCalledWith(3, '/pedidos/42', {
        method: 'PUT',
        body: JSON.stringify({
          cliente_id: 2,
          descricao: 'Descricao existente',
          valor_total: '150.50',
          status: 'EM_PROCESSAMENTO',
        }),
      });
    });
    expect(await screen.findByText('Pedido atualizado com sucesso.'))
      .toBeInTheDocument();
    expect(screen.getByText('Cliente atualizado')).toBeInTheDocument();
  });
});
