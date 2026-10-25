import type { Metadata } from 'next';
import { notFound } from 'next/navigation';
import { SiteHeader } from '@/components/site-header';
import { SiteFooter } from '@/components/site-footer';
import { getDictionary } from '@/lib/dictionary';
import { dir, isLocale, type Locale } from '@/lib/i18n';

export function generateStaticParams() {
  return [{ locale: 'ar' }, { locale: 'en' }];
}

export async function generateMetadata({
  params,
}: {
  params: Promise<{ locale: string }>;
}): Promise<Metadata> {
  const { locale } = await params;
  const l = isLocale(locale) ? locale : 'ar';

  return {
    title: l === 'ar' ? 'رعاية تمريضية منزلية موثوقة' : 'Trusted home nursing care',
  };
}

export default async function LocaleLayout({
  children,
  params,
}: {
  children: React.ReactNode;
  params: Promise<{ locale: string }>;
}) {
  const { locale } = await params;

  if (!isLocale(locale)) notFound();

  const l = locale as Locale;
  const dict = getDictionary(l);

  return (
    <html lang={l} dir={dir(l)} suppressHydrationWarning>
      <body className="flex min-h-dvh flex-col">
        <a href="#main" className="skip-link">
          {l === 'ar' ? 'تجاوز إلى المحتوى' : 'Skip to content'}
        </a>

        <SiteHeader locale={l} dict={dict} />

        <main id="main" className="flex-1">
          {children}
        </main>

        <SiteFooter locale={l} dict={dict} />
      </body>
    </html>
  );
}