import { notFound } from 'next/navigation';
// biome-ignore lint/correctness/noUnresolvedImports: next/root-params is a compiler-generated module (types emitted at dev/build time), biome can't resolve it statically
import { locale as getRootLocale } from 'next/root-params';
import { hasLocale } from 'next-intl';
import { getRequestConfig } from 'next-intl/server';

import { namespaces } from './config';
import { routing } from './routing';

import type { Locale } from './config';

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
  const paramValue = await getRootLocale();
  if (!hasLocale(routing.locales, paramValue)) notFound();

  return {
    locale: paramValue,
    messages: await loadLocaleMessages(paramValue),
  };
});
