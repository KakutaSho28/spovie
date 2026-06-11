import { useEffect, useRef, useState } from 'react';
import { Link, useNavigate, useParams, useSearchParams } from 'react-router-dom';
import { apiClient } from '../api/client';
import {
  AnnotationCanvas,
  AnnotationCanvasHandle,
  DrawTool,
} from '../components/AnnotationCanvas';
import { ClipModal } from '../components/ClipModal';
import { useHtml5VideoLoop } from '../hooks/useHtml5VideoLoop';
import { useYouTubePlayer } from '../hooks/useYouTubePlayer';
import type { Annotation, Video } from '../types';

const COLORS = ['#ff3b30', '#ffd60a', '#ffffff'];

export function AnnotatePage() {
  const { videoId } = useParams<{ videoId: string }>();
  const [searchParams] = useSearchParams();
  const annotationId = searchParams.get('annotationId');
  const navigate = useNavigate();

  const [video, setVideo] = useState<Video | null>(null);
  const [startSeconds, setStartSeconds] = useState(0);
  const [endSeconds, setEndSeconds] = useState(10);
  const [looping, setLooping] = useState(false);
  const [tool, setTool] = useState<DrawTool>('select');
  const [color, setColor] = useState(COLORS[0]);
  const [comment, setComment] = useState('');
  const [error, setError] = useState('');
  const [saving, setSaving] = useState(false);
  const [drawing, setDrawing] = useState(false);
  const [clipModalOpen, setClipModalOpen] = useState(false);
  const [savedAnnotationId, setSavedAnnotationId] = useState<number | null>(null);

  const canvasRef = useRef<AnnotationCanvasHandle>(null);
  const wrapRef = useRef<HTMLDivElement>(null);
  const html5VideoRef = useRef<HTMLVideoElement>(null);
  const [wrapSize, setWrapSize] = useState({ width: 0, height: 0 });

  const isUpload = video?.type === 'upload';

  // ----- 動画情報の取得 -----
  useEffect(() => {
    apiClient.get('/videos').then((res) => {
      const found = (res.data.data as Video[]).find((v) => v.id === Number(videoId));
      setVideo(found ?? null);
    });
  }, [videoId]);

  // ----- 既存アノテーションの読み込み（「開く」から来た場合） -----
  useEffect(() => {
    if (!annotationId) return;
    apiClient.get(`/videos/${videoId}/annotations`).then((res) => {
      const found = (res.data.data as Annotation[]).find(
        (a) => a.id === Number(annotationId),
      );
      if (!found) return;
      setStartSeconds(found.start_seconds);
      setEndSeconds(found.end_seconds);
      setComment(found.comment ?? '');
      setSavedAnnotationId(found.id);
      window.setTimeout(() => canvasRef.current?.loadCanvasData(found.canvas_data), 600);
    });
  }, [annotationId, videoId]);

  // ----- ResizeObserver: プレーヤーとCanvasのサイズを同期 -----
  useEffect(() => {
    if (!wrapRef.current) return;
    const observer = new ResizeObserver((entries) => {
      const rect = entries[0].contentRect;
      setWrapSize({ width: rect.width, height: rect.height });
    });
    observer.observe(wrapRef.current);
    return () => observer.disconnect();
  }, [video]);

  // ----- YouTube再生（type=youtubeのみ） -----
  const { containerRef, playFrom, pause } = useYouTubePlayer({
    videoId: video?.type === 'youtube' ? (video.youtube_video_id ?? '') : '',
    loop: looping ? { start: startSeconds, end: endSeconds } : null,
  });

  // ----- HTML5再生（type=uploadのみ） -----
  useHtml5VideoLoop({
    videoRef: html5VideoRef,
    loop: looping ? { start: startSeconds, end: endSeconds } : null,
  });

  const handleLoopToggle = () => {
    if (looping) {
      if (isUpload) {
        html5VideoRef.current?.pause();
      } else {
        pause();
      }
      setLooping(false);
    } else {
      setLooping(true);
      if (isUpload && html5VideoRef.current) {
        html5VideoRef.current.currentTime = startSeconds;
        html5VideoRef.current.play();
      } else {
        playFrom(startSeconds);
      }
    }
  };

  const handleToolChange = (next: DrawTool) => {
    setTool(next);
    setDrawing(next !== 'select');
    if (next !== 'select') {
      if (isUpload) {
        html5VideoRef.current?.pause();
      } else {
        pause();
      }
    }
  };

  /** アノテーションを保存し、保存したIDを返す */
  const saveAnnotation = async (): Promise<number | null> => {
    setError('');
    if (endSeconds <= startSeconds) {
      setError('終了秒数は開始秒数より大きくしてください');
      return null;
    }
    const canvasData = canvasRef.current?.toCanvasData();
    const res = await apiClient.post(`/videos/${videoId}/annotations`, {
      start_seconds: startSeconds,
      end_seconds: endSeconds,
      canvas_data: canvasData,
      comment: comment || null,
    });
    return res.data.data.id as number;
  };

  const handleSave = async () => {
    setSaving(true);
    try {
      const id = await saveAnnotation();
      if (id === null) {
        setSaving(false);
        return;
      }
      navigate(`/videos/${videoId}/annotations`);
    } catch {
      setError('保存に失敗しました');
      setSaving(false);
    }
  };

  /** 切り抜き保存：アノテーション保存 → モーダルを開く */
  const handleClipSave = async () => {
    setSaving(true);
    try {
      const id = await saveAnnotation();
      if (id === null) {
        setSaving(false);
        return;
      }
      setSavedAnnotationId(id);
      setClipModalOpen(true);
      setSaving(false);
    } catch {
      setError('保存に失敗しました');
      setSaving(false);
    }
  };

  const handleClear = () => {
    if (window.confirm('描画をすべて消去しますか？')) {
      canvasRef.current?.clear();
    }
  };

  if (!video) return <p className="muted">読み込み中...</p>;

  return (
    <>
      <Link to={`/videos/${videoId}/annotations`} className="link muted">
        ← 一覧に戻る
      </Link>
      <h1 className="page-title" style={{ marginTop: 12 }}>{video.title}</h1>

      {/* プレーヤー + Canvas オーバーレイ */}
      <div className="player-wrap" ref={wrapRef}>
        {isUpload && video.file_url ? (
          <video
            ref={html5VideoRef}
            src={video.file_url}
            controls
            playsInline
            style={{ position: 'absolute', inset: 0, width: '100%', height: '100%' }}
          />
        ) : (
          <div ref={containerRef} />
        )}
        <div className={`canvas-layer ${drawing ? '' : 'pass-through'}`}>
          <AnnotationCanvas
            ref={canvasRef}
            width={wrapSize.width}
            height={wrapSize.height}
            tool={tool}
            color={color}
          />
        </div>
      </div>

      {/* ループ設定 */}
      <div className="toolbar">
        <span className="label">ループ</span>
        <input
          className="seconds-input"
          type="number"
          min={0}
          value={startSeconds}
          onChange={(e) => setStartSeconds(Number(e.target.value))}
          aria-label="開始秒数"
        />
        <span className="muted">〜</span>
        <input
          className="seconds-input"
          type="number"
          min={1}
          value={endSeconds}
          onChange={(e) => setEndSeconds(Number(e.target.value))}
          aria-label="終了秒数"
        />
        <button className="btn btn-primary btn-sm" onClick={handleLoopToggle}>
          {looping ? '停止' : 'ループ再生'}
        </button>
      </div>

      {/* 描画ツール */}
      <div className="toolbar">
        <span className="label">描画</span>
        <button
          className={`tool-btn ${tool === 'select' ? 'active' : ''}`}
          onClick={() => handleToolChange('select')}
        >
          動画操作
        </button>
        <button
          className={`tool-btn ${tool === 'pen' ? 'active' : ''}`}
          onClick={() => handleToolChange('pen')}
        >
          ペン
        </button>
        <button
          className={`tool-btn ${tool === 'arrow' ? 'active' : ''}`}
          onClick={() => handleToolChange('arrow')}
        >
          矢印
        </button>
        <button
          className={`tool-btn ${tool === 'text' ? 'active' : ''}`}
          onClick={() => handleToolChange('text')}
        >
          テキスト
        </button>
        <button className="tool-btn" onClick={() => canvasRef.current?.undo()}>
          元に戻す
        </button>
        <button className="tool-btn" onClick={() => canvasRef.current?.deleteSelected()}>
          選択を削除
        </button>
        <span className="label" style={{ marginLeft: 8 }}>色</span>
        {COLORS.map((c) => (
          <button
            key={c}
            className={`color-dot ${color === c ? 'active' : ''}`}
            style={{ background: c }}
            onClick={() => setColor(c)}
            aria-label={`色 ${c}`}
          />
        ))}
      </div>

      {/* コメント */}
      <div className="field">
        <label htmlFor="comment">コメント</label>
        <textarea
          id="comment"
          rows={3}
          maxLength={1000}
          value={comment}
          onChange={(e) => setComment(e.target.value)}
          placeholder="例: 3番がヘルプに来るのが0.5秒遅い"
        />
      </div>

      {error && <p className="error-msg">{error}</p>}

      <div style={{ display: 'flex', gap: 10, flexWrap: 'wrap' }}>
        <button className="btn btn-primary" onClick={handleSave} disabled={saving}>
          {saving ? '保存中...' : '保存する'}
        </button>
        {isUpload && (
          <button className="btn btn-primary" onClick={handleClipSave} disabled={saving}>
            切り抜き保存
          </button>
        )}
        <button className="btn btn-ghost" onClick={handleClear}>
          描画をクリア
        </button>
      </div>

      {clipModalOpen && (
        <ClipModal
          videoId={Number(videoId)}
          annotationId={savedAnnotationId}
          defaultTitle={video.title}
          defaultStart={startSeconds}
          defaultEnd={endSeconds}
          onClose={() => setClipModalOpen(false)}
        />
      )}
    </>
  );
}
