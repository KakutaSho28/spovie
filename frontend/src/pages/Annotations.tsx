import { useEffect, useState } from 'react';
import { Link, useNavigate, useParams } from 'react-router-dom';
import { apiClient } from '../api/client';
import type { Annotation } from '../types';

export function AnnotationsPage() {
  const { videoId } = useParams<{ videoId: string }>();
  const navigate = useNavigate();
  const [annotations, setAnnotations] = useState<Annotation[]>([]);
  const [loading, setLoading] = useState(true);
  const [toast, setToast] = useState('');

  const fetchAnnotations = async () => {
    const res = await apiClient.get(`/videos/${videoId}/annotations`);
    setAnnotations(res.data.data);
    setLoading(false);
  };

  useEffect(() => {
    fetchAnnotations();
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [videoId]);

  const showToast = (message: string) => {
    setToast(message);
    window.setTimeout(() => setToast(''), 2000);
  };

  const handleShare = async (annotation: Annotation) => {
    const res = await apiClient.post(`/annotations/${annotation.id}/share`, {
      expires_at: null,
    });
    const shareUrl: string = res.data.data.share_url;
    await navigator.clipboard.writeText(shareUrl);
    showToast('共有リンクをコピーしました ✓');
  };

  const handleDelete = async (annotation: Annotation) => {
    const ok = window.confirm('このアノテーションを削除しますか？');
    if (!ok) return;
    await apiClient.delete(`/annotations/${annotation.id}`);
    fetchAnnotations();
  };

  return (
    <>
      <Link to="/" className="link muted">← 動画一覧に戻る</Link>
      <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', margin: '12px 0 20px' }}>
        <h1 className="page-title" style={{ margin: 0 }}>アノテーション一覧</h1>
        <button
          className="btn btn-primary btn-sm"
          onClick={() => navigate(`/videos/${videoId}/annotate`)}
        >
          + 新しく作成する
        </button>
      </div>

      {loading ? (
        <p className="muted">読み込み中...</p>
      ) : annotations.length === 0 ? (
        <div className="empty">
          <p>まだアノテーションがありません。作成してみましょう！</p>
        </div>
      ) : (
        annotations.map((a) => (
          <div className="anno-item" key={a.id}>
            <span className="anno-range">
              {a.start_seconds}s — {a.end_seconds}s
            </span>
            <span className="anno-comment">{a.comment ?? '（コメントなし）'}</span>
            <div style={{ display: 'flex', gap: 8 }}>
              <button
                className="btn btn-ghost btn-sm"
                onClick={() => navigate(`/videos/${videoId}/annotate?annotationId=${a.id}`)}
              >
                開く
              </button>
              <button className="btn btn-ghost btn-sm" onClick={() => handleShare(a)}>
                共有
              </button>
              <button className="btn btn-danger btn-sm" onClick={() => handleDelete(a)}>
                削除
              </button>
            </div>
          </div>
        ))
      )}

      {toast && <div className="toast">{toast}</div>}
    </>
  );
}
