import { useEffect, useRef, useState } from 'react';
import { apiClient } from '../api/client';
import type { Clip } from '../types';
import { TimeInput } from './TimeInput';

const POLL_INTERVAL_MS = 3000;
const POLL_TIMEOUT_MS = 10 * 60 * 1000; // 10分でタイムアウト

type Props = {
  videoId: number;
  annotationId: number | null;
  defaultTitle: string;
  defaultStart: number;
  defaultEnd: number;
  onClose: () => void;
};

type Phase = 'input' | 'processing' | 'done' | 'error';

/** 切り抜き作成 → FFmpeg処理ポーリング → ダウンロードURL表示までを行うモーダル */
export function ClipModal({
  videoId,
  annotationId,
  defaultTitle,
  defaultStart,
  defaultEnd,
  onClose,
}: Props) {
  const [phase, setPhase] = useState<Phase>('input');
  const [title, setTitle] = useState(defaultTitle);
  const [startSeconds, setStartSeconds] = useState(defaultStart);
  const [endSeconds, setEndSeconds] = useState(defaultEnd);
  const [downloadUrl, setDownloadUrl] = useState('');
  const [error, setError] = useState('');
  const [copied, setCopied] = useState(false);
  const [startTimeValid, setStartTimeValid] = useState(true);
  const [endTimeValid, setEndTimeValid] = useState(true);
  const pollRef = useRef<number>();
  const timeoutRef = useRef<number>();

  // アンマウント時にポーリング停止
  useEffect(() => {
    return () => {
      if (pollRef.current) window.clearInterval(pollRef.current);
      if (timeoutRef.current) window.clearTimeout(timeoutRef.current);
    };
  }, []);

  const startPolling = (clipId: number) => {
    pollRef.current = window.setInterval(async () => {
      const res = await apiClient.get(`/clips/${clipId}`);
      const clip: Clip = res.data.data;
      if (clip.status === 'done' && clip.download_url) {
        window.clearInterval(pollRef.current);
        setDownloadUrl(clip.download_url);
        setPhase('done');
      } else if (clip.status === 'error') {
        window.clearInterval(pollRef.current);
        setError('切り抜き処理に失敗しました。もう一度お試しください。');
        setPhase('error');
      }
    }, POLL_INTERVAL_MS);

    timeoutRef.current = window.setTimeout(() => {
      window.clearInterval(pollRef.current);
      setError('処理がタイムアウトしました。時間をおいて確認してください。');
      setPhase('error');
    }, POLL_TIMEOUT_MS);
  };

  const handleSubmit = async () => {
    setError('');
    if (!startTimeValid || !endTimeValid) {
      setError('時刻は 0:00 の形式で入力してください');
      return;
    }
    if (endSeconds <= startSeconds) {
      setError('終了時刻は開始時刻より後にしてください');
      return;
    }
    setPhase('processing');
    try {
      const res = await apiClient.post('/clips', {
        video_id: videoId,
        annotation_id: annotationId,
        title,
        start_seconds: startSeconds,
        end_seconds: endSeconds,
      });
      startPolling(res.data.data.id);
    } catch {
      setError('切り抜きの開始に失敗しました');
      setPhase('error');
    }
  };

  const handleCopy = async () => {
    await navigator.clipboard.writeText(downloadUrl);
    setCopied(true);
    window.setTimeout(() => setCopied(false), 2000);
  };

  const handleLineShare = () => {
    const lineShareUrl = `https://line.me/R/msg/text/?${encodeURIComponent(downloadUrl)}`;
    window.open(lineShareUrl, '_blank');
  };

  return (
    <div className="modal-overlay" onClick={onClose}>
      <div className="modal" onClick={(e) => e.stopPropagation()}>
        {phase === 'input' && (
          <>
            <h2 style={{ marginTop: 0, fontSize: 18 }}>切り抜きを作成</h2>
            <div className="field">
              <label htmlFor="clip-title">タイトル</label>
              <input
                id="clip-title"
                value={title}
                onChange={(e) => setTitle(e.target.value)}
                maxLength={255}
              />
            </div>
            <div className="field">
              <label>切り抜き区間</label>
              <div style={{ display: 'flex', gap: 8, alignItems: 'center' }}>
                <TimeInput
                  value={startSeconds}
                  onChange={setStartSeconds}
                  onValidityChange={setStartTimeValid}
                  ariaLabel="開始時刻"
                />
                <span className="muted">〜</span>
                <TimeInput
                  value={endSeconds}
                  onChange={setEndSeconds}
                  onValidityChange={setEndTimeValid}
                  ariaLabel="終了時刻"
                />
              </div>
            </div>
            {error && <p className="error-msg">{error}</p>}
            <div style={{ display: 'flex', gap: 10, marginTop: 18 }}>
              <button className="btn btn-primary" onClick={handleSubmit} disabled={!title}>
                切り抜きを開始する
              </button>
              <button className="btn btn-ghost" onClick={onClose}>
                キャンセル
              </button>
            </div>
          </>
        )}

        {phase === 'processing' && (
          <div style={{ textAlign: 'center', padding: '20px 0' }}>
            <div className="spinner" />
            <p style={{ marginTop: 16 }}>FFmpegで処理中...</p>
            <p className="muted">しばらくお待ちください（自動で更新されます）</p>
          </div>
        )}

        {phase === 'done' && (
          <>
            <h2 style={{ marginTop: 0, fontSize: 18 }}>✅ 切り抜きが完成しました</h2>
            <div className="field">
              <label>ダウンロードURL</label>
              <input value={downloadUrl} readOnly onFocus={(e) => e.target.select()} />
            </div>
            <div style={{ display: 'flex', gap: 10, flexWrap: 'wrap' }}>
              <button className="btn btn-primary" onClick={handleCopy}>
                {copied ? 'コピーしました ✓' : 'URLをコピー'}
              </button>
              <button className="btn btn-primary" onClick={handleLineShare}>
                LINEで共有
              </button>
              <a className="btn btn-ghost" href={downloadUrl}>
                ダウンロード
              </a>
              <button className="btn btn-ghost" onClick={onClose}>
                閉じる
              </button>
            </div>
          </>
        )}

        {phase === 'error' && (
          <>
            <p className="error-msg">{error}</p>
            <button className="btn btn-ghost" onClick={onClose}>
              閉じる
            </button>
          </>
        )}
      </div>
    </div>
  );
}
