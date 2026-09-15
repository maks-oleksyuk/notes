import { describe, expect, it, vi } from 'vitest';

import { locales, namespaces } from '@/i18n/config';
import { loadLocaleMessages } from '@/i18n/request';

vi.mock('next/root-params', () => ({ locale: async () => 'en' }));

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
