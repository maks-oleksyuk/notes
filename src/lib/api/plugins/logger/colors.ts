const codes = {
  red: '\x1b[31m',
  green: '\x1b[32m',
  yellow: '\x1b[33m',
  cyan: '\x1b[36m',
  gray: '\x1b[90m',
  reset: '\x1b[0m',
};

type Color = keyof typeof codes;

// ANSI escapes only make sense in a real terminal. Non-TTY sinks (Docker, CI, browser devtools) render them as garbage,
// so colors are off unless stdout is a TTY and NO_COLOR is unset.
export function supportsColor(): boolean {
  return (
    typeof process !== 'undefined' &&
    !process.env.NO_COLOR &&
    Boolean(process.stdout?.isTTY)
  );
}

export function paint(text: string, color: Color, on: boolean): string {
  return on ? `${codes[color]}${text}${codes.reset}` : text;
}

export function colorizeStatus(status: number, on: boolean): string {
  if (status >= 200 && status < 300) return paint(String(status), 'green', on);
  if (status >= 300 && status < 400) return paint(String(status), 'cyan', on);
  if (status >= 400 && status < 500) return paint(String(status), 'yellow', on);
  return paint(String(status), 'red', on);
}

export function colorizeMethod(method: string, on: boolean): string {
  const m = method.toUpperCase();
  if (m === 'POST') return paint(m, 'green', on);
  if (m === 'GET') return paint(m, 'cyan', on);
  if (m === 'PUT' || m === 'PATCH') return paint(m, 'yellow', on);
  if (m === 'DELETE') return paint(m, 'red', on);
  return m;
}
