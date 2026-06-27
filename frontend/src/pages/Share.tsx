import { useEffect, useRef, useState } from 'react';
import { Link, useParams } from 'react-router-dom';
import { apiClient } from '../api/client';
import {
  AnnotationCanvas,
  AnnotationCanvasHandle,
} from '../components/AnnotationCanvas';
import { useYouTubePlayer } from '../hooks/useYouTubePlayer';
import type { ShareView } from '../types';
import { formatTime } from '../utils/time';

export function SharePage() {
  const { token } = useParams<{ token: string }>();
  const [view, setView] = useState<ShareView | null>(null);
  const [errorMessage, setErrorMessage] = useState('');

  const canvasRef = useRef<AnnotationCanvasHandle>(null);
  const wrapRef = useRef<HTMLDivElement>(null);
  const [wrapSize, setWrapSize] = useState({ width: 0, height: 0 });

  useEffect(() => {
    apiClient
      .get(`/share/${token}`)
      .then((res) => setView(res.data.data))
      .catch((err) => {
        if (err.response?.status === 410) {
          setErrorMessage('この共有リンクは有効期限が切れています');
        } else {
          setErrorMessage('共有リンクが見つかりません');
        }
      });
  }, [token]);

  useEffect(() => {
    if (!wrapRef.current) return;
    const observer = new ResizeObserver((entries) => {
      const rect = entries[0].contentRect;
      setWrapSize({ width: rect.width, height: rect.height });
    });
    observer.observe(wrapRef.current);
    return () => observer.disconnect();
  }, [view]);

  const { containerRef, isReady, playFrom } = useYouTubePlayer({
    videoId: view?.video.youtube_video_id ?? '',
    loop: view
      ? { start: view.annotation.start_seconds, end: view.annotation.end_seconds }
      : null,
  });

  // プレーヤー準備完了 → ループ開始 + アノテーション再現
  useEffect(() => {
    if (!isReady || !view || wrapSize.width === 0) return;
    playFrom(view.annotation.start_seconds);
    canvasRef.current?.loadCanvasData(view.annotation.canvas_data);
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [isReady, view, wrapSize.width]);

  if (errorMessage) {
    return (
      <main className="container">
        <div className="empty">
          <p>{errorMessage}</p>
          <Link to="/register" className="btn btn-primary">Spovieを使ってみる</Link>
        </div>
      </main>
    );
  }

  if (!view) return <main className="container"><p className="muted">読み込み中...</p></main>;

  return (
    <>
      <header className="app-header">
        <span className="brand">
          Spo<span style={{ color: 'var(--accent)' }}>vie</span>
        </span>
        <Link to="/login" className="btn btn-ghost btn-sm">ログイン</Link>
      </header>
      <main className="container">
        <h1 className="page-title">{view.video.title}</h1>
        <p className="anno-range" style={{ marginBottom: 14 }}>
          {formatTime(view.annotation.start_seconds)} — {formatTime(view.annotation.end_seconds)} ループ再生中
        </p>

        <div className="player-wrap" ref={wrapRef}>
          <div ref={containerRef} />
          <div className="canvas-layer pass-through">
            <AnnotationCanvas
              ref={canvasRef}
              width={wrapSize.width}
              height={wrapSize.height}
              tool="select"
              color="#ff3b30"
              readOnly
            />
          </div>
        </div>

        {view.annotation.comment && (
          <div className="anno-item" style={{ marginTop: 16 }}>
            <span style={{ fontSize: 14 }}>💬 {view.annotation.comment}</span>
          </div>
        )}

        <p style={{ marginTop: 32, textAlign: 'center' }}>
          <Link to="/register" className="link">Spovieを使ってみる →</Link>
        </p>
      </main>
    </>
  );
}
