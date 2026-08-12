import {
  createContext,
  useCallback,
  useContext,
  useEffect,
  useMemo,
  useState,
  type PropsWithChildren,
} from 'react';
import { ApiError, apiRequest, jsonBody } from '../lib/api';
import type { AuthUser } from '../types/api';

interface UserResponse {
  user: AuthUser;
}

interface AuthContextValue {
  user: AuthUser | null;
  loading: boolean;
  sessionError: string | null;
  login: (username: string, password: string) => Promise<void>;
  logout: () => Promise<void>;
}

const AuthContext = createContext<AuthContextValue | null>(null);

export function AuthProvider({ children }: PropsWithChildren) {
  const [user, setUser] = useState<AuthUser | null>(null);
  const [loading, setLoading] = useState(true);
  const [sessionError, setSessionError] = useState<string | null>(null);

  const restoreSession = useCallback(async () => {
    setLoading(true);
    setSessionError(null);

    try {
      const response = await apiRequest<UserResponse>('/auth/me');
      setUser(response.user);
    } catch (error) {
      setUser(null);

      if (error instanceof ApiError && error.status === 0) {
        setSessionError(error.message);
      }
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => {
    void restoreSession();
  }, [restoreSession]);

  const login = useCallback(async (username: string, password: string) => {
    const response = await apiRequest<UserResponse>('/auth/login', {
      method: 'POST',
      body: jsonBody({ usuario: username, senha: password }),
      retryOnUnauthorized: false,
    });

    setUser(response.user);
    setSessionError(null);
  }, []);

  const logout = useCallback(async () => {
    try {
      await apiRequest<void>('/auth/logout', {
        method: 'POST',
        retryOnUnauthorized: false,
      });
    } finally {
      setUser(null);
    }
  }, []);

  const value = useMemo(
    () => ({ user, loading, sessionError, login, logout }),
    [user, loading, sessionError, login, logout],
  );

  return <AuthContext.Provider value={value}>{children}</AuthContext.Provider>;
}

export function useAuth(): AuthContextValue {
  const context = useContext(AuthContext);

  if (!context) {
    throw new Error('useAuth deve ser usado dentro de AuthProvider.');
  }

  return context;
}
