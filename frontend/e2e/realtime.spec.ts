import { expect, test, type Page } from '@playwright/test';
import { fakePusher } from './helpers/fakePusher';

test.use({ baseURL: 'http://localhost:5175' });

const API = 'http://api.test/api';
const ANNOTATION_ID = 10;

type User = { id: number; name: string; email: string };
const ALICE: User = { id: 1, name: 'アリス', email: 'a@example.com' };
const BOB: User = { id: 2, name: 'ボブ', email: 'b@example.com' };

type ApiComment = { id: number; body: string; user: { id: number; name: string }; is_own: boolean; created_at: string };

type World = {
  comments: ApiComment[];
  annotations: Record<string, unknown>[];
  authRequests: { channel: string; authorization: string | undefined }[];
  socketIdHeaders: (string | undefined)[];
};

const annotation = (id: number, comment: string) => ({
  id,
  video_id: 1,
  start_seconds: 1,
  end_seconds: 5,
  canvas_data: { canvas_width: 1280, canvas_height: 720, objects: [] },
  comment,
  comments_count: 0,
  created_at: '2026-10-10T00:00:00+09:00',
});

const comment = (id: number, body: string, user: User): ApiComment => ({
  id,
  body,
  user: { id: user.id, name: user.name },
  is_own: false,
  created_at: '2026-10-10T00:00:00+09:00',
});

function newWorld(): World {
  return { comments: [], annotations: [annotation(ANNOTATION_ID, '最初のメモ')], authRequests: [], socketIdHeaders: [] };
}

/** ユーザーとしてログイン済みの状態で、API をモックしたページを開く */
async function openAs(page: Page, user: User, world: World, nextCommentId: { value: number }) {
  await page.addInitScript(
    ([token, u]) => {
      localStorage.setItem('spovie_token', token as string);
      localStorage.setItem('spovie_user', JSON.stringify(u));
    },
    [`token-${user.id}`, user],
  );

  await page.route('http://api.test/**', async (route) => {
    const req = route.request();
    const url = req.url().replace(API, '');
    const method = req.method();
    const socketId = req.headers()['x-socket-id'];
    if (method !== 'GET') world.socketIdHeaders.push(socketId);

    if (url === '/broadcasting/auth') {
      const body = req.postDataJSON() as { channel_name: string };
      world.authRequests.push({ channel: body.channel_name, authorization: req.headers()['authorization'] });
      return route.fulfill({ json: { auth: 'e2e-key:signature' } });
    }
    if (url === '/videos/1') {
      return route.fulfill({
        json: { data: { id: 1, type: 'youtube', youtube_video_id: 'dQw4w9WgXcQ', file_url: null, title: 'テスト動画', team: null, created_at: '2026-10-10T00:00:00+09:00' } },
      });
    }
    if (url === '/videos/1/annotations') return route.fulfill({ json: { data: world.annotations } });
    if (url === `/annotations/${ANNOTATION_ID}/comments` && method === 'GET') {
      return route.fulfill({ json: { data: world.comments } });
    }
    if (url === `/annotations/${ANNOTATION_ID}/comments` && method === 'POST') {
      const body = req.postDataJSON() as { body: string };
      const created = comment(nextCommentId.value++, body.body, user);
      world.comments.push(created);
      return route.fulfill({ status: 201, json: { data: { ...created, is_own: true } } });
    }
    const del = url.match(/^\/comments\/(\d+)$/);
    if (del && method === 'DELETE') {
      world.comments = world.comments.filter((c) => c.id !== Number(del[1]));
      return route.fulfill({ json: { message: 'コメントを削除しました' } });
    }
    return route.fulfill({ json: { data: [] } });
  });
}

async function openComments(page: Page) {
  await page.goto('/videos/1/annotations');
  await page.getByRole('button', { name: /コメントを見る/ }).click();
  await expect(page.getByText('コメントなし')).toBeVisible();
}

