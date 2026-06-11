import { Link, Outlet, useNavigate } from 'react-router-dom';
import { apiClient } from '../api/client';
import { useAuthStore } from '../store/auth';

export function Layout() {
  const navigate = useNavigate();
  const clear = useAuthStore((s) => s.clear);

  const handleLogout = async () => {
    try {
      await apiClient.post('/auth/logout');
    } finally {
      clear();
      navigate('/login');
    }
  };

  return (
    <>
      <header className="app-header">
        <Link to="/" className="brand">
          Spo<span>vie</span>
        </Link>
        <button className="btn btn-ghost btn-sm" onClick={handleLogout}>
          ログアウト
        </button>
      </header>
      <main className="container">
        <Outlet />
      </main>
    </>
  );
}
