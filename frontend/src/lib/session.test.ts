import { describe, expect, it, vi } from 'vitest';
import {
  invalidateSession,
  sessionStorageKey,
  subscribeToSessionInvalidation,
} from './session';

describe('session invalidation', () => {
  it('notifica a aba atual', () => {
    const listener = vi.fn();
    const unsubscribe = subscribeToSessionInvalidation(listener);

    invalidateSession();

    expect(listener).toHaveBeenCalledTimes(1);
    unsubscribe();
  });

  it('notifica outra aba pelo evento de storage', () => {
    const listener = vi.fn();
    const unsubscribe = subscribeToSessionInvalidation(listener);

    window.dispatchEvent(new StorageEvent('storage', {
      key: sessionStorageKey,
      newValue: 'nova-invalidacao',
    }));

    expect(listener).toHaveBeenCalledTimes(1);
    unsubscribe();
  });

  it('remove os listeners ao cancelar a inscricao', () => {
    const listener = vi.fn();
    const unsubscribe = subscribeToSessionInvalidation(listener);

    unsubscribe();
    invalidateSession();

    expect(listener).not.toHaveBeenCalled();
  });
});