test.describe('リアルタイム（偽の Pusher サーバー）', () => {
  test('接続中の表示と、Bearer トークン付きのチャンネル認証・購読が行われる', async ({ browser }) => {
    const world = newWorld();
    const page = await browser.newPage();
    const pusher = await fakePusher(page);
    await openAs(page, BOB, world, { value: 100 });

    await openComments(page);

    await expect(page.getByRole('status')).toHaveText('リアルタイム接続中');
    await expect.poll(() => pusher.subscribed).toEqual(
      expect.arrayContaining(['private-video.1', `private-annotation.${ANNOTATION_ID}`]),
    );
    expect(world.authRequests.map((r) => r.channel)).toEqual(
      expect.arrayContaining(['private-video.1', `private-annotation.${ANNOTATION_ID}`]),
    );
    expect(world.authRequests.every((r) => r.authorization === 'Bearer token-2')).toBe(true);
    await page.close();
  });

  test('他の人のコメントが、リロードなしで表示され、削除も反映される', async ({ browser }) => {
    const world = newWorld();
    const page = await browser.newPage();
    const pusher = await fakePusher(page);
    await openAs(page, BOB, world, { value: 100 });
    await openComments(page);
    await expect.poll(() => pusher.subscribed).toContain(`private-annotation.${ANNOTATION_ID}`);

    const fromAlice = comment(1, '3番のヘルプが遅い', ALICE);
    pusher.push(`private-annotation.${ANNOTATION_ID}`, 'comment.created', { comment: fromAlice });

    await expect(page.getByText('3番のヘルプが遅い')).toBeVisible();
    // 他人のコメントには削除ボタンが出ない（is_own は user.id から再計算される）
    await expect(page.getByRole('button', { name: '削除', exact: true }).filter({ hasText: '削除' })).toHaveCount(1); // アノテーション自体の削除ボタンのみ

    pusher.push(`private-annotation.${ANNOTATION_ID}`, 'comment.deleted', { id: 1, annotation_id: ANNOTATION_ID });
    await expect(page.getByText('3番のヘルプが遅い')).toHaveCount(0);
    await page.close();
  });

  test('自分の投稿は即時に表示され、同じコメントの通知が届いても二重にならない', async ({ browser }) => {
    const world = newWorld();
    const page = await browser.newPage();
    const pusher = await fakePusher(page);
    await openAs(page, ALICE, world, { value: 100 });
    await openComments(page);
    await expect.poll(() => pusher.subscribed).toContain(`private-annotation.${ANNOTATION_ID}`);

    await page.getByPlaceholder('コメントを追加').fill('ナイスカット');
    await page.getByRole('button', { name: '投稿' }).click();
    await expect(page.getByText('ナイスカット')).toHaveCount(1);

    // サーバーが（toOthers を無視して）自分にも通知した場合を想定
    pusher.push(`private-annotation.${ANNOTATION_ID}`, 'comment.created', { comment: comment(100, 'ナイスカット', ALICE) });
    await page.waitForTimeout(300);
    await expect(page.getByText('ナイスカット')).toHaveCount(1);

    // 自分のコメントには削除ボタンがある
    await expect(page.locator('.comment-item').getByRole('button', { name: '削除' })).toHaveCount(1);
    await page.close();
  });

  test('新しいアノテーションの通知で、一覧がリロードなしで更新される', async ({ browser }) => {
    const world = newWorld();
    const page = await browser.newPage();
    const pusher = await fakePusher(page);
    await openAs(page, BOB, world, { value: 100 });
    await page.goto('/videos/1/annotations');
    await expect(page.getByText('最初のメモ')).toBeVisible();
    await expect.poll(() => pusher.subscribed).toContain('private-video.1');

    world.annotations.unshift(annotation(11, 'アリスが追加したメモ'));
    pusher.push('private-video.1', 'annotation.created', {
      annotation: { id: 11, video_id: 1, start_seconds: 1, end_seconds: 5, comment: 'アリスが追加したメモ', created_at: '2026-10-10T00:00:00+09:00' },
    });

    await expect(page.getByText('アリスが追加したメモ')).toBeVisible();
    await expect(page.getByText('新しいアノテーションが追加されました')).toBeVisible();
    await page.close();
  });

  test('POST などの変更系リクエストに X-Socket-ID が付く（toOthers 用）', async ({ browser }) => {
    const world = newWorld();
    const page = await browser.newPage();
    const pusher = await fakePusher(page);
    await openAs(page, ALICE, world, { value: 100 });
    await openComments(page);
    await expect.poll(() => pusher.subscribed).toContain(`private-annotation.${ANNOTATION_ID}`);

    await page.getByPlaceholder('コメントを追加').fill('x');
    await page.getByRole('button', { name: '投稿' }).click();
    await expect(page.locator('.comment-item')).toHaveCount(1);

    expect(world.socketIdHeaders).toContain('1234.5678');
    await page.close();
  });

  test('切断すると表示が変わり、再接続すると切断中に増えたコメントを取得し直す', async ({ browser }) => {
    const world = newWorld();
    const page = await browser.newPage();
    // バッジの表示状態の履歴を記録する（切断は一瞬なので、現在値ではなく履歴で確認する）
    await page.addInitScript(() => {
      const w = window as unknown as { __badge: string[] };
      w.__badge = [];
      new MutationObserver(() => {
        const el = document.querySelector('.realtime-badge');
        if (el) w.__badge.push(el.className.replace('realtime-badge ', ''));
      }).observe(document, { subtree: true, childList: true, attributes: true, attributeFilter: ['class'] });
    });
    const pusher = await fakePusher(page);
    await openAs(page, BOB, world, { value: 100 });
    await openComments(page);
    await expect(page.getByRole('status')).toHaveText('リアルタイム接続中');

    // 切断中にアリスがコメントした想定（通知は届かない）
    world.comments.push(comment(5, '切断中のコメント', ALICE));
    pusher.drop();

    await expect
      .poll(() => page.evaluate(() => (window as unknown as { __badge: string[] }).__badge))
      .toContain('realtime-connecting');
    // 再接続後、表示が戻り、取りこぼしたコメントが一覧の再取得で現れる
    await expect(page.getByRole('status')).toHaveText('リアルタイム接続中');
    await expect(page.getByText('切断中のコメント')).toBeVisible();
    await page.close();
  });
});

test('Pusher のキー未設定の環境ではバッジを出さず、通常どおり動作する', async ({ browser }) => {
  const world = newWorld();
  const page = await browser.newPage({ baseURL: 'http://localhost:5174' });
  await openAs(page, BOB, world, { value: 100 });

  await page.goto('/videos/1/annotations');

  await expect(page.getByText('最初のメモ')).toBeVisible();
  await expect(page.getByRole('status')).toHaveCount(0);
  await page.close();
});
