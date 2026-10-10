import { expect, test, type Page } from '@playwright/test';
import { pageWindow } from '../src/lib/pagination';

const API = 'http://api.test/api';

type Video = {
  id: number;
  type: 'youtube';
  youtube_video_id: string;
  file_url: null;
  title: string;
  team: null;
  created_at: string;
};

/** id が大きいほど新しい。一覧は新しい順 */
function makeVideos(count: number): Video[] {
  return Array.from({ length: count }, (_, i) => ({
    id: count - i,
    type: 'youtube' as const,
    youtube_video_id: 'dQw4w9WgXcQ',
    file_url: null,
    title: `動画 ${count - i}`,
    team: null,
    created_at: '2026-10-10T00:00:00+09:00',
  }));
}

/** GET /videos?page=&per_page= をページングして返すモック。受け取ったリクエストの URL を記録する */
async function mockVideos(page: Page, videos: Video[], requests: string[]) {
  await page.addInitScript(() => {
    localStorage.setItem('spovie_token', 'e2e-token');
    localStorage.setItem('spovie_user', JSON.stringify({ id: 1, name: 'E2E', email: 'e2e@example.com' }));
  });

  await page.route('http://api.test/**', async (route) => {
    const req = route.request();
    const url = new URL(req.url());
    const path = url.pathname.replace('/api', '');

    if (path === '/videos' && req.method() === 'GET') {
      requests.push(url.search);
      const perPage = Number(url.searchParams.get('per_page') ?? 20);
      const current = Number(url.searchParams.get('page') ?? 1);
      const lastPage = Math.max(1, Math.ceil(videos.length / perPage));
      return route.fulfill({
        json: {
          data: videos.slice((current - 1) * perPage, current * perPage),
          meta: { current_page: current, last_page: lastPage, per_page: perPage, total: videos.length },
        },
      });
    }
    const del = path.match(/^\/videos\/(\d+)$/);
    if (del && req.method() === 'DELETE') {
      const index = videos.findIndex((v) => v.id === Number(del[1]));
      if (index >= 0) videos.splice(index, 1);
      return route.fulfill({ json: { message: '動画を削除しました' } });
    }
    return route.fulfill({ json: { data: [] } });
  });
}

test('45 件の一覧が 20 件ずつ表示され、ページボタンで移動できる', async ({ page }) => {
  const requests: string[] = [];
  await mockVideos(page, makeVideos(45), requests);
  await page.goto('/');

  await expect(page.locator('.card')).toHaveCount(20);
  await expect(page.getByText('動画 45', { exact: true })).toBeVisible();
  await expect(page.getByText('全45件')).toBeVisible();
  await expect(page.getByRole('button', { name: '1ページ目' })).toHaveAttribute('aria-current', 'page');
  await expect(page.getByRole('button', { name: '前へ' })).toBeDisabled();
  expect(requests[0]).toContain('per_page=20');

  await page.getByRole('button', { name: '2ページ目' }).click();
  await expect(page).toHaveURL(/[?&]page=2/);
  await expect(page.getByText('動画 25', { exact: true })).toBeVisible();
  await expect(page.getByText('動画 45', { exact: true })).toHaveCount(0);
  await expect(page.locator('.card')).toHaveCount(20);

  await page.getByRole('button', { name: '次へ' }).click();
  await expect(page).toHaveURL(/[?&]page=3/);
  await expect(page.locator('.card')).toHaveCount(5);
  await expect(page.getByRole('button', { name: '次へ' })).toBeDisabled();

  await page.getByRole('button', { name: '前へ' }).click();
  await expect(page).toHaveURL(/[?&]page=2/);
  await page.getByRole('button', { name: '1ページ目' }).click();
  await expect(page).not.toHaveURL(/page=/);
});

test('URL の page を直接開いても、そのページが表示される（リロード・共有できる）', async ({ page }) => {
  const requests: string[] = [];
  await mockVideos(page, makeVideos(45), requests);

  await page.goto('/?page=3');

  await expect(page.locator('.card')).toHaveCount(5);
  await expect(page.getByRole('button', { name: '3ページ目' })).toHaveAttribute('aria-current', 'page');
});

test('20 件以下ならページ送りを表示しない', async ({ page }) => {
  await mockVideos(page, makeVideos(20), []);
  await page.goto('/');

  await expect(page.locator('.card')).toHaveCount(20);
  await expect(page.getByRole('navigation', { name: 'ページ送り' })).toHaveCount(0);
});

test('フィルタを変えると 1 ページ目に戻り、サーバーにフィルタとページが渡る', async ({ page }) => {
  const requests: string[] = [];
  await mockVideos(page, makeVideos(45), requests);
  await page.goto('/?page=2');
  await expect(page.getByRole('button', { name: '2ページ目' })).toHaveAttribute('aria-current', 'page');

  await page.getByRole('combobox').selectOption('personal');

  await expect(page).not.toHaveURL(/page=/);
  await expect.poll(() => requests.some((q) => q.includes('scope=personal') && q.includes('page=1'))).toBe(true);
});

test('最後のページの最後の 1 件を削除すると、最終ページへ戻る', async ({ page }) => {
  const requests: string[] = [];
  await mockVideos(page, makeVideos(41), requests); // 3 ページ目は 1 件だけ
  page.on('dialog', (dialog) => dialog.accept());
  await page.goto('/?page=3');
  await expect(page.locator('.card')).toHaveCount(1);

  await page.getByRole('button', { name: '削除' }).click();

  await expect(page).toHaveURL(/[?&]page=2/);
  await expect(page.locator('.card')).toHaveCount(20);
  await expect(page.getByText('全40件')).toBeVisible();
});

test.describe('pageWindow（ページ番号の並び）', () => {
  test('少ないページ数はすべて表示する', () => {
    expect(pageWindow(1, 1)).toEqual([1]);
    expect(pageWindow(2, 3)).toEqual([1, 2, 3]);
    expect(pageWindow(4, 7)).toEqual([1, 2, 3, 4, 5, 6, 7]);
  });

  test('多いページ数は先頭・末尾・現在の前後だけを出し、間を省略する', () => {
    expect(pageWindow(1, 10)).toEqual([1, 2, 'ellipsis-end', 10]);
    expect(pageWindow(5, 10)).toEqual([1, 'ellipsis-start', 4, 5, 6, 'ellipsis-end', 10]);
    expect(pageWindow(10, 10)).toEqual([1, 'ellipsis-start', 9, 10]);
  });

  test('省略記号が 1 ページ分しか隠さない場合は、そのページ番号を出す', () => {
    expect(pageWindow(4, 10)).toEqual([1, 2, 3, 4, 5, 'ellipsis-end', 10]);
    expect(pageWindow(7, 10)).toEqual([1, 'ellipsis-start', 6, 7, 8, 9, 10]);
  });
});
