import { FormEvent, useState } from 'react';
import { Link } from 'react-router-dom';
import { apiClient } from '../api/client';
import type { Team } from '../types';

export function TeamNewPage() {
  const [name, setName] = useState('');
  const [team, setTeam] = useState<Team | null>(null);
  const [copied, setCopied] = useState(false);
  const [error, setError] = useState('');

  const handleSubmit = async (e: FormEvent) => {
    e.preventDefault();
    setError('');
    try {
      const res = await apiClient.post('/teams', { name });
      setTeam(res.data.data);
    } catch {
      setError('チームの作成に失敗しました');
    }
  };

  const copyInviteUrl = async () => {
    if (!team) return;
    await navigator.clipboard.writeText(team.invite_url);
    setCopied(true);
    window.setTimeout(() => setCopied(false), 2000);
  };

  return (
    <>
      <Link to="/teams" className="link muted">← チーム一覧に戻る</Link>
      <h1 className="page-title" style={{ marginTop: 12 }}>チームを作成</h1>

      {!team ? (
        <form onSubmit={handleSubmit} style={{ maxWidth: 480 }}>
          <div className="field">
            <label htmlFor="team-name">チーム名</label>
            <input
              id="team-name"
              value={name}
              onChange={(e) => setName(e.target.value)}
              maxLength={100}
              required
              placeholder="例: 渋谷バスケ"
            />
          </div>
          {error && <p className="error-msg">{error}</p>}
          <button className="btn btn-primary">作成する</button>
        </form>
      ) : (
        <div className="field" style={{ maxWidth: 680 }}>
          <label>招待URL</label>
          <div style={{ display: 'flex', gap: 8 }}>
            <input value={team.invite_url} readOnly onFocus={(e) => e.target.select()} />
            <button className="btn btn-primary" onClick={copyInviteUrl}>
              {copied ? 'コピー済み' : 'コピー'}
            </button>
          </div>
          <p style={{ marginTop: 16 }}>
            <Link className="btn btn-ghost" to={`/teams/${team.id}`}>チーム詳細へ</Link>
          </p>
        </div>
      )}
    </>
  );
}
