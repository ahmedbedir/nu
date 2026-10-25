import Link from 'next/link';
import type { Locale } from '@/lib/i18n';
import type { Dictionary } from '@/messages/ar';

export function SiteFooter({ locale, dict }: { locale: Locale; dict: Dictionary }) {
  return (
    <footer className="mt-20 border-t border-neutral-200 bg-white">
      <div className="container-page grid gap-8 py-12 sm:grid-cols-2 lg:grid-cols-4">
        <div className="lg:col-span-2">
          <div className="flex items-center gap-2.5">
            <span className="grid h-8 w-8 place-items-center rounded-lg bg-brand-500 text-white">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                <path
                  d="M12 21s-7-4.35-7-10a4 4 0 0 1 7-2.65A4 4 0 0 1 19 11c0 5.65-7 10-7 10Z"
                  stroke="currentColor"
                  strokeWidth="1.8"
                  strokeLinejoin="round"
                />
              </svg>
            </span>
            <span className="text-sm font-bold text-navy">{dict.brand}</span>
          </div>
          <p className="mt-3 max-w-md text-sm leading-relaxed text-neutral-500">{dict.footer.disclaimer}</p>
        </div>

        <div>
          <h3 className="mb-3 text-xs font-bold uppercase tracking-wide text-neutral-400">
            {dict.nav.services}
          </h3>
          <ul className="space-y-2 text-sm text-neutral-600">
            <li>
              <Link href={`/${locale}/services`} className="hover:text-brand-600">
                {dict.nav.services}
              </Link>
            </li>
            <li>
              <Link href={`/${locale}#how`} className="hover:text-brand-600">
                {dict.nav.howItWorks}
              </Link>
            </li>
            <li>
              <Link href={`/${locale}/patients`} className="hover:text-brand-600">
                {dict.nav.patients}
              </Link>
            </li>
          </ul>
        </div>

        <div>
          <h3 className="mb-3 text-xs font-bold uppercase tracking-wide text-neutral-400">
            {dict.footer.contact}
          </h3>
          <ul className="space-y-2 text-sm text-neutral-600">
            <li>
              <a href="mailto:hello@homecare.eg" className="hover:text-brand-600">
                hello@homecare.eg
              </a>
            </li>
            <li className="text-neutral-400">{dict.footer.legal}</li>
          </ul>
        </div>
      </div>

      <div className="border-t border-neutral-100">
        <div className="container-page flex flex-col items-center justify-between gap-2 py-5 text-2xs text-neutral-400 sm:flex-row">
          <p>
            © {new Date().getFullYear()} {dict.brand}. {dict.footer.rights}
          </p>
          <p>{locale === 'ar' ? 'القاهرة، مصر' : 'Cairo, Egypt'}</p>
        </div>
      </div>
    </footer>
  );
}