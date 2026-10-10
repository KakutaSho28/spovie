import { defineConfig } from 'vite';
import react from '@vitejs/plugin-react';
import { VitePWA } from 'vite-plugin-pwa';

/** API レスポンスのランタイムキャッシュ名（ログイン/ログアウト時に破棄する。src/lib/pwa.ts と合わせる） */
const API_CACHE = 'spovie-api';

export default defineConfig({
  plugins: [
    react(),
    VitePWA({
      registerType: 'autoUpdate',
      includeAssets: ['favicon.svg', 'icons/apple-touch-icon.png'],
      manifest: {
        name: 'Spovie',
        short_name: 'Spovie',
        description: 'スポーツ動画にペン・矢印・テキストで書き込み、チームで共有するアノテーションツール',
        lang: 'ja',
        start_url: '/',
        scope: '/',
        display: 'standalone',
        theme_color: '#ff8a3d',
        background_color: '#101513',
        icons: [
          { src: '/icons/icon-192.png', sizes: '192x192', type: 'image/png', purpose: 'any' },
          { src: '/icons/icon-512.png', sizes: '512x512', type: 'image/png', purpose: 'any' },
          { src: '/icons/icon-maskable-512.png', sizes: '512x512', type: 'image/png', purpose: 'maskable' },
        ],
      },
      workbox: {
        // アプリシェル（HTML / JS / CSS / アイコン）をプリキャッシュ。SPA なのでどの画面の遷移もシェルで開く
        globPatterns: ['**/*.{js,css,html,svg,png,ico,webmanifest}'],
        navigateFallback: '/index.html',
        navigateFallbackDenylist: [/^\/api\//],
        cleanupOutdatedCaches: true,
        clientsClaim: true,
        skipWaiting: true,
        // 上から順に評価され、最初に一致したものが使われる。キャッシュしないものを先に NetworkOnly で除外する
        runtimeCaching: [
          // WebSocket 認証。トークン付きの毎回異なる応答なのでキャッシュしない
          {
            urlPattern: ({ url }) => url.pathname === '/api/broadcasting/auth',
            handler: 'NetworkOnly',
          },
          // クリップのダウンロード（認証不要の共有URL。動画データなのでキャッシュしない）
          {
            urlPattern: ({ url }) => /^\/api\/clips\/[^/]+\/download\//.test(url.pathname),
            handler: 'NetworkOnly',
          },
          // 動画ファイル（アップロード動画・署名付きURL・Range リクエスト）。容量が大きく、
          // 著作権ポリシー上も端末にキャッシュしない
          {
            urlPattern: ({ request, url }) =>
              request.destination === 'video' ||
              request.destination === 'audio' ||
              request.headers.has('range') ||
              /\.(mp4|m4v|mov|webm)$/i.test(url.pathname),
            handler: 'NetworkOnly',
          },
          // 一覧などの GET API: ネットワーク優先（3秒で諦めてキャッシュ）、最大5分
          {
            urlPattern: ({ request, url }) => request.method === 'GET' && url.pathname.startsWith('/api/'),
            handler: 'NetworkFirst',
            options: {
              cacheName: API_CACHE,
              networkTimeoutSeconds: 3,
              expiration: { maxAgeSeconds: 5 * 60, maxEntries: 50 },
              cacheableResponse: { statuses: [200] },
            },
          },
        ],
      },
    }),
  ],
  // E2E で複数の dev サーバーを同時に起動してもキャッシュが衝突しないようにする
  cacheDir: process.env.VITE_CACHE_DIR ?? 'node_modules/.vite',
  server: {
    host: true,
    port: 5173,
  },
});
