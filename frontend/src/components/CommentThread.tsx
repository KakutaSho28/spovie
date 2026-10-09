import { FormEvent, useEffect, useState } from 'react';
import { apiClient } from '../api/client';
import { useChannelEvent, useOnReconnect } from '../hooks/useRealtime';
import { removeComment, upsertComment, withOwnership } from '../lib/commentList';
import { useAuthStore } from '../store/auth';
import type { Comment } from '../types';

type Props = {
  annotationId: number;
};

export function CommentThread({ annotationId }: Props) {
  const myUserId = useAuthStore((s) => s.user?.id);
  const [comments, setComments] = useState<Comment[]>([]);
  const [body, setBody] = useState('');
  const [loading, setLoading] = useState(true);
  const [posting, setPosting] = useState(false);
  const [error, setError] = useState('');

  const fetchComments = async () => {
    const res = await apiClient.get(`/annotations/${annotationId}/comments`);
    setComments(res.data.data);
    setLoading(false);
  };

  useEffect(() => {
    fetchComments().catch(() => {
      setError('コメントの取得に失敗しました');
      setLoading(false);
    });
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [annotationId]);

  // ----- リアルタイム: 他の人のコメントの追加・削除を即時反映（id で重複排除） -----
  useChannelEvent<{ comment: Comment }>(`annotation.${annotationId}`, 'comment.created', ({ comment }) =>
    setComments((list) => upsertComment(list, withOwnership(comment, myUserId))),
  );
  useChannelEvent<{ id: number }>(`annotation.${annotationId}`, 'comment.deleted', ({ id }) =>
    setComments((list) => removeComment(list, id)),
  );
  // 切断中に取りこぼした分を補う
  useOnReconnect(() => {
    fetchComments().catch(() => undefined);
  });

  const handleSubmit = async (e: FormEvent) => {
    e.preventDefault();
    const trimmed = body.trim();
    if (!trimmed) return;

    setPosting(true);
    setError('');
    try {
      const res = await apiClient.post<{ data: Comment }>(`/annotations/${annotationId}/comments`, {
        body: trimmed,
      });
      setBody('');
      // 先に自分の画面へ反映（後から届く WebSocket 通知は id で重複排除される）
      setComments((list) => upsertComment(list, withOwnership(res.data.data, myUserId)));
    } catch {
      setError('コメントの投稿に失敗しました');
    } finally {
      setPosting(false);
    }
  };

  const handleDelete = async (comment: Comment) => {
    await apiClient.delete(`/comments/${comment.id}`);
    setComments((list) => removeComment(list, comment.id));
  };

  return (
    <div className="comment-thread">
      {loading ? (
        <p className="muted">コメントを読み込み中...</p>
      ) : comments.length === 0 ? (
        <p className="muted">コメントなし</p>
      ) : (
        <div className="comment-list">
          {comments.map((comment) => (
            <div className="comment-item" key={comment.id}>
              <div>
                <div className="comment-meta">
                  <strong>{comment.user.name}</strong>
                  <span>{new Date(comment.created_at).toLocaleString('ja-JP')}</span>
                </div>
                <p>{comment.body}</p>
              </div>
              {comment.is_own && (
                <button className="btn btn-ghost btn-sm" onClick={() => handleDelete(comment)}>
                  削除
                </button>
              )}
            </div>
          ))}
        </div>
      )}

      <form className="comment-form" onSubmit={handleSubmit}>
        <input
          value={body}
          onChange={(e) => setBody(e.target.value)}
          maxLength={1000}
          placeholder="コメントを追加"
        />
        <button className="btn btn-primary btn-sm" disabled={posting || !body.trim()}>
          投稿
        </button>
      </form>
      {error && <p className="error-msg">{error}</p>}
    </div>
  );
}
