export const locales = ['en', 'uk'] as const;
export type Locale = (typeof locales)[number];

export const defaultLocale: Locale = 'en';

export const localeNames: Record<Locale, string> = {
  en: 'English',
  uk: 'Українська',
};

// New translation domain? Add the filename (without .json) here.
export const namespaces = ['common'] as const;
