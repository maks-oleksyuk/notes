import { afterEach, describe, expect, it, vi } from 'vitest';

import {
  ansiToConsoleFormat,
  colorizeMethod,
  colorizeStatus,
  paint,
  supportsColor,
} from '@/lib/http-client/plugins/logger/colors';

describe('paint', () => {
  it('wraps text in ANSI codes when on', () => {
    expect(paint('hi', 'red', true)).toBe('\x1b[31mhi\x1b[0m');
  });

  it('returns text unchanged when off', () => {
    expect(paint('hi', 'red', false)).toBe('hi');
  });
});

describe('ansiToConsoleFormat', () => {
  it('converts each ANSI code to %c and collects the matching CSS styles', () => {
    const { text, styles } = ansiToConsoleFormat(
      `${paint('GET', 'cyan', true)} /posts ${paint('200', 'green', true)}`,
    );

    expect(text).toBe('%cGET%c /posts %c200%c');
    // The DevTools-native ANSI palette (see CSS_BY_ANSI_CODE in colors.ts).
    expect(styles).toEqual([
      'color:light-dark(#0aa, rgb(18 181 203))', // cyan
      '', // reset
      'color:light-dark(#0a0, rgb(1 200 1))', // green
      '', // reset
    ]);
  });

  it('passes a message without ANSI through untouched (no %-escaping)', () => {
    const { text, styles } = ansiToConsoleFormat('GET /search?q=%20test');

    expect(text).toBe('GET /search?q=%20test');
    expect(styles).toEqual([]);
  });

  it('escapes literal % so paths cannot eat the style arguments', () => {
    const { text, styles } = ansiToConsoleFormat(
      `${paint('GET', 'cyan', true)} /search?q=%20`,
    );

    expect(text).toBe('%cGET%c /search?q=%%20');
    expect(styles).toHaveLength(2);
  });

  it('maps an unknown ANSI code to an empty style instead of crashing', () => {
    // 35 = magenta, deliberately not in the palette.
    const { text, styles } = ansiToConsoleFormat('\x1b[35mhi\x1b[0m');

    expect(text).toBe('%chi%c');
    expect(styles).toEqual(['', '']);
  });
});

describe('colorizeStatus', () => {
  it.each([
    [200, 'green'],
    [204, 'green'],
    [301, 'cyan'],
    [404, 'yellow'],
    [500, 'red'],
  ])('colors status %i as %s', (status, color) => {
    expect(colorizeStatus(status, true)).toBe(
      paint(String(status), color as never, true),
    );
  });

  it('returns plain text when colors are off', () => {
    expect(colorizeStatus(200, false)).toBe('200');
  });
});

describe('colorizeMethod', () => {
  it.each([
    ['POST', 'green'],
    ['GET', 'cyan'],
    ['PUT', 'yellow'],
    ['PATCH', 'yellow'],
    ['DELETE', 'red'],
  ])('colors %s as %s', (method, color) => {
    expect(colorizeMethod(method, true)).toBe(
      paint(method, color as never, true),
    );
  });

  it('uppercases and passes through unknown methods uncolored', () => {
    expect(colorizeMethod('options', true)).toBe('OPTIONS');
  });
});

describe('supportsColor', () => {
  afterEach(() => {
    vi.unstubAllEnvs();
    vi.unstubAllGlobals();
  });

  it('is false when NO_COLOR is set', () => {
    vi.stubEnv('NO_COLOR', '1');
    expect(supportsColor()).toBe(false);
  });

  it('is false when stdout is not a TTY', () => {
    vi.unstubAllEnvs();
    const original = process.stdout.isTTY;
    Object.defineProperty(process.stdout, 'isTTY', {
      value: false,
      configurable: true,
    });
    expect(supportsColor()).toBe(false);
    Object.defineProperty(process.stdout, 'isTTY', {
      value: original,
      configurable: true,
    });
  });

  it('is true in a real TTY with NO_COLOR unset', () => {
    vi.unstubAllEnvs();
    const original = process.stdout.isTTY;
    Object.defineProperty(process.stdout, 'isTTY', {
      value: true,
      configurable: true,
    });
    expect(supportsColor()).toBe(true);
    Object.defineProperty(process.stdout, 'isTTY', {
      value: original,
      configurable: true,
    });
  });
});
