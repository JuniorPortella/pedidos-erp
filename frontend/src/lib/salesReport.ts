import type { TDocumentDefinitions } from 'pdfmake/interfaces';
import type { Client, Order, OrderStatus } from '../types/api';
import { formatCurrency, sumMoney } from './money';

const statusLabels: Record<OrderStatus, string> = {
  PENDENTE: 'Pendente',
  EM_PROCESSAMENTO: 'Em processamento',
  CONCLUIDO: 'Concluido',
};

export function buildSalesReport(
  orders: Order[],
  clients: Client[],
  generatedAt = new Date(),
): TDocumentDefinitions {
  const clientsById = new Map(
    clients.map((client) => [client.id, client]),
  );
  const total = sumMoney(orders.map((order) => order.valor_total));
  const completedTotal = sumMoney(
    orders
      .filter((order) => order.status === 'CONCLUIDO')
      .map((order) => order.valor_total),
  );

  return {
    pageSize: 'A4',
    pageOrientation: 'landscape',
    pageMargins: [32, 36, 32, 36],
    info: {
      title: 'Relatorio de vendas - Pedidos ERP',
      subject: 'Relatorio simples de pedidos e valores',
      creator: 'Pedidos ERP',
    },
    content: [
      { text: 'Pedidos ERP', style: 'brand' },
      { text: 'Relatorio de vendas', style: 'title' },
      {
        text: `Gerado em ${generatedAt.toLocaleString('pt-BR')}`,
        style: 'metadata',
      },
      {
        columns: [
          { text: `Pedidos: ${orders.length}`, style: 'summary' },
          { text: `Total: ${formatCurrency(total)}`, style: 'summary' },
          {
            text: `Concluido: ${formatCurrency(completedTotal)}`,
            style: 'summary',
          },
        ],
        columnGap: 12,
        margin: [0, 16, 0, 18],
      },
      {
        table: {
          headerRows: 1,
          widths: [42, 130, '*', 95, 105, 90],
          body: [
            [
              { text: 'Codigo', style: 'tableHeader' },
              { text: 'Cliente', style: 'tableHeader' },
              { text: 'Descricao', style: 'tableHeader' },
              { text: 'Status', style: 'tableHeader' },
              { text: 'Criado em', style: 'tableHeader' },
              { text: 'Valor', style: 'tableHeader', alignment: 'right' },
            ],
            ...orders.map((order) => [
              String(order.id),
              clientsById.get(order.cliente_id)?.nome
                ?? `Cliente #${order.cliente_id}`,
              order.descricao,
              statusLabels[order.status],
              new Date(order.created_at).toLocaleDateString('pt-BR'),
              {
                text: formatCurrency(order.valor_total),
                alignment: 'right' as const,
              },
            ]),
          ],
        },
        layout: 'lightHorizontalLines',
      },
    ],
    footer: (currentPage, pageCount) => ({
      text: `Pagina ${currentPage} de ${pageCount}`,
      alignment: 'center',
      fontSize: 8,
      color: '#64748b',
    }),
    styles: {
      brand: {
        color: '#0b63e5',
        bold: true,
        fontSize: 10,
      },
      title: {
        color: '#0b1838',
        bold: true,
        fontSize: 20,
        margin: [0, 4, 0, 0],
      },
      metadata: {
        color: '#64748b',
        fontSize: 9,
        margin: [0, 4, 0, 0],
      },
      summary: {
        color: '#0b1838',
        bold: true,
        fontSize: 11,
      },
      tableHeader: {
        color: '#ffffff',
        fillColor: '#0b1838',
        bold: true,
        fontSize: 9,
      },
    },
    defaultStyle: {
      font: 'Roboto',
      fontSize: 8,
      color: '#1f2937',
    },
  };
}

export async function downloadSalesReport(
  orders: Order[],
  clients: Client[],
): Promise<void> {
  const [{ default: pdfMake }, { default: pdfFonts }] = await Promise.all([
    import('pdfmake/build/pdfmake'),
    import('pdfmake/build/vfs_fonts'),
  ]);

  pdfMake.vfs = pdfFonts;
  pdfMake.createPdf(buildSalesReport(orders, clients)).download(
    `relatorio-vendas-${new Date().toISOString().slice(0, 10)}.pdf`,
  );
}
