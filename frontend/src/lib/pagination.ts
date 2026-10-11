export type PageItem = number | 'ellipsis-start' | 'ellipsis-end';

/**
 * ページ番号ボタンの並びを作る。常に先頭・末尾・現在ページの前後を出し、間は省略記号にする。
 * 例: 全10ページで現在5 → [1, …, 4, 5, 6, …, 10]。7ページ以下はすべて表示する。
 */
export function pageWindow(current: number, last: number, siblings = 1): PageItem[] {
  if (last <= 5 + siblings * 2) {
    return Array.from({ length: last }, (_, i) => i + 1);
  }

  const start = Math.max(2, current - siblings);
  const end = Math.min(last - 1, current + siblings);
  const items: PageItem[] = [1];

  if (start > 2) items.push(start === 3 ? 2 : 'ellipsis-start');
  for (let page = start; page <= end; page++) items.push(page);
  if (end < last - 1) items.push(end === last - 2 ? last - 1 : 'ellipsis-end');

  items.push(last);
  return items;
}
