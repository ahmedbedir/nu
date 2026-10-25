'use client';

import Link from 'next/link';
import { usePathname } from 'next/navigation';
import { useEffect, useState } from 'react';
import { getToken, setToken, api } from '@/lib/api';
import { otherLocale, type Locale } from '@/lib/i18n';
import type { Dictionary } from '@/messages/ar';

export function SiteHeader({ locale, dict }: { locale: Locale; dict: Dictionary }) {
  const pathname = usePathname();
  const [signedIn, setSignedIn] = useState(false);

  useEffect(() => {
    setSignedIn(Boolean(getToken()));
  }, [pathname]);

  const other = otherLocale(locale);
  const swapped = pathname.replace(`/${locale}`, `/${other}`) || `/${other}`;
  const isRtl = locale === 'ar';

  async function signOut() {
    await api.logout();
    setToken(null);
    setSignedIn(false);
    window.location.href = `/${locale}`;
  }

  return (
    <header className="sticky top-0 z-40 border-b border-neutral-200/70 bg-white/85 backdrop-blur">
      <div className="container-page flex h-16 items-center gap-4">
        <Link href={`/${locale}`} className="flex items-center gap-2.5" aria-label={dict.brand}>
          <span className="grid h-9 w-9 place-items-center rounded-xl bg-brand-500 text-white shadow-sm">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" aria-hidden="true">
              <path
                d="M12 21s-7-4.35-7-10a4 4 0 0 1 7-2.65A4 4 0 0 1 19 11c0 5.65-7 10-7 10Z"
                stroke="currentColor"
                strokeWidth="1.7"
                strokeLinejoin="round"
              />
              <path d="M12 8.5v5M9.5 11h5" stroke="currentColor" strokeWidth="1.7" strokeLinecap="round" />
            </svg>
          </span>
          <span className="flex flex-col leading-tight">
            <span className="text-[15px] font-bold text-navy">{dict.brand}</span>
            <span className="text-2xs text-neutral-500">{dict.brandTagline}</span>
          </span>
        </Link>

        <nav className="ms-auto hidden items-center gap-1 md:flex" aria-label="main">
          <Link
            href={`/${locale}/services`}
            className="rounded-lg px-3 py-2 text-sm font-medium text-neutral-600 transition-colors hover:bg-brand-50 hover:text-brand-700"
          >
            {dict.nav.services}
          </Link>
          <Link
            href={`/${locale}#how`}
            className="rounded-lg px-3 py-2 text-sm font-medium text-neutral-600 transition-colors hover:bg-brand-50 hover:text-brand-700"
          >
            {dict.nav.howItWorks}
          </Link>
          {signedIn && (
            <Link
              href={`/${locale}/patients`}
              className="rounded-lg px-3 py-2 text-sm font-medium text-neutral-600 transition-colors hover:bg-brand-50 hover:text-brand-700"
            >
              {dict.nav.patients}
            </Link>
          )}
        </nav>

        <div className="ms-auto flex items-center gap-2 md:ms-0">
          <Link
            href={swapped}
            hrefLang={other}
            className="rounded-lg border border-neutral-200 px-3 py-1.5 text-xs font-semibold text-neutral-600 transition-colors hover:border-brand-200 hover:text-brand-700"
          >
            {dict.common.language}
          </Link>

          {signedIn ? (
            <button type="button" onClick={signOut} className="btn-secondary !px-3.5 !py-2 !text-xs">
              {dict.nav.logout}
            </button>
          ) : (
            <Link href={`/${locale}/login`} className="btn-primary !px-4 !py-2 !text-xs">
              {dict.nav.login}
            </Link>
          )}
        </div>
      </div>

      <div className="border-t border-coral-100 bg-coral-50/70">
        <p className="container-page py-1.5 text-center text-2xs font-medium text-coral-700">
          {dict.footer.notLaunch}
        </p>
      </div>
      <span className="sr-only">{isRtl ? 'الاتجاه من اليمين لليسار' : 'Left-to-right layout'}</span>
    </header>
  );
}