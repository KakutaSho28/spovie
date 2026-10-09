import type { CanvasData, CanvasObject } from '../types';

/**
 * Fabric.js オブジェクトを「保存時のキャンバスサイズ → 現在のキャンバスサイズ」に合わせて変換する。
 *
 * 保存形式: Fabric の toJSON() の objects をそのまま（絶対座標）保存し、
 * 保存時のサイズを canvas_width / canvas_height に記録する。
 * 復元時は left / top / scaleX / scaleY に (現在サイズ / 保存時サイズ) を掛ける。
 * ペン（Path）・矢印（Group）・テキストのいずれも、原点まわりの一様な拡大縮小として正しく再現される。
 */
export function scaleCanvasObjects(data: CanvasData, width: number, height: number): CanvasObject[] {
  const baseWidth = data.canvas_width || width;
  const baseHeight = data.canvas_height || height;
  const sx = width / baseWidth;
  const sy = height / baseHeight;

  return data.objects.map((obj) => {
    const absolute = obj.__normalized ? fromLegacyNormalized(obj, baseWidth, baseHeight) : obj;
    return {
      ...absolute,
      left: mul(absolute.left, sx),
      top: mul(absolute.top, sy),
      scaleX: mul(absolute.scaleX, sx),
      scaleY: mul(absolute.scaleY, sy),
    };
  });
}

/**
 * 旧形式（__normalized: true）を保存時サイズの絶対座標に戻す。
 * 旧形式は left/top を 0〜1 に、scale を (scale × 保存時サイズ / 1000) に変換して保存していた。
 */
function fromLegacyNormalized(obj: CanvasObject, baseWidth: number, baseHeight: number): CanvasObject {
  const { __normalized, ...rest } = obj;
  void __normalized;
  return {
    ...rest,
    left: mul(rest.left, baseWidth),
    top: mul(rest.top, baseHeight),
    scaleX: mul(rest.scaleX, 1000 / baseWidth),
    scaleY: mul(rest.scaleY, 1000 / baseHeight),
  };
}

function mul(value: unknown, factor: number): unknown {
  return typeof value === 'number' ? value * factor : value;
}
