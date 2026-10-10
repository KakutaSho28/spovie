import { useEffect } from 'react';
import { Link, Outlet, useNavigate } from 'react-router-dom';
import { apiClient } from '../api/client';
import { getEcho } from '../lib/echo';
import { useAuthStore } from '../store/auth';
import { InstallButton } from './InstallButton';
import { RealtimeBadge } from './RealtimeBadge';

export function Layout() {
  const navigate = useNavigate();
  const clear = useAuthStore((s) => s.clear);

  // ログイン中は常にリアルタイム接続を張る（接続状態をヘッダーに表示するため）
  useEffect(() => {
    getEcho();
  }, []);

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
        <nav className="app-nav">
          <RealtimeBadge />
          <InstallButton />
          <Link to="/" className="link">動画</Link>
          <Link to="/teams" className="link">チーム</Link>
          <button className="btn btn-ghost btn-sm" onClick={handleLogout}>
            ログアウト
          </button>
        </nav>
      </header>
      <main className="container">
        <Outlet />
      </main>
    </>
  );
}
