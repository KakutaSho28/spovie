import { expect, test, type Page } from '@playwright/test';
import { scaleCanvasObjects } from '../src/lib/canvasScale';

const API = 'http://api.test/api';

const VIDEO = {
  id: 1,
  type: 'upload',
  youtube_video_id: null,
  // 再生は不要（キャンバスの検証のみ）なので存在しないURLで良い
  file_url: 'http://api.test/storage/videos/missing.mp4',
  title: 'E2E テスト動画',
  team: null,
  created_at: '2026-10-10T00:00:00+09:00',
};

/** 描画色 → 検証対象の図形 */
const SHAPES = {
  pen: [255, 59, 48], // #ff3b30
  arrow: [255, 214, 10], // #ffd60a
  text: [255, 255, 255], // #ffffff
} as const;

type ShapeName = keyof typeof SHAPES;
/** キャンバスに対する相対座標（0〜1）のバウンディングボックス */
type Box = { x0: number; y0: number; x1: number; y1: number };

type Store = { annotation: Record<string, unknown> | null };

async function mockApi(page: Page, store: Store) {
  await page.addInitScript(() => {
    localStorage.setItem('spovie_token', 'e2e-token');
    localStorage.setItem('spovie_user', JSON.stringify({ id: 1, name: 'E2E', email: 'e2e@example.com' }));
  });

  await page.route('http://api.test/**', async (route) => {
    const req = route.request();
    const url = req.url();

    if (url.endsWith('.mp4')) return route.abort();

    if (url === `${API}/videos/1` && req.method() === 'GET') {
      return route.fulfill({ json: { data: VIDEO } });
    }
    if (url === `${API}/videos/1/annotations` && req.method() === 'POST') {
      const body = req.postDataJSON() as Record<string, unknown>;
      store.annotation = { id: 1, video_id: 1, comments_count: 0, created_at: VIDEO.created_at, ...body };
      return route.fulfill({ status: 201, json: { data: store.annotation } });
    }
    if (url === `${API}/videos/1/annotations` && req.method() === 'GET') {
      return route.fulfill({ json: { data: store.annotation ? [store.annotation] : [] } });
    }
    return route.fulfill({ json: { data: [] } });
  });
}

/** lower-canvas の描画結果から、色ごとのバウンディングボックスを相対座標で求める */
async function measureShapes(page: Page): Promise<Record<ShapeName, Box | null>> {
  return page.locator('canvas.lower-canvas').evaluate((el, shapes) => {
    const canvas = el as HTMLCanvasElement;
    const ctx = canvas.getContext('2d');
    if (!ctx) throw new Error('no 2d context');
    const { width, height } = canvas;
    const { data } = ctx.getImageData(0, 0, width, height);

    const result: Record<string, { x0: number; y0: number; x1: number; y1: number } | null> = {};
    for (const [name, [r, g, b]] of Object.entries(shapes)) {
      let x0 = Infinity;
      let y0 = Infinity;
      let x1 = -Infinity;
      let y1 = -Infinity;
      for (let y = 0; y < height; y++) {
        for (let x = 0; x < width; x++) {
          const i = (y * width + x) * 4;
          if (data[i + 3] < 200) continue;
          if (Math.abs(data[i] - r) + Math.abs(data[i + 1] - g) + Math.abs(data[i + 2] - b) > 30) continue;
          x0 = Math.min(x0, x);
          y0 = Math.min(y0, y);
          x1 = Math.max(x1, x);
          y1 = Math.max(y1, y);
        }
      }
      result[name] = Number.isFinite(x0)
        ? { x0: x0 / width, y0: y0 / height, x1: (x1 + 1) / width, y1: (y1 + 1) / height }
        : null;
    }
    return result;
  }, SHAPES) as Promise<Record<ShapeName, Box | null>>;
}

/** キャンバス上の相対座標 → ページ座標 */
async function at(page: Page, rx: number, ry: number) {
  const box = await page.locator('canvas.upper-canvas').boundingBox();
  if (!box) throw new Error('canvas not found');
  return { x: box.x + box.width * rx, y: box.y + box.height * ry };
}

