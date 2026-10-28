import { describe, expect, it, vi } from 'vitest';

import { locales, namespaces } from '@/i18n/config';

const getRootLocale = vi.hoisted(() => vi.fn());
vi.mock('next/root-params', () => ({ locale: getRootLocale }));
vi.mock('next-intl/server', () => ({ getRequestConfig: (fn: unknown) => fn }));

const { default: getRequestConfig, loadLocaleMessages } = await import(
  '@/i18n/request'
);

describe('loadLocaleMessages', () => {
  it.each(locales)(
    'loads every configured namespace for locale "%s"',
    async (locale) => {
      const messages = await loadLocaleMessages(locale);

      for (const namespace of namespaces) {
        expect(messages[namespace]).toBeTypeOf('object');
      }
    },
  );
});

describe('request config', () => {
  it('resolves locale and messages for a supported locale', async () => {
    getRootLocale.mockResolvedValueOnce('en');

    const config = await getRequestConfig({
      requestLocale: Promise.resolve('en'),
    });

    expect(config.locale).toBe('en');
    expect(config.messages?.common).toBeTypeOf('object');
  });

  it('404s for an unsupported locale', async () => {
    getRootLocale.mockResolvedValueOnce('xx');

    await expect(
      getRequestConfig({ requestLocale: Promise.resolve('xx') }),
    ).rejects.toThrow();
  });
});
