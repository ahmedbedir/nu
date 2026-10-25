export type Locale = 'ar' | 'en';

export const locales: Locale[] = ['ar', 'en'];
export const defaultLocale: Locale = 'ar';

export const dir = (locale: Locale): 'rtl' | 'ltr' => (locale === 'ar' ? 'rtl' : 'ltr');

export function isLocale(value: string | undefined): value is Locale {
  return value === 'ar' || value === 'en';
}

export function otherLocale(locale: Locale): Locale {
  return locale === 'ar' ? 'en' : 'ar';
}

/** EGP formatting with Arabic-friendly digits preserved in both locales. */
export function formatMoney(amount: number | string, locale: Locale): string {
  const value = typeof amount === 'string' ? Number(amount) : amount;
  const formatted = new Intl.NumberFormat(locale === 'ar' ? 'ar-EG' : 'en-EG', {
    minimumFractionDigits: 0,
    maximumFractionDigits: 2,
  }).format(value);

  return locale === 'ar' ? `${formatted} ج.م` : `EGP ${formatted}`;
}