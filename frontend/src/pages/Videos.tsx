import { useEffect, useState } from 'react';
import { Link } from 'react-router-dom';
import { apiClient } from '../api/client';
import type { Video } from '../types';

export function VideosPage() {
  const [videos, setVideos] = useState<Video[]>([]);
  const [loading, setLoading] = useState(true);

  const fetchVideos = async () => {
    const res = await apiClient.get('/videos');
    setVideos(res.data.data);
    setLoading(false);
  };

  useEffect(() => {
    fetchVideos();
  }, []);

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

      {loading ? (
        <p className="muted">読み込み中...</p>
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
    </>
  );
}
