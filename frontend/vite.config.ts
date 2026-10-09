import { defineConfig } from 'vite';
import react from '@vitejs/plugin-react';

export default defineConfig({
  plugins: [react()],
  // E2E で複数の dev サーバーを同時に起動してもキャッシュが衝突しないようにする
  cacheDir: process.env.VITE_CACHE_DIR ?? 'node_modules/.vite',
  server: {
    host: true,
    port: 5173,
  },
});
