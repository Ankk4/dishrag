import { type ReactNode, useEffect } from 'react';
import { Navigate } from 'react-router-dom';
import { useAuth } from '../context/AuthContext';

export function AuthGate({ children }: { children: ReactNode }): React.JSX.Element {
  const { user, ready, refreshMe } = useAuth();

  useEffect(() => {
    void refreshMe();
  }, [refreshMe]);

  if (!ready) {
    return <div className="centered muted">Loading…</div>;
  }
  if (!user) {
    return <Navigate to="/login" replace />;
  }
  return <>{children}</>;
}
