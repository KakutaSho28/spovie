import { useEffect, useRef, useState } from 'react';

/** YouTube IFrame API のスクリプトを一度だけ読み込む */
let apiLoading: Promise<void> | null = null;

function loadYouTubeApi(): Promise<void> {
  if (window.YT?.Player) return Promise.resolve();
  if (apiLoading) return apiLoading;

  apiLoading = new Promise((resolve) => {
    const tag = document.createElement('script');
    tag.src = 'https://www.youtube.com/iframe_api';
    document.body.appendChild(tag);
    window.onYouTubeIframeAPIReady = () => resolve();
  });
  return apiLoading;
}

type UseYouTubePlayerOptions = {
  videoId: string;
  /** ループ区間。null のときはループしない */
  loop?: { start: number; end: number } | null;
};

/**
 * YouTube IFrame Player を管理するカスタムフック
 * - プレーヤーの初期化・破棄
 * - 指定区間のループ再生（onStateChange + ポーリング監視）
 */
export function useYouTubePlayer({ videoId, loop }: UseYouTubePlayerOptions) {
  const containerRef = useRef<HTMLDivElement>(null);
  const playerRef = useRef<YT.Player | null>(null);
  const loopRef = useRef(loop);
  const [isReady, setIsReady] = useState(false);

  loopRef.current = loop ?? null;

  useEffect(() => {
    let cancelled = false;
    let intervalId: number | undefined;

    loadYouTubeApi().then(() => {
      if (cancelled || !containerRef.current) return;

      playerRef.current = new window.YT.Player(containerRef.current, {
        videoId,
        playerVars: { rel: 0, playsinline: 1 },
        events: {
          onReady: () => {
            setIsReady(true);
            // ループ監視：250ms間隔で再生位置を確認
            intervalId = window.setInterval(() => {
              const player = playerRef.current;
              const currentLoop = loopRef.current;
              if (!player || !currentLoop) return;
              const t = player.getCurrentTime();
              if (t >= currentLoop.end || t < currentLoop.start - 1) {
                player.seekTo(currentLoop.start, true);
              }
            }, 250);
          },
        },
      });
    });

    return () => {
      cancelled = true;
      if (intervalId) window.clearInterval(intervalId);
      playerRef.current?.destroy();
      playerRef.current = null;
    };
    // videoId が変わったらプレーヤーを作り直す
  }, [videoId]);

  /** 指定秒数から再生を開始する */
  const playFrom = (seconds: number) => {
    playerRef.current?.seekTo(seconds, true);
    playerRef.current?.playVideo();
  };

  const pause = () => playerRef.current?.pauseVideo();

  const getCurrentTime = () => playerRef.current?.getCurrentTime() ?? 0;

  return { containerRef, isReady, playFrom, pause, getCurrentTime };
}