test('pen / arrow / text keep their relative position when reopened at a smaller width', async ({ browser }) => {
  const store: Store = { annotation: null };

  // ---- 1280px 幅で描画して保存 ----
  const wide = await browser.newPage({ viewport: { width: 1280, height: 900 } });
  await mockApi(wide, store);
  await wide.goto('/videos/1/annotate');
  await expect(wide.locator('canvas.upper-canvas')).toBeVisible();

  // ペン（赤）: 左上の波線
  await wide.getByRole('button', { name: 'ペン', exact: true }).click();
  await wide.getByRole('button', { name: '色 #ff3b30' }).click();
  let p = await at(wide, 0.1, 0.2);
  await wide.mouse.move(p.x, p.y);
  await wide.mouse.down();
  for (const [rx, ry] of [[0.15, 0.3], [0.2, 0.22], [0.25, 0.35], [0.3, 0.25]]) {
    p = await at(wide, rx, ry);
    await wide.mouse.move(p.x, p.y, { steps: 5 });
  }
  await wide.mouse.up();

  // 矢印（黄）: 中央から右下へ
  await wide.getByRole('button', { name: '矢印', exact: true }).click();
  await wide.getByRole('button', { name: '色 #ffd60a' }).click();
  p = await at(wide, 0.45, 0.45);
  await wide.mouse.move(p.x, p.y);
  await wide.mouse.down();
  p = await at(wide, 0.75, 0.7);
  await wide.mouse.move(p.x, p.y, { steps: 10 });
  await wide.mouse.up();

  // テキスト（白）: 右上
  await wide.getByRole('button', { name: 'テキスト', exact: true }).click();
  await wide.getByRole('button', { name: '色 #ffffff' }).click();
  p = await at(wide, 0.6, 0.15);
  await wide.mouse.click(p.x, p.y);
  await wide.keyboard.press('Escape');

  const before = await measureShapes(wide);
  for (const name of Object.keys(SHAPES) as ShapeName[]) {
    expect(before[name], `${name} should be drawn at 1280px`).not.toBeNull();
  }
  const wideCanvasWidth = (await wide.locator('canvas.upper-canvas').boundingBox())?.width ?? 0;

  await wide.getByRole('button', { name: '保存する' }).click();
  await expect.poll(() => store.annotation).not.toBeNull();
  await wide.close();

  // ---- 640px 幅で開き直す ----
  const narrow = await browser.newPage({ viewport: { width: 640, height: 900 } });
  await mockApi(narrow, store);
  await narrow.goto('/videos/1/annotate?annotationId=1');
  await expect(narrow.locator('canvas.upper-canvas')).toBeVisible();
  const narrowCanvasWidth = (await narrow.locator('canvas.upper-canvas').boundingBox())?.width ?? 0;
  expect(narrowCanvasWidth).toBeLessThan(wideCanvasWidth * 0.75);

  await expect
    .poll(async () => Object.values(await measureShapes(narrow)).every((b) => b !== null))
    .toBe(true);
  const after = await measureShapes(narrow);

  // 相対位置・相対サイズが一致すること（アンチエイリアス分の誤差を許容）
  const TOLERANCE = 0.02;
  for (const name of Object.keys(SHAPES) as ShapeName[]) {
    const a = before[name] as Box;
    const b = after[name] as Box;
    for (const key of ['x0', 'y0', 'x1', 'y1'] as const) {
      expect(Math.abs(a[key] - b[key]), `${name}.${key}: 1280px=${a[key].toFixed(3)} 640px=${b[key].toFixed(3)}`)
        .toBeLessThan(TOLERANCE);
    }
  }
  await narrow.close();
});

test('legacy normalized data (__normalized) is restored at the saved geometry', () => {
  // 旧形式: 1000px 幅で left=200, top=100, scaleX=scaleY=1 の図形
  const legacy = {
    canvas_width: 1000,
    canvas_height: 500,
    objects: [{ type: 'Path', left: 0.2, top: 0.2, scaleX: 1, scaleY: 0.5, __normalized: true }],
  };

  const [atSaved] = scaleCanvasObjects(legacy, 1000, 500);
  expect(atSaved).toMatchObject({ left: 200, top: 100, scaleX: 1, scaleY: 1 });
  expect(atSaved).not.toHaveProperty('__normalized');

  const [atHalf] = scaleCanvasObjects(legacy, 500, 250);
  expect(atHalf).toMatchObject({ left: 100, top: 50, scaleX: 0.5, scaleY: 0.5 });
});

test('scaleX / scaleY / left / top が無い図形でも、undefined で上書きせずに拡大縮小する', () => {
  // 手で作ったデータ（デモ用シード等）は、Fabric の toJSON と違って scale を持たないことがある
  const data = {
    canvas_width: 1000,
    canvas_height: 500,
    objects: [{ type: 'Circle', radius: 40 }, { type: 'IText', text: 'x', left: 100, top: 50 }],
  };

  const [circle, text] = scaleCanvasObjects(data, 500, 250);

  expect(circle).toMatchObject({ scaleX: 0.5, scaleY: 0.5, radius: 40 });
  expect(circle).not.toHaveProperty('left');
  expect(circle).not.toHaveProperty('top');
  expect(text).toMatchObject({ left: 50, top: 25, scaleX: 0.5, scaleY: 0.5 });
  for (const value of Object.values(circle)) expect(value).not.toBeUndefined();
});
