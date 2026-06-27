import { useEffect, useState } from 'react';
import { useNavigate, useParams } from 'react-router-dom';
import { apiClient } from '../api/client';
import type { Team } from '../types';

export function TeamJoinPage() {
  const { token } = useParams<{ token: string }>();
  const navigate = useNavigate();
  const [message, setMessage] = useState('チームに参加しています...');

  useEffect(() => {
    const join = async () => {
      try {
        const lookup = await apiClient.get(`/teams/invite/${token}`);
        const found: Team = lookup.data.data;
        const joined = await apiClient.post(`/teams/${found.id}/join`, {
          invite_token: token,
        });
        navigate(`/teams/${joined.data.data.id}`, { replace: true });
      } catch {
        setMessage('招待リンクが無効、または参加できませんでした。');
      }
    };

    join();
  }, [navigate, token]);

  return <p className="muted">{message}</p>;
}
