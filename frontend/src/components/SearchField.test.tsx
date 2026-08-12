import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { useState } from 'react';
import { describe, expect, it, vi } from 'vitest';
import { SearchField } from './SearchField';

function SearchHarness({ onChange }: { onChange?: (value: string) => void }) {
  const [value, setValue] = useState('');

  return (
    <SearchField
      label="Buscar registros"
      value={value}
      onChange={(nextValue) => {
        setValue(nextValue);
        onChange?.(nextValue);
      }}
    />
  );
}

describe('SearchField', () => {
  it('envia o texto digitado ao componente pai', async () => {
    const onChange = vi.fn();
    const user = userEvent.setup();

    render(<SearchHarness onChange={onChange} />);

    const input = screen.getByRole('searchbox', {
      name: 'Buscar registros',
    });

    await user.type(input, 'cliente');

    expect(input).toHaveValue('cliente');
    expect(onChange).toHaveBeenLastCalledWith('cliente');
  });

  it('mostra um unico controle para limpar a busca', async () => {
    const user = userEvent.setup();

    render(<SearchHarness />);

    const input = screen.getByRole('searchbox', {
      name: 'Buscar registros',
    });

    expect(input).toHaveAttribute('type', 'text');

    await user.type(input, 'pedido');

    const clearButtons = screen.getAllByRole('button', {
      name: 'Limpar busca',
    });

    expect(clearButtons).toHaveLength(1);

    await user.click(clearButtons[0]);

    expect(input).toHaveValue('');
    expect(
      screen.queryByRole('button', { name: 'Limpar busca' }),
    ).not.toBeInTheDocument();
  });
});
