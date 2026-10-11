import { expect, test, type Page } from '@playwright/test';

const API = 'http://api.test/api';

const TEAM = {
  id: 7,
  name: 'FC スポビー',
  invite_token: 'INVITE123',
  invite_url: 'http://localhost:5174/teams/join/INVITE123',
  owner: { id: 2, name: 'オーナー' },
  members: [
    { id: 2, name: 'オーナー', role: 'owner' },
    { id: 1, name: 'E2E', role: 'member' },
  ],
  created_at: '2026-10-10T00:00:00+09:00',
};

const USER = { id: 1, name: 'E2E', email: 'e2e@example.com' };

type Calls = { method: string; url: string; body: unknown }[];

async function mockApi(page: Page, calls: Calls) {
  await page.route('http://api.test/**', async (route) => {
    const req = route.request();
    const url = req.url();
    let body: unknown = null;
    try {
      body = req.postDataJSON();
    } catch {
      body = null;
    }
    calls.push({ method: req.method(), url, body });

    if (url === `${API}/auth/login`) {
      return route.fulfill({ json: { data: { user: USER, token: 'e2e-token' } } });
    }
    if (url === `${API}/teams/join` && req.method() === 'POST') {
      return route.fulfill({ json: { data: TEAM } });
    }
    if (url === `${API}/teams/7`) return route.fulfill({ json: { data: TEAM } });
    if (url === `${API}/teams`) return route.fulfill({ json: { data: [TEAM] } });
    return route.fulfill({ json: { data: [] } });
  });
}

test('招待リンクを未ログインで開くと、ログイン後に自動でチームへ参加する', async ({ page }) => {
  const calls: Calls = [];
  await mockApi(page, calls);

  await page.goto('/teams/join/INVITE123');
  await expect(page).toHaveURL(/\/login$/);
  expect(calls.some((c) => c.url.endsWith('/teams/join'))).toBe(false);

  await page.getByLabel('メールアドレス').fill('e2e@example.com');
  await page.getByLabel('パスワード').fill('password123');
  await page.getByRole('button', { name: 'ログイン', exact: true }).click();

  await expect(page).toHaveURL(/\/teams\/7$/);
  await expect(page.getByRole('heading', { name: TEAM.name })).toBeVisible();

  const join = calls.find((c) => c.url === `${API}/teams/join`);
  expect(join?.method).toBe('POST');
  expect(join?.body).toEqual({ invite_token: 'INVITE123' });
});

test('ログイン済みで招待リンクを開くと、その場で参加してチーム詳細へ移動する', async ({ page }) => {
  const calls: Calls = [];
  await page.addInitScript((user) => {
    localStorage.setItem('spovie_token', 'e2e-token');
    localStorage.setItem('spovie_user', JSON.stringify(user));
  }, USER);
  await mockApi(page, calls);

  await page.goto('/teams/join/INVITE123');

  await expect(page).toHaveURL(/\/teams\/7$/);
  expect(calls.filter((c) => c.url === `${API}/teams/join`)).toHaveLength(1);
});

test('動画一覧のフィルタはサーバー側（scope / team_id）で行う', async ({ page }) => {
  const calls: Calls = [];
  await page.addInitScript((user) => {
    localStorage.setItem('spovie_token', 'e2e-token');
    localStorage.setItem('spovie_user', JSON.stringify(user));
  }, USER);
  await mockApi(page, calls);

  await page.goto('/');
  await expect(page.getByRole('combobox')).toBeVisible();

  await page.getByRole('combobox').selectOption('personal');
  await expect.poll(() => calls.some((c) => c.url.includes('/videos?scope=personal'))).toBe(true);

  await page.getByRole('combobox').selectOption('7');
  await expect.poll(() => calls.some((c) => c.url.includes('/videos?team_id=7'))).toBe(true);
});
