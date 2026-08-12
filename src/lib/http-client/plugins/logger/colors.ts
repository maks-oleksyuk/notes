import { sharedEnv } from '@/lib/env/shared';

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
    !sharedEnv().NO_COLOR &&
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

// Browser devtools don't render raw ANSI (only Chromium does; Firefox/Safari
// print the escapes as garbage) — the portable browser format is `%c` + CSS.
// The logger keeps building messages with ANSI inline (one code path for every
// sink) and converts at the console boundary.
//
// The palette is the exact one Chrome DevTools itself use to render ANSI SGR
// codes (devtools-frontend: panels/console/consoleView.css, the
// `--console-color-*` custom properties) — so these %c logs are visually
// identical to server logs that Next forwards with their ANSI intact.
// `light-dark()` switches between the same per-theme values DevTools does.
const CSS_BY_ANSI_CODE: Record<string, string> = {
  '31': 'color:light-dark(#a00, rgb(237 78 76))', // red
  '32': 'color:light-dark(#0a0, rgb(1 200 1))', // green
  '33': 'color:light-dark(#a50, rgb(210 192 87))', // yellow
  '36': 'color:light-dark(#0aa, rgb(18 181 203))', // cyan
  '90': 'color:light-dark(#555, rgb(137 137 137))', // bright black (gray)
  '0': '', // reset
};

// biome-ignore lint/suspicious/noControlCharactersInRegex: matching ANSI escapes is the whole point
const ANSI_RE = /\x1b\[(?<code>\d+)m/gu;

/**
 * Rewrites ANSI escapes into a console `%c` format string plus its style
 * arguments: `console.log(text, ...styles)`. Messages without ANSI pass
 * through untouched (no `%`-escaping either — with zero style args the
 * console prints format directives literally, so `%%` would show as-is).
 */
export function ansiToConsoleFormat(message: string): {
  text: string;
  styles: string[];
} {
  if (!message.includes('\x1b[')) return { text: message, styles: [] };

  const styles: string[] = [];
  // Escape literal `%` first (a path like /search?q=%20 would otherwise eat
  // style arguments as format directives), then swap each ANSI code for `%c`.
  const text = message
    .replaceAll('%', '%%')
    .replace(ANSI_RE, (_match, code: string) => {
      styles.push(CSS_BY_ANSI_CODE[code] ?? '');
      return '%c';
    });
  return { text, styles };
}
