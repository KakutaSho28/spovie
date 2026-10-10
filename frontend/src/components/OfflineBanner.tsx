import { useOnlineStatus } from '../hooks/useOnlineStatus';

/** オフライン中に全画面の上部へ表示する帯。何が使えないかを明示する */
export function OfflineBanner() {
  const online = useOnlineStatus();
  if (online) return null;

  return (
    <div className="offline-banner" role="alert">
      オフラインです。表示済みの一覧は見られますが、YouTube の再生・保存・投稿・コメントには接続が必要です。
    </div>
  );
}
