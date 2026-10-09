import { defineConfig, devices } from '@playwright/test';

const PORT = 5174;
/** Pusher のキーを設定した dev サーバー（リアルタイム機能のテスト用） */
const REALTIME_PORT = 5175;

/**
 * E2E テスト。API は各テストで page.route によりモックするため、バックエンド不要。
 * リアルタイムのテストは WebSocket も偽の Pusher サーバーに差し替える（実際のキー・通信は不要）。
 */
export default defineConfig({
  testDir: './e2e',
  timeout: 30_000,
  retries: process.env.CI ? 1 : 0,
  reporter: process.env.CI ? 'github' : 'list',
  use: {
    baseURL: `http://localhost:${PORT}`,
    ...devices['Desktop Chrome'],
    deviceScaleFactor: 1,
  },
  webServer: [
    {
      command: `npm run dev -- --port ${PORT} --strictPort`,
      url: `http://localhost:${PORT}`,
      reuseExistingServer: !process.env.CI,
      env: { VITE_API_BASE_URL: 'http://api.test/api' },
    },
    {
      command: `npm run dev -- --port ${REALTIME_PORT} --strictPort`,
      url: `http://localhost:${REALTIME_PORT}`,
      reuseExistingServer: !process.env.CI,
      env: {
        VITE_API_BASE_URL: 'http://api.test/api',
        VITE_PUSHER_APP_KEY: 'e2e-key',
        VITE_PUSHER_APP_CLUSTER: 'ap3',
        VITE_CACHE_DIR: 'node_modules/.vite-realtime',
      },
    },
  ],
});
