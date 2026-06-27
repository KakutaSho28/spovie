import { useEffect, useState } from 'react';
import { Link } from 'react-router-dom';
import { apiClient } from '../api/client';
import type { Team } from '../types';

export function TeamsPage() {
  const [teams, setTeams] = useState<Team[]>([]);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    apiClient.get('/teams').then((res) => {
      setTeams(res.data.data);
      setLoading(false);
    });
  }, []);

  return (
    <>
      <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: 20 }}>
        <h1 className="page-title" style={{ margin: 0 }}>チーム</h1>
        <Link to="/teams/new" className="btn btn-primary btn-sm">
          + チームを作成
        </Link>
      </div>

      {loading ? (
        <p className="muted">読み込み中...</p>
      ) : teams.length === 0 ? (
        <div className="empty">
          <p>参加中のチームがありません。</p>
          <Link to="/teams/new" className="btn btn-primary">チームを作成する</Link>
        </div>
      ) : (
        <div className="grid">
          {teams.map((team) => (
            <Link className="card team-card" to={`/teams/${team.id}`} key={team.id}>
              <div className="card-body">
                <p className="card-title">{team.name}</p>
                <p className="card-meta">
                  {team.members.length}人 ・ オーナー: {team.owner.name}
                </p>
              </div>
            </Link>
          ))}
        </div>
      )}
    </>
  );
}
