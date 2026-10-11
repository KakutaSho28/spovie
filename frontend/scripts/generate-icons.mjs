// PWA アイコン（PNG）を SVG から生成する。結果は public/icons にコミット済みなので、通常のビルドでは実行不要。
// 使い方: cd frontend && node scripts/generate-icons.mjs（Playwright の Chromium が必要）
import { chromium } from '@playwright/test';
import { readFileSync } from 'node:fs';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';

const root = join(dirname(fileURLToPath(import.meta.url)), '..', 'public', 'icons');
const targets = [
  { svg: 'icon.svg', out: 'icon-192.png', size: 192 },
  { svg: 'icon.svg', out: 'icon-512.png', size: 512 },
  { svg: 'icon-maskable.svg', out: 'icon-maskable-512.png', size: 512 },
  // iOS は角丸を自前で付けるため、背景が全面塗りのバージョンを使う
  { svg: 'icon-maskable.svg', out: 'apple-touch-icon.png', size: 180 },
];

const browser = await chromium.launch();
const page = await browser.newPage();
for (const { svg, out, size } of targets) {
  const markup = readFileSync(join(root, svg), 'utf8');
  await page.setViewportSize({ width: size, height: size });
  await page.setContent(
    `<style>html,body{margin:0;background:transparent}svg{display:block;width:${size}px;height:${size}px}</style>${markup}`,
  );
  await page.screenshot({ path: join(root, out), omitBackground: true, clip: { x: 0, y: 0, width: size, height: size } });
  console.log('generated', out);
}
await browser.close();
