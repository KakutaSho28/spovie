import { useEffect, useRef, useState } from 'react';
import { Link, useParams } from 'react-router-dom';
import { apiClient } from '../api/client';
import type { Clip } from '../types';
import { formatTime } from '../utils/time';

const POLL_INTERVAL_MS = 3000;
const POLL_TIMEOUT_MS = 10 * 60 * 1000;

export function ClipStatusPage() {
  const { clipId } = useParams<{ clipId: string }>();
  const [clip, setClip] = useState<Clip | null>(null);
  const [timedOut, setTimedOut] = useState(false);
  const [copied, setCopied] = useState(false);
  const pollRef = useRef<number>();

  useEffect(() => {
    const fetchClip = async () => {
      const res = await apiClient.get(`/clips/${clipId}`);
      const data: Clip = res.data.data;
      setClip(data);
      if (data.status !== 'processing') {
        window.clearInterval(pollRef.current);
      }
    };

    fetchClip();
    pollRef.current = window.setInterval(fetchClip, POLL_INTERVAL_MS);
    const timeoutId = window.setTimeout(() => {
      window.clearInterval(pollRef.current);
      setTimedOut(true);
    }, POLL_TIMEOUT_MS);

    return () => {
      window.clearInterval(pollRef.current);
      window.clearTimeout(timeoutId);
    };
  }, [clipId]);

  const handleCopy = async () => {
    if (!clip?.download_url) return;
    await navigator.clipboard.writeText(clip.download_url);
    setCopied(true);
    window.setTimeout(() => setCopied(false), 2000);
  };

  const handleLineShare = () => {
    if (!clip?.download_url) return;
    const lineShareUrl = `https://line.me/R/msg/text/?${encodeURIComponent(clip.download_url)}`;
    window.open(lineShareUrl, '_blank');
  };

  if (!clip) return <p className="muted">読み込み中...</p>;

  return (
    <>
      <Link to="/" className="link muted">← 動画一覧に戻る</Link>
      <h1 className="page-title" style={{ marginTop: 12 }}>{clip.title}</h1>
      <p className="anno-range" style={{ marginBottom: 20 }}>
        {formatTime(clip.start_seconds)} — {formatTime(clip.end_seconds)}
      </p>

      {clip.status === 'processing' && !timedOut && (
        <div style={{ textAlign: 'center', padding: '40px 0' }}>
          <div className="spinner" />
          <p style={{ marginTop: 16 }}>FFmpegで処理中...</p>
          <p className="muted">しばらくお待ちください（自動で更新されます）</p>
        </div>
      )}

      {timedOut && clip.status === 'processing' && (
        <p className="error-msg">
          処理がタイムアウトしました。時間をおいてページを再読み込みしてください。
        </p>
      )}

      {clip.status === 'done' && clip.download_url && (
        <>
          <div className="field" style={{ maxWidth: 560 }}>
            <label>ダウンロードURL</label>
            <input value={clip.download_url} readOnly onFocus={(e) => e.target.select()} />
          </div>
          <div style={{ display: 'flex', gap: 10, flexWrap: 'wrap' }}>
            <button className="btn btn-primary" onClick={handleCopy}>
              {copied ? 'コピーしました ✓' : 'URLをコピー'}
            </button>
            <button className="btn btn-primary" onClick={handleLineShare}>
              LINEで共有
            </button>
            <a className="btn btn-ghost" href={clip.download_url}>
              ダウンロード
            </a>
          </div>
        </>
      )}

      {clip.status === 'error' && (
        <p className="error-msg">
          切り抜き処理に失敗しました。動画一覧からもう一度お試しください。
        </p>
      )}
    </>
  );
}
