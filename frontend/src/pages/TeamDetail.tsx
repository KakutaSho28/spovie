import { useEffect, useState } from 'react';
import { Link, useNavigate, useParams } from 'react-router-dom';
import { apiClient } from '../api/client';
import { useAuthStore } from '../store/auth';
import type { Team } from '../types';

export function TeamDetailPage() {
  const { teamId } = useParams<{ teamId: string }>();
  const navigate = useNavigate();
  const user = useAuthStore((s) => s.user);
  const [team, setTeam] = useState<Team | null>(null);
  const [copied, setCopied] = useState(false);
  const isOwner = team?.owner.id === user?.id;

  const fetchTeam = async () => {
    const res = await apiClient.get(`/teams/${teamId}`);
    setTeam(res.data.data);
  };

  useEffect(() => {
    fetchTeam();
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [teamId]);

  const copyInviteUrl = async () => {
    if (!team) return;
    await navigator.clipboard.writeText(team.invite_url);
    setCopied(true);
    window.setTimeout(() => setCopied(false), 2000);
  };

  const handleRemove = async (memberId: number) => {
    await apiClient.delete(`/teams/${teamId}/members/${memberId}`);
    fetchTeam();
  };

  const handleLeave = async () => {
    await apiClient.delete(`/teams/${teamId}/members/me`);
    navigate('/teams');
  };

  const handleDelete = async () => {
    if (!window.confirm('このチームを削除しますか？')) return;
    await apiClient.delete(`/teams/${teamId}`);
    navigate('/teams');
  };

  if (!team) return <p className="muted">読み込み中...</p>;

  return (
    <>
      <Link to="/teams" className="link muted">← チーム一覧に戻る</Link>
      <h1 className="page-title" style={{ marginTop: 12 }}>{team.name}</h1>

      <div className="field" style={{ maxWidth: 680 }}>
        <label>招待URL</label>
        <div style={{ display: 'flex', gap: 8 }}>
          <input value={team.invite_url} readOnly onFocus={(e) => e.target.select()} />
          <button className="btn btn-primary" onClick={copyInviteUrl}>
            {copied ? 'コピー済み' : 'コピー'}
          </button>
        </div>
      </div>

      <h2 className="section-title">メンバー</h2>
      <div className="member-list">
        {team.members.map((member) => (
          <div className="member-row" key={member.id}>
            <div>
              <strong>{member.name}</strong>
              <span className="muted"> ・ {member.role === 'owner' ? 'オーナー' : 'メンバー'}</span>
            </div>
            {isOwner && member.id !== user?.id && (
              <button className="btn btn-danger btn-sm" onClick={() => handleRemove(member.id)}>
                外す
              </button>
            )}
          </div>
        ))}
      </div>

      <div style={{ display: 'flex', gap: 10, marginTop: 20 }}>
        <Link to={`/?team=${team.id}`} className="btn btn-ghost">チーム動画を見る</Link>
        {isOwner ? (
          <button className="btn btn-danger" onClick={handleDelete}>チームを削除</button>
        ) : (
          <button className="btn btn-danger" onClick={handleLeave}>チームを脱退</button>
        )}
      </div>
    </>
  );
}
