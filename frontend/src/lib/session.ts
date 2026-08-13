const SESSION_INVALIDATED_EVENT = 'pedidos:session-invalidated';
const SESSION_STORAGE_KEY = 'pedidos:session-invalidated-at';

export function invalidateSession(): void {
  window.dispatchEvent(new Event(SESSION_INVALIDATED_EVENT));

  try {
    window.localStorage.setItem(
      SESSION_STORAGE_KEY,
      `${Date.now()}-${Math.random()}`,
    );
  } catch {
    // The current tab still receives the custom event without localStorage.
  }
}

export function subscribeToSessionInvalidation(
  listener: () => void,
): () => void {
  const handleStorage = (event: StorageEvent) => {
    if (event.key === SESSION_STORAGE_KEY) {
      listener();
    }
  };

  window.addEventListener(SESSION_INVALIDATED_EVENT, listener);
  window.addEventListener('storage', handleStorage);

  return () => {
    window.removeEventListener(SESSION_INVALIDATED_EVENT, listener);
    window.removeEventListener('storage', handleStorage);
  };
}

export const sessionStorageKey = SESSION_STORAGE_KEY;
