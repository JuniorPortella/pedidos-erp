import { describe, expect, it } from 'vitest';
import { matchesSearch, normalizeSearch } from './search';

describe('search helpers', () => {
  it('normaliza acentos, espacos e letras maiusculas', () => {
    expect(normalizeSearch('  João ÁVILA  ')).toBe('joao avila');
  });

  it('encontra todos os termos mesmo em campos diferentes', () => {
    expect(
      matchesSearch('jaqueline operador', [
        'Jaqueline Souza',
        'jaqueline@example.com',
        'OPERADOR',
      ]),
    ).toBe(true);
  });

  it('rejeita quando algum termo nao existe', () => {
    expect(matchesSearch('cliente concluido', ['Cliente Teste', 'PENDENTE'])).toBe(
      false,
    );
  });
});
