import { FormEvent, useEffect, useMemo, useState } from 'react';
import { Link, useNavigate } from 'react-router-dom';
import { apiClient } from '../api/client';
import { FileUploadInput } from '../components/FileUploadInput';
import type { Team } from '../types';

type Mode = 'youtube' | 'upload';

/** YouTube URLから動画IDを抽出する（watch?v= / youtu.be / embed 対応） */
function extractYoutubeVideoId(url: string): string | null {
  const match = url.match(/(?:v=|youtu\.be\/|embed\/)([a-zA-Z0-9_-]{11})/);
  return match?.[1] ?? null;
}

export function VideoNewPage() {
  const navigate = useNavigate();
  const [mode, setMode] = useState<Mode>('youtube');
  const [title, setTitle] = useState('');
  const [url, setUrl] = useState('');
  const [file, setFile] = useState<File | null>(null);
  const [progress, setProgress] = useState(0);
  const [error, setError] = useState('');
  const [loading, setLoading] = useState(false);
  const [teams, setTeams] = useState<Team[]>([]);
  const [teamId, setTeamId] = useState('');

  const previewId = useMemo(() => extractYoutubeVideoId(url), [url]);

  useEffect(() => {
    apiClient.get('/teams').then((res) => setTeams(res.data.data));
  }, []);

  const handleYoutubeSubmit = async (e: FormEvent) => {
    e.preventDefault();
    setError('');
    if (!previewId) {
      setError('YouTubeのURLを入力してください');
      return;
    }
    setLoading(true);
    try {
      const res = await apiClient.post('/videos', {
        title,
        youtube_url: url,
        team_id: teamId ? Number(teamId) : null,
      });
      navigate(`/videos/${res.data.data.id}/annotations`);
    } catch {
      setError('登録に失敗しました。URLとタイトルを確認してください。');
      setLoading(false);
    }
  };

  const handleUploadSubmit = async (e: FormEvent) => {
    e.preventDefault();
    setError('');
    if (!file) {
      setError('動画ファイルを選択してください');
      return;
    }
    setLoading(true);
    setProgress(0);
    try {
      const formData = new FormData();
      formData.append('title', title);
      formData.append('file', file);
      if (teamId) {
        formData.append('team_id', teamId);
      }
      const res = await apiClient.post('/videos/upload', formData, {
        headers: { 'Content-Type': 'multipart/form-data' },
        onUploadProgress: (event) => {
          if (event.total) {
            setProgress(Math.round((event.loaded / event.total) * 100));
          }
        },
      });
      navigate(`/videos/${res.data.data.id}/annotations`);
    } catch {
      setError('アップロードに失敗しました。ファイルを確認してください。');
      setLoading(false);
    }
  };

  return (
    <>
      <Link to="/" className="link muted">← 動画一覧に戻る</Link>
      <h1 className="page-title" style={{ marginTop: 12 }}>動画を追加</h1>

      <div className="mode-tabs">
        <button
          className={`mode-tab ${mode === 'youtube' ? 'active' : ''}`}
          onClick={() => setMode('youtube')}
          type="button"
        >
          YouTube URL
        </button>
        <button
          className={`mode-tab ${mode === 'upload' ? 'active' : ''}`}
          onClick={() => setMode('upload')}
          type="button"
        >
          動画をアップロード
        </button>
      </div>

      <form
        onSubmit={mode === 'youtube' ? handleYoutubeSubmit : handleUploadSubmit}
        style={{ maxWidth: 560 }}
      >
        <div className="field">
          <label htmlFor="title">タイトル</label>
          <input
            id="title"
            value={title}
            onChange={(e) => setTitle(e.target.value)}
            placeholder="例: vs 渋谷バスケ 2026/6/1"
            required
            maxLength={255}
          />
        </div>

        <div className="field">
          <label htmlFor="team-id">追加先</label>
          <select id="team-id" value={teamId} onChange={(e) => setTeamId(e.target.value)}>
            <option value="">個人で追加</option>
            {teams.map((team) => (
              <option key={team.id} value={team.id}>{team.name}</option>
            ))}
          </select>
        </div>

        {mode === 'youtube' ? (
          <>
            <div className="field">
              <label htmlFor="url">YouTube URL</label>
              <input
                id="url"
                type="url"
                value={url}
                onChange={(e) => setUrl(e.target.value)}
                placeholder="https://www.youtube.com/watch?v=..."
                required
              />
            </div>
            {previewId && (
              <div className="field">
                <label>プレビュー</label>
                <img
                  src={`https://img.youtube.com/vi/${previewId}/mqdefault.jpg`}
                  alt="サムネイルプレビュー"
                  style={{ borderRadius: 10, maxWidth: 320, display: 'block' }}
                />
              </div>
            )}
            <p className="muted">
              YouTube動画はアノテーション・共有リンクに対応しています（切り抜き保存は不可）。
            </p>
          </>
        ) : (
          <>
            <div className="field">
              <label>動画ファイル（mp4）</label>
              <FileUploadInput file={file} onSelect={setFile} onError={setError} />
              {loading && (
                <div className="upload-progress">
                  <div style={{ width: `${progress}%` }} />
                </div>
              )}
            </div>
            <p className="muted">
              アップロード動画はアノテーション・切り抜き保存・LINE共有に対応しています。
              自分たちで撮影した動画のみアップロードしてください。
            </p>
          </>
        )}

        {error && <p className="error-msg">{error}</p>}
        <button type="submit" className="btn btn-primary" disabled={loading}>
          {loading
            ? mode === 'upload'
              ? `アップロード中... ${progress}%`
              : '登録中...'
            : '登録する'}
        </button>
      </form>
    </>
  );
}
