'use client';

import { SegmentedControl } from '@mantine/core';
import { useLocale, useTranslations } from 'next-intl';

import { localeNames, locales } from '@/i18n/config';
import { usePathname, useRouter } from '@/i18n/navigation';

import type { Locale } from '@/i18n/config';

export function LocaleSwitcher() {
  const locale = useLocale();
  const t = useTranslations();
  const router = useRouter();
  const pathname = usePathname();

  function handleChange(next: string) {
    router.replace(pathname, { locale: next });
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
