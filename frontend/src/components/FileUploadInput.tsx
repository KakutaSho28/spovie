import { DragEvent, useRef, useState } from 'react';

const MAX_SIZE_MB = 500;

type Props = {
  file: File | null;
  onSelect: (file: File | null) => void;
  onError: (message: string) => void;
};

/** mp4専用のドラッグ&ドロップ対応ファイル入力 */
export function FileUploadInput({ file, onSelect, onError }: Props) {
  const inputRef = useRef<HTMLInputElement>(null);
  const [dragging, setDragging] = useState(false);

  const validateAndSelect = (selected: File | undefined) => {
    if (!selected) return;
    if (selected.type !== 'video/mp4') {
      onError('mp4形式の動画ファイルを選択してください');
      return;
    }
    if (selected.size > MAX_SIZE_MB * 1024 * 1024) {
      onError(`ファイルサイズは${MAX_SIZE_MB}MB以下にしてください`);
      return;
    }
    onError('');
    onSelect(selected);
  };

  const handleDrop = (e: DragEvent) => {
    e.preventDefault();
    setDragging(false);
    validateAndSelect(e.dataTransfer.files[0]);
  };

  const formatSize = (bytes: number) => `${(bytes / 1024 / 1024).toFixed(1)} MB`;

  return (
    <div
      className={`upload-drop ${dragging ? 'dragging' : ''}`}
      onDragOver={(e) => {
        e.preventDefault();
        setDragging(true);
      }}
      onDragLeave={() => setDragging(false)}
      onDrop={handleDrop}
      onClick={() => inputRef.current?.click()}
      role="button"
      tabIndex={0}
      onKeyDown={(e) => e.key === 'Enter' && inputRef.current?.click()}
    >
      <input
        ref={inputRef}
        type="file"
        accept="video/mp4"
        style={{ display: 'none' }}
        onChange={(e) => validateAndSelect(e.target.files?.[0])}
      />
      {file ? (
        <>
          <p style={{ margin: 0, fontWeight: 600 }}>{file.name}</p>
          <p className="muted" style={{ margin: '4px 0 0' }}>{formatSize(file.size)}</p>
        </>
      ) : (
        <>
          <p style={{ margin: 0 }}>ここにmp4ファイルをドロップ</p>
          <p className="muted" style={{ margin: '4px 0 0' }}>
            またはクリックして選択（最大{MAX_SIZE_MB}MB）
          </p>
        </>
      )}
    </div>
  );
}
