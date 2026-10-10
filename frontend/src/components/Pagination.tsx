import { pageWindow } from '../lib/pagination';

type Props = {
  currentPage: number;
  lastPage: number;
  total: number;
  onChange: (page: number) => void;
};

/** ページ送り（前へ / ページ番号 / 次へ）。1ページしかないときは何も出さない */
export function Pagination({ currentPage, lastPage, total, onChange }: Props) {
  if (lastPage <= 1) return null;

  return (
    <nav className="pagination" aria-label="ページ送り">
      <button
        className="btn btn-ghost btn-sm"
        onClick={() => onChange(currentPage - 1)}
        disabled={currentPage <= 1}
      >
        前へ
      </button>

      {pageWindow(currentPage, lastPage).map((item) =>
        typeof item === 'number' ? (
          <button
            key={item}
            className={`btn btn-sm ${item === currentPage ? 'btn-primary' : 'btn-ghost'}`}
            onClick={() => onChange(item)}
            aria-label={`${item}ページ目`}
            aria-current={item === currentPage ? 'page' : undefined}
          >
            {item}
          </button>
        ) : (
          <span key={item} className="pagination-ellipsis" aria-hidden="true">
            …
          </span>
        ),
      )}

      <button
        className="btn btn-ghost btn-sm"
        onClick={() => onChange(currentPage + 1)}
        disabled={currentPage >= lastPage}
      >
        次へ
      </button>

      <span className="pagination-total muted">全{total}件</span>
    </nav>
  );
}
