import { cookies } from 'next/headers';
import { getRequestConfig } from 'next-intl/server';

import { defaultLocale, locales, namespaces } from './config';

import type { Locale } from './config';

const LOCALE_COOKIE = 'NEXT_LOCALE';

async function loadLocaleMessages(locale: Locale) {
  const entries = await Promise.all(
    namespaces.map(async (namespace) => {
      const messages = await import(`../messages/${locale}/${namespace}.json`);
      return [namespace, messages.default] as const;
    }),
  );
  return Object.fromEntries(entries);
}

export default getRequestConfig(async () => {
  const cookieLocale = (await cookies()).get(LOCALE_COOKIE)?.value;
  const locale = locales.includes(cookieLocale as Locale)
    ? (cookieLocale as Locale)
    : defaultLocale;

  return {
    locale,
    messages: await loadLocaleMessages(locale),
  };
});
