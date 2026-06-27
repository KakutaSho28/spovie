import { useEffect, useState } from 'react';
import { formatTime, parseTimeInput } from '../utils/time';

type Props = {
  value: number;
  onChange: (seconds: number) => void;
  onValidityChange?: (valid: boolean) => void;
  ariaLabel: string;
};

export function TimeInput({
  value,
  onChange,
  onValidityChange,
  ariaLabel,
}: Props) {
  const [draft, setDraft] = useState(formatTime(value));
  const [focused, setFocused] = useState(false);

  useEffect(() => {
    if (!focused) {
      setDraft(formatTime(value));
    }
  }, [focused, value]);

  const handleChange = (next: string) => {
    setDraft(next);
    const parsed = parseTimeInput(next);
    const valid = parsed !== null;
    onValidityChange?.(valid);
    if (valid) {
      onChange(parsed);
    }
  };

  const handleBlur = () => {
    setFocused(false);
    const parsed = parseTimeInput(draft);
    if (parsed === null) {
      setDraft(formatTime(value));
      onValidityChange?.(true);
      return;
    }
    setDraft(formatTime(parsed));
  };

  return (
    <input
      className="time-input"
      type="text"
      inputMode="decimal"
      value={draft}
      onFocus={() => setFocused(true)}
      onChange={(e) => handleChange(e.target.value)}
      onBlur={handleBlur}
      placeholder="0:00"
      aria-label={ariaLabel}
    />
  );
}
