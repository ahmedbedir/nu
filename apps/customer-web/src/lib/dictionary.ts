import { ar } from '@/messages/ar';
import { en } from '@/messages/en';
import type { Dictionary } from '@/messages/ar';
import type { Locale } from './i18n';

const dictionaries: Record<Locale, Dictionary> = { ar, en };

export function getDictionary(locale: Locale): Dictionary {
  return dictionaries[locale] ?? ar;
}