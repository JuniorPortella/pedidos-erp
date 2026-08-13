import { describe, expect, it } from 'vitest';
import type { Client, Order } from '../types/api';
import { buildSalesReport } from './salesReport';

const client: Client = {
  id: 1,
  nome: 'Cliente Teste',
  telefone: '11999999999',
  created_at: '2026-08-12T12:00:00+00:00',
  updated_at: '2026-08-12T12:00:00+00:00',
};

const orders: Order[] = [
  {
    id: 10,
    cliente_id: 1,
    descricao: 'Pedido concluido',
    valor_total: '100.00',
    status: 'CONCLUIDO',
    criado_por: 1,
    created_at: '2026-08-12T12:00:00+00:00',
    updated_at: '2026-08-12T12:00:00+00:00',
  },
  {
    id: 11,
    cliente_id: 99,
    descricao: 'Pedido pendente',
    valor_total: '50.25',
    status: 'PENDENTE',
    criado_por: 1,
    created_at: '2026-08-12T12:00:00+00:00',
    updated_at: '2026-08-12T12:00:00+00:00',
  },
];

describe('buildSalesReport', () => {
  it('monta o resumo, resolve clientes e inclui os pedidos', () => {
    const document = buildSalesReport(
      orders,
      [client],
      new Date('2026-08-12T15:00:00Z'),
    );
    const serialized = JSON.stringify(document.content);

    expect(serialized).toContain('Relatorio de vendas');
    expect(serialized).toContain('Pedidos: 2');
    expect(serialized).toContain('150,25');
    expect(serialized).toContain('Cliente Teste');
    expect(serialized).toContain('Cliente #99');
    expect(serialized).toContain('Pedido concluido');
  });
});
