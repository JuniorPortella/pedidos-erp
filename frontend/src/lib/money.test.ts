import { describe, expect, it } from 'vitest';
import {
  formatCurrency,
  moneyToCents,
  normalizeMoneyInput,
  sumMoney,
} from './money';

describe('money', () => {
  it('normaliza valores digitados com virgula ou ponto', () => {
    expect(normalizeMoneyInput(' 149,9 ')).toBe('149.90');
    expect(normalizeMoneyInput('25.50')).toBe('25.50');
  });

  it('rejeita zero, negativos e mais de duas casas', () => {
    expect(normalizeMoneyInput('0')).toBeNull();
    expect(normalizeMoneyInput('-1')).toBeNull();
    expect(normalizeMoneyInput('10,999')).toBeNull();
  });

  it('soma em centavos e formata em real', () => {
    expect(moneyToCents('10.25')).toBe(1025);
    expect(sumMoney(['10.25', '20.10', '0.05'])).toBe('30.40');
    expect(formatCurrency('149.90')).toContain('149,90');
  });
});
