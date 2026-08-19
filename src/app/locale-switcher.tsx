'use client';

import { SegmentedControl } from '@mantine/core';
import { useRouter } from 'next/navigation';
import { useLocale, useTranslations } from 'next-intl';

import { localeNames, locales } from '@/i18n/config';

import type { Locale } from '@/i18n/config';

export function LocaleSwitcher() {
  const locale = useLocale();
  const t = useTranslations();
  const router = useRouter();

  function handleChange(next: string) {
    // biome-ignore lint/suspicious/noDocumentCookie: Cookie Store API not yet universally supported
    document.cookie = `NEXT_LOCALE=${next}; path=/; max-age=${60 * 60 * 24 * 365}; SameSite=Lax`;
    router.refresh();
  }

  return (
    <SegmentedControl
      aria-label={t('common.language')}
      value={locale}
      onChange={handleChange}
      data={locales.map((value: Locale) => ({
        value,
        label: localeNames[value],
      }))}
    />
  );
}
