import { useEffect, useRef, useState } from 'react';
import { Link, useNavigate, useParams } from 'react-router-dom';
import { apiClient } from '../api/client';
import { clearPendingInvite, savePendingInvite } from '../lib/pendingInvite';
import { useAuthStore } from '../store/auth';
import type { Team } from '../types';

/**
 * 招待リンク /teams/join/:token
 * 未ログインならトークンを保存してログインへ。ログイン後にここへ戻ってきて参加する。
 */
export function TeamJoinPage() {
  const { token: inviteToken } = useParams<{ token: string }>();
  const authToken = useAuthStore((s) => s.token);
  const navigate = useNavigate();
  const [message, setMessage] = useState('チームに参加しています...');
  const [failed, setFailed] = useState(false);
  const requested = useRef(false);

  useEffect(() => {
    if (!inviteToken) return;

    if (!authToken) {
      savePendingInvite(inviteToken);
      navigate('/login', { replace: true });
      return;
    }

    // 開発時の StrictMode による二重実行で API を2回呼ばない
    if (requested.current) return;
    requested.current = true;
    clearPendingInvite();

    apiClient
      .post<{ data: Team }>('/teams/join', { invite_token: inviteToken })
      .then((res) => navigate(`/teams/${res.data.data.id}`, { replace: true }))
      .catch(() => {
        setFailed(true);
        setMessage('招待リンクが無効、または参加できませんでした。');
      });
  }, [authToken, inviteToken, navigate]);

  return (
    <main className="container">
      <p className={failed ? 'error-msg' : 'muted'}>{message}</p>
      {failed && <Link to="/" className="link">ホームに戻る</Link>}
    </main>
  );
}
