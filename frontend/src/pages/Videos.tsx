import { useEffect, useState } from 'react';
import { Link, useSearchParams } from 'react-router-dom';
import { apiClient } from '../api/client';
import { OfflineFallback } from '../components/OfflineFallback';
import { Pagination } from '../components/Pagination';
import { useOnlineStatus } from '../hooks/useOnlineStatus';
import type { PageMeta, Team, Video } from '../types';

const PER_PAGE = 20;

export function VideosPage() {
  const [searchParams, setSearchParams] = useSearchParams();
  const [videos, setVideos] = useState<Video[]>([]);
  const [teams, setTeams] = useState<Team[]>([]);
  const [loading, setLoading] = useState(true);
  const [failed, setFailed] = useState(false);
  const [meta, setMeta] = useState<PageMeta | null>(null);
  const online = useOnlineStatus();
  const teamFilter = searchParams.get('team') ?? 'all';
  const page = Math.max(1, Number(searchParams.get('page')) || 1);

  const goToPage = (next: number) => {
    const params = new URLSearchParams(searchParams);
    if (next <= 1) params.delete('page');
    else params.set('page', String(next));
    setSearchParams(params);
  };

  // フィルタとページ送りはサーバー側で行う（20 件ずつ。クライアント側で絞ると取りこぼす）
  const fetchVideos = async () => {
    const filter =
      teamFilter === 'all'
        ? {}
        : teamFilter === 'personal'
          ? { scope: 'personal' }
          : { team_id: Number(teamFilter) };
    try {
      const res = await apiClient.get<{ data: Video[]; meta: PageMeta }>('/videos', {
        params: { ...filter, page, per_page: PER_PAGE },
      });
      // 最後のページの動画を削除した等で、ページが範囲外になったら最終ページへ戻す
      if (res.data.data.length === 0 && page > 1) {
        goToPage(res.data.meta.last_page);
        return;
      }
      setVideos(res.data.data);
      setMeta(res.data.meta);
      setFailed(false);
    } catch {
      setVideos([]);
      setFailed(true);
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    apiClient.get('/teams').then((res) => setTeams(res.data.data));
  }, []);

  useEffect(() => {
    setLoading(true);
    fetchVideos();
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [teamFilter, page]);

  const handleDelete = async (video: Video) => {
    const ok = window.confirm(
      `「${video.title}」を削除しますか？\nこの操作は取り消せません。アノテーションも削除されます。`,
    );
    if (!ok) return;
    await apiClient.delete(`/videos/${video.id}`);
    fetchVideos();
  };

  return (
    <>
      <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: 20 }}>
        <h1 className="page-title" style={{ margin: 0 }}>動画一覧</h1>
        <Link to="/videos/new" className="btn btn-primary btn-sm">
          + 動画を追加
        </Link>
      </div>

      <div className="toolbar">
        <span className="label">表示</span>
        <select
          className="select-input"
          value={teamFilter}
          onChange={(e) => {
            const next = e.target.value;
            // フィルタを変えたら 1 ページ目に戻す
            setSearchParams(next === 'all' ? {} : { team: next });
          }}
        >
          <option value="all">すべて</option>
          <option value="personal">個人</option>
          {teams.map((team) => (
            <option key={team.id} value={team.id}>{team.name}</option>
          ))}
        </select>
      </div>

      {loading ? (
        <p className="muted">読み込み中...</p>
      ) : failed ? (
        online ? (
          <p className="error-msg">動画一覧を取得できませんでした。</p>
        ) : (
          <OfflineFallback what="動画一覧" onRetry={fetchVideos} />
        )
      ) : videos.length === 0 ? (
        <div className="empty">
          <p>動画がまだありません。</p>
          <Link to="/videos/new" className="btn btn-primary">最初の動画を追加する</Link>
        </div>
      ) : (
        <div className="grid">
          {videos.map((video) => (
            <div className="card" key={video.id}>
              {video.type === 'youtube' && video.youtube_video_id ? (
                <img
                  src={`https://img.youtube.com/vi/${video.youtube_video_id}/mqdefault.jpg`}
                  alt={video.title}
                />
              ) : video.file_url ? (
                <video src={video.file_url} preload="metadata" muted />
              ) : (
                <div className="thumb-fallback">🎬</div>
              )}
              <div className="card-body">
                <p className="card-title">{video.title}</p>
                <p className="card-meta">
                  {video.type === 'upload' ? '📁 アップロード' : '▶ YouTube'}
                  {video.team ? ` ・ ${video.team.name}` : ' ・ 個人'}
                  {' ・ '}
                  {new Date(video.created_at).toLocaleDateString('ja-JP')}
                </p>
                <div className="card-actions">
                  <Link to={`/videos/${video.id}/annotations`} className="btn btn-ghost btn-sm">
                    アノテーション一覧
                  </Link>
                  <button className="btn btn-danger btn-sm" onClick={() => handleDelete(video)}>
                    削除
                  </button>
                </div>
              </div>
            </div>
          ))}
        </div>
      )}

      {meta && !failed && (
        <Pagination
          currentPage={meta.current_page}
          lastPage={meta.last_page}
          total={meta.total}
          onChange={goToPage}
        />
      )}
    </>
  );
}
