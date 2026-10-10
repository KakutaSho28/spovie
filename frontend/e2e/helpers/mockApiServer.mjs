// PWA の E2E 用の最小 API サーバー（依存なし）。
// Service Worker の通信は Playwright の route では横取りできないため、実際の HTTP サーバーとして立てる。
import { createServer } from 'node:http';

const PORT = Number(process.env.MOCK_API_PORT ?? 5180);
const ORIGIN = process.env.MOCK_API_ALLOW_ORIGIN ?? 'http://localhost:5176';

const video = {
  id: 1,
  type: 'upload',
  youtube_video_id: null,
  file_url: `http://localhost:${PORT}/storage/videos/sample.mp4`,
  title: 'キャッシュ確認用の動画',
  team: null,
  created_at: '2026-10-10T00:00:00+09:00',
};

// ネットワーク断の再現用。context.setOffline() は Service Worker 自身の通信には効かないため、
// サーバー側で接続を切る（GET /__down?on=1 で切断、on=0 で復帰）。
let down = false;

createServer((req, res) => {
  const url = new URL(req.url ?? '/', `http://localhost:${PORT}`);

  if (url.pathname === '/__down') {
    down = url.searchParams.get('on') === '1';
    res.writeHead(200).end(String(down));
    return;
  }
  if (down) {
    req.socket.destroy();
    return;
  }

  res.setHeader('Access-Control-Allow-Origin', ORIGIN);
  res.setHeader('Access-Control-Allow-Headers', 'authorization, content-type, accept, x-socket-id');
  res.setHeader('Access-Control-Allow-Methods', 'GET, POST, DELETE, OPTIONS');
  res.setHeader('Vary', 'Origin');

  if (req.method === 'OPTIONS') {
    res.writeHead(204).end();
    return;
  }

  const json = (body, status = 200) => {
    res.writeHead(status, { 'Content-Type': 'application/json' }).end(JSON.stringify(body));
  };

  if (url.pathname === '/api/health') return json({ data: { status: 'ok', database: 'ok' } });
  if (url.pathname === '/api/videos') return json({ data: [video], meta: { current_page: 1, last_page: 1 } });
  if (url.pathname === '/api/teams') return json({ data: [] });
  if (url.pathname === '/api/auth/logout') return json({ message: 'ログアウトしました' });
  if (url.pathname === '/api/broadcasting/auth') return json({ auth: 'key:signature' });
  if (url.pathname.startsWith('/api/clips/')) {
    res.writeHead(200, { 'Content-Type': 'video/mp4' }).end('fake-clip-bytes');
    return;
  }
  if (url.pathname.endsWith('.mp4')) {
    res.writeHead(200, { 'Content-Type': 'video/mp4' }).end('fake-video-bytes');
    return;
  }
  return json({ message: 'not found' }, 404);
}).listen(PORT, () => console.log(`mock api on :${PORT}`));
