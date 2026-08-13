export function normalizeMoneyInput(value: string): string | null {
  const normalized = value.trim().replace(',', '.');

  if (!/^(?:0|[1-9][0-9]{0,9})(?:\.[0-9]{1,2})?$/.test(normalized)) {
    return null;
  }

  const [integer, decimal = ''] = normalized.split('.');
  const result = `${integer}.${decimal.padEnd(2, '0')}`;

  return result === '0.00' ? null : result;
}

export function moneyToCents(value: string): number {
  const [integer, decimal = ''] = value.split('.');

  return Number(integer) * 100 + Number(decimal.padEnd(2, '0').slice(0, 2));
}

export function sumMoney(values: string[]): string {
  const cents = values.reduce(
    (total, value) => total + moneyToCents(value),
    0,
  );

  return `${Math.floor(cents / 100)}.${String(cents % 100).padStart(2, '0')}`;
}

export function formatCurrency(value: string): string {
  return new Intl.NumberFormat('pt-BR', {
    style: 'currency',
    currency: 'BRL',
  }).format(moneyToCents(value) / 100);
}
