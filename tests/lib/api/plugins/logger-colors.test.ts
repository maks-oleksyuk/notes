import { afterEach, describe, expect, it, vi } from 'vitest';
import {
  colorizeMethod,
  colorizeStatus,
  paint,
  supportsColor,
} from '@/lib/api/plugins/logger/colors';

describe('paint', () => {
  it('wraps text in ANSI codes when on', () => {
    expect(paint('hi', 'red', true)).toBe('\x1b[31mhi\x1b[0m');
  });

  it('returns text unchanged when off', () => {
    expect(paint('hi', 'red', false)).toBe('hi');
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
