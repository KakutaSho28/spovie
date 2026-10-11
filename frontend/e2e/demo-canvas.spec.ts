import { expect, test } from '@playwright/test';
import { readFileSync } from 'node:fs';

/**
 * デモ用シーダー（backend/database/seeders/DemoSeeder.php）が保存する描画データを、
 * フロントが実際に復元して描画できることを確認する。同じ JSON ファイルを使うので、
 * シーダー側のデータが壊れる（Fabric が読めない形式になる）とここで検知できる。
 */
const canvasData = JSON.parse(
  readFileSync(new URL('../../backend/database/seeders/demo-canvas.json', import.meta.url), 'utf8'),
);

const API = 'http://api.test/api';

test('デモ用のアノテーションの描画（円とテキスト）が復元される', async ({ page }) => {
  await page.addInitScript(() => {
    localStorage.setItem('spovie_token', 'e2e-token');
    localStorage.setItem('spovie_user', JSON.stringify({ id: 1, name: 'アリス（デモ）', email: 'demo1@spovie.example' }));
  });
  await page.route('http://api.test/**', async (route) => {
    const url = route.request().url().replace(API, '');
    if (url === '/videos/1') {
      return route.fulfill({
        json: {
          data: { id: 1, type: 'upload', youtube_video_id: null, file_url: 'http://api.test/missing.mp4', title: 'デモ動画', team: null, created_at: '2026-10-10T00:00:00+09:00' },
        },
      });
    }
    if (url === '/videos/1/annotations') {
      return route.fulfill({
        json: {
          data: [
            { id: 5, video_id: 1, start_seconds: 12, end_seconds: 20, canvas_data: canvasData, comment: 'デモ', comments_count: 0, created_at: '2026-10-10T00:00:00+09:00' },
          ],
        },
      });
    }
    return route.fulfill({ json: { data: [] } });
  });

  await page.setViewportSize({ width: 1280, height: 900 });
  await page.goto('/videos/1/annotate?annotationId=5');
  await expect(page.locator('canvas.lower-canvas')).toBeVisible();

  const found = () =>
    page.locator('canvas.lower-canvas').evaluate((el) => {
      const canvas = el as HTMLCanvasElement;
      const { data } = canvas.getContext('2d')!.getImageData(0, 0, canvas.width, canvas.height);
      const has = (r: number, g: number, b: number) => {
        for (let i = 0; i < data.length; i += 4) {
          if (data[i + 3] > 200 && Math.abs(data[i] - r) + Math.abs(data[i + 1] - g) + Math.abs(data[i + 2] - b) < 30) return true;
        }
        return false;
      };
      return { circle: has(255, 59, 48), text: has(255, 214, 10) };
    });

  await expect.poll(found).toEqual({ circle: true, text: true });
});
