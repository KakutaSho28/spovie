export function formatTime(seconds: number): string {
  const safeSeconds = Number.isFinite(seconds) && seconds > 0 ? seconds : 0;
  const minutes = Math.floor(safeSeconds / 60);
  const remaining = safeSeconds - minutes * 60;
  const isWholeSecond = Math.abs(remaining - Math.round(remaining)) < 0.001;

  if (isWholeSecond) {
    return `${minutes}:${String(Math.round(remaining)).padStart(2, '0')}`;
  }

  const fixed = remaining.toFixed(3).replace(/0+$/, '').replace(/\.$/, '');
  const [whole, fraction] = fixed.split('.');
  return `${minutes}:${whole.padStart(2, '0')}.${fraction}`;
}

export function parseTimeInput(value: string): number | null {
  const trimmed = value.trim();
  if (!trimmed) return null;

  if (/^\d+(?:\.\d+)?$/.test(trimmed)) {
    return Number(trimmed);
  }

  const match = trimmed.match(/^(\d+):([0-5]?\d(?:\.\d+)?)$/);
  if (!match) return null;

  const minutes = Number(match[1]);
  const seconds = Number(match[2]);
  return minutes * 60 + seconds;
}
