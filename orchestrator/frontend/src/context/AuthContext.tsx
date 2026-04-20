import {
  createContext,
  useCallback,
  useContext,
  useMemo,
  useState,
  type ReactNode,
} from 'react';
import type { User } from '../types';
import { apiFetch, parseJsonOrThrow, setAccessToken } from '../api/client';

interface AuthState {
  user: User | null;
  ready: boolean;
}

interface AuthContextValue extends AuthState {
  login: (email: string, password: string) => Promise<void>;
  register: (email: string, password: string, name: string) => Promise<void>;
  logout: () => Promise<void>;
  refreshMe: () => Promise<void>;
}

const AuthContext = createContext<AuthContextValue | null>(null);

export function AuthProvider({ children }: { children: ReactNode }): React.JSX.Element {
  const [user, setUser] = useState<User | null>(null);
  const [ready, setReady] = useState(false);

  const refreshMe = useCallback(async () => {
    const res = await apiFetch('/api/v1/auth/me');
    if (!res.ok) {
      setUser(null);
      setAccessToken(null);
      setReady(true);
      return;
    }
    const body = await parseJsonOrThrow<{ id: string; email: string; name: string; created_at: string }>(
      res,
    );
    setUser({
      id: body.id,
      email: body.email,
      name: body.name,
      created_at: body.created_at,
    });
    setReady(true);
  }, []);

  const login = useCallback(async (email: string, password: string) => {
    const res = await fetch('/api/v1/auth/login', {
      method: 'POST',
      credentials: 'include',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ email, password }),
    });
    const body = await parseJsonOrThrow<{
      access_token: string;
      user: User;
    }>(res);
    setAccessToken(body.access_token);
    setUser(body.user);
    setReady(true);
  }, []);

  const register = useCallback(async (email: string, password: string, name: string) => {
    const res = await fetch('/api/v1/auth/register', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ email, password, name }),
    });
    await parseJsonOrThrow(res);
    await login(email, password);
  }, [login]);

  const logout = useCallback(async () => {
    await apiFetch('/api/v1/auth/logout', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ all_devices: false }),
    });
    setAccessToken(null);
    setUser(null);
  }, []);

  const value = useMemo(
    () => ({
      user,
      ready,
      login,
      register,
      logout,
      refreshMe,
    }),
    [user, ready, login, register, logout, refreshMe],
  );

  return <AuthContext.Provider value={value}>{children}</AuthContext.Provider>;
}

export function useAuth(): AuthContextValue {
  const ctx = useContext(AuthContext);
  if (!ctx) {
    throw new Error('useAuth must be used within AuthProvider');
  }
  return ctx;
}
