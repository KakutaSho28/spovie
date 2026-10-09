const KEY = 'spovie_pending_invite';

/** 未ログインで招待リンクを開いたとき、ログイン後に参加できるようトークンを覚えておく */
export function savePendingInvite(token: string): void {
  try {
    sessionStorage.setItem(KEY, token);
  } catch {
    // ストレージが使えない環境では、招待リンクを開き直してもらう
  }
}

/** 参加処理を始めたら保留を消す */
export function clearPendingInvite(): void {
  try {
    sessionStorage.removeItem(KEY);
  } catch {
    // 何もしない
  }
}

/**
 * ログイン / 登録後の遷移先。保留中の招待があればそのチーム参加ページ、なければ '/'。
 * 読み取り専用（描画中や複数箇所から呼ばれても結果が変わらない）。消すのは参加ページの責務。
 */
export function postLoginPath(): string {
  try {
    const token = sessionStorage.getItem(KEY);
    if (token) return `/teams/join/${encodeURIComponent(token)}`;
  } catch {
    // 何もしない
  }
  return '/';
}
