import { RefObject, useEffect } from 'react';

type Options = {
  videoRef: RefObject<HTMLVideoElement>;
  /** ループ区間。null のときはループしない */
  loop: { start: number; end: number } | null;
};

/**
 * HTML5 <video> 要素で指定区間をループ再生させるフック
 * timeupdate イベントで再生位置を監視し、区間外に出たら開始秒数へ戻す
 */
export function useHtml5VideoLoop({ videoRef, loop }: Options) {
  useEffect(() => {
    const video = videoRef.current;
    if (!video || !loop) return;

    const handleTimeUpdate = () => {
      if (video.currentTime >= loop.end || video.currentTime < loop.start - 1) {
        video.currentTime = loop.start;
      }
    };

    video.addEventListener('timeupdate', handleTimeUpdate);
    return () => video.removeEventListener('timeupdate', handleTimeUpdate);
  }, [videoRef, loop]);
}
