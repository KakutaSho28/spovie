/** API レスポンスのランタイムキャッシュ名（vite.config.ts の API_CACHE と同じ値） */
export const API_CACHE_NAME = 'spovie-api';

/**
 * 端末に残っている API キャッシュを破棄する。
 * キャッシュは URL 単位で、ユーザーごとのデータが入るため、ログイン/ログアウトのたびに消して
 * 別ユーザーのデータをオフライン時に見せないようにする。
 */
export async function clearApiCache(): Promise<void> {
  try {
    if ('caches' in window) await caches.delete(API_CACHE_NAME);
  } catch {
    // キャッシュが使えない環境では何もしない
  }
}
