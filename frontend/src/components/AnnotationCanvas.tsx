import {
  forwardRef,
  useEffect,
  useImperativeHandle,
  useRef,
} from 'react';
import * as fabric from 'fabric';
import { scaleCanvasObjects } from '../lib/canvasScale';
import type { CanvasData } from '../types';

export type DrawTool = 'pen' | 'arrow' | 'text' | 'select';

export type AnnotationCanvasHandle = {
  /** 現在の描画を保存時サイズ付きJSONで取得する */
  toCanvasData: () => CanvasData;
  /** 保存済みJSONを読み込んで再描画する */
  loadCanvasData: (data: CanvasData) => void;
  /** 全消去 */
  clear: () => void;
  /** 直前の操作を取り消す */
  undo: () => void;
  /** 選択中のオブジェクトを削除 */
  deleteSelected: () => void;
};

type Props = {
  /** 親要素（プレーヤー）にぴったり重ねるためのサイズ */
  width: number;
  height: number;
  tool: DrawTool;
  color: string;
  /** 閲覧専用モード（共有ページ用） */
  readOnly?: boolean;
};

/**
 * プレーヤーに重ねる透明な描画キャンバス。
 * 保存時は Fabric の絶対座標とキャンバスサイズを記録し、
 * 復元時・リサイズ時は (現在サイズ / 元のサイズ) で全オブジェクトを拡大縮小する。
 */
export const AnnotationCanvas = forwardRef<AnnotationCanvasHandle, Props>(
  function AnnotationCanvas({ width, height, tool, color, readOnly = false }, ref) {
    const canvasElRef = useRef<HTMLCanvasElement>(null);
    const fabricRef = useRef<fabric.Canvas | null>(null);
    const arrowStartRef = useRef<{ x: number; y: number } | null>(null);
    const sizeRef = useRef({ width: 0, height: 0 });

    // ----- 初期化・破棄 -----
    useEffect(() => {
      if (!canvasElRef.current) return;

      const canvas = new fabric.Canvas(canvasElRef.current, {
        selection: !readOnly,
      });
      fabricRef.current = canvas;

      return () => {
        canvas.dispose();
        fabricRef.current = null;
      };
    }, [readOnly]);

    // ----- サイズ同期（プレーヤーのリサイズに追従） -----
    useEffect(() => {
      const canvas = fabricRef.current;
      if (!canvas || width === 0 || height === 0) return;
      const prev = sizeRef.current;
      canvas.setDimensions({ width, height });
      // 既存の描画もプレーヤーと同じ比率で拡大縮小する
      if (prev.width > 0 && prev.height > 0) {
        const sx = width / prev.width;
        const sy = height / prev.height;
        canvas.forEachObject((o) => {
          o.set({
            left: o.left * sx,
            top: o.top * sy,
            scaleX: o.scaleX * sx,
            scaleY: o.scaleY * sy,
          });
          o.setCoords();
        });
      }
      sizeRef.current = { width, height };
      canvas.renderAll();
    }, [width, height]);

    // ----- ツール切り替え -----
    useEffect(() => {
      const canvas = fabricRef.current;
      if (!canvas || readOnly) return;

      canvas.isDrawingMode = tool === 'pen';
      if (tool === 'pen') {
        const brush = new fabric.PencilBrush(canvas);
        brush.color = color;
        brush.width = 4;
        canvas.freeDrawingBrush = brush;
      }

      // 矢印ツール: mousedownで始点、mouseupで終点を取り矢印を生成
      const handleMouseDown = (opt: { scenePoint: fabric.Point }) => {
        if (tool !== 'arrow') return;
        arrowStartRef.current = { x: opt.scenePoint.x, y: opt.scenePoint.y };
      };

      const handleMouseUp = (opt: { scenePoint: fabric.Point }) => {
        if (tool === 'arrow' && arrowStartRef.current) {
          const start = arrowStartRef.current;
          const end = { x: opt.scenePoint.x, y: opt.scenePoint.y };
          arrowStartRef.current = null;
          if (Math.hypot(end.x - start.x, end.y - start.y) < 8) return;
          addArrow(canvas, start, end, color);
        }
        if (tool === 'text') {
          const text = new fabric.IText('テキストを入力', {
            left: opt.scenePoint.x,
            top: opt.scenePoint.y,
            fill: color,
            fontSize: 22,
            fontFamily: 'sans-serif',
          });
          canvas.add(text);
          canvas.setActiveObject(text);
          text.enterEditing();
        }
      };

      canvas.on('mouse:down', handleMouseDown);
      canvas.on('mouse:up', handleMouseUp);
      return () => {
        canvas.off('mouse:down', handleMouseDown);
        canvas.off('mouse:up', handleMouseUp);
      };
    }, [tool, color, readOnly]);

    // ----- 外部から呼べる操作 -----
    useImperativeHandle(ref, () => ({
      toCanvasData: () => {
        const canvas = fabricRef.current;
        if (!canvas) return { canvas_width: width, canvas_height: height, objects: [] };
        const json = canvas.toJSON() as { version?: string; objects: Record<string, unknown>[] };
        // 絶対座標 + 保存時サイズで保存（復元時にサイズ比で変換する）
        return {
          version: json.version,
          canvas_width: width,
          canvas_height: height,
          objects: json.objects,
        };
      },

      loadCanvasData: (data) => {
        const canvas = fabricRef.current;
        if (!canvas) return;
        // 保存時サイズ → 現在のキャンバスサイズに変換して復元
        const objects = scaleCanvasObjects(data, width, height);
        canvas
          .loadFromJSON({ version: data.version, objects })
          .then(() => {
            if (readOnly) {
              canvas.forEachObject((o) => {
                o.selectable = false;
                o.evented = false;
              });
            }
            canvas.renderAll();
          });
      },

      clear: () => fabricRef.current?.clear(),

      undo: () => {
        const canvas = fabricRef.current;
        if (!canvas) return;
        const objects = canvas.getObjects();
        const last = objects[objects.length - 1];
        if (last) canvas.remove(last);
      },

      deleteSelected: () => {
        const canvas = fabricRef.current;
        if (!canvas) return;
        canvas.getActiveObjects().forEach((o) => canvas.remove(o));
        canvas.discardActiveObject();
        canvas.renderAll();
      },
    }));

    return (
      <canvas
        ref={canvasElRef}
        width={width}
        height={height}
        style={{ position: 'absolute', inset: 0 }}
      />
    );
  },
);

/** 矢印（線 + 三角形のグループ）を追加する */
function addArrow(
  canvas: fabric.Canvas,
  start: { x: number; y: number },
  end: { x: number; y: number },
  color: string,
) {
  const angle = Math.atan2(end.y - start.y, end.x - start.x);
  const headLength = 16;

  const line = new fabric.Line([start.x, start.y, end.x, end.y], {
    stroke: color,
    strokeWidth: 4,
  });

  const head = new fabric.Triangle({
    left: end.x,
    top: end.y,
    width: headLength,
    height: headLength,
    fill: color,
    angle: (angle * 180) / Math.PI + 90,
    originX: 'center',
    originY: 'center',
  });

  const group = new fabric.Group([line, head]);
  canvas.add(group);
}

