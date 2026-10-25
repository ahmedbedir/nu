import Link from 'next/link';
import { notFound } from 'next/navigation';
import { getDictionary } from '@/lib/dictionary';
import { formatMoney, isLocale, type Locale } from '@/lib/i18n';
import { api } from '@/lib/api';

export const dynamic = 'force-dynamic';

export async function generateMetadata({ params }: { params: Promise<{ locale: string }> }) {
  const { locale } = await params;
  return { title: isLocale(locale) && locale === 'ar' ? 'الخدمات' : 'Services' };
}

export default async function ServicesPage({ params }: { params: Promise<{ locale: string }> }) {
  const { locale } = await params;
  if (!isLocale(locale)) notFound();

  const l = locale as Locale;
  const t = getDictionary(l);
  const ar = l === 'ar';

  const res = await api.services();
  const services = res.ok ? res.data.data : [];

  return (
    <div className="container-page py-12">
      <header className="mx-auto max-w-2xl text-center">
        <h1 className="text-3xl font-extrabold tracking-tight text-navy">{t.services.title}</h1>
        <p className="mt-3 text-sm leading-relaxed text-neutral-600">{t.services.subtitle}</p>
      </header>

      {services.length === 0 ? (
        <div className="card mx-auto mt-10 max-w-lg p-10 text-center">
          <div className="mx-auto grid h-12 w-12 place-items-center rounded-full bg-neutral-100 text-neutral-400">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" aria-hidden="true">
              <path d="M4 7h16M4 12h16M4 17h10" stroke="currentColor" strokeWidth="1.8" strokeLinecap="round" />
            </svg>
          </div>
          <p className="mt-4 text-sm text-neutral-500">{t.services.empty}</p>
        </div>
      ) : (
        <ul className="mt-10 grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
          {services.map((s) => (
            <li key={s.public_id} className="card flex flex-col p-6 transition-shadow hover:shadow-lift">
              <div className="flex items-start justify-between gap-3">
                <h2 className="text-base font-bold leading-snug text-navy">
                  {ar ? s.name_ar : s.name_en}
                </h2>
                <span className={s.billing_mode === 'time' ? 'badge-neutral' : 'badge-success'}>
                  {s.billing_mode === 'time'
                    ? ar
                      ? 'بالوقت'
                      : 'Time'
                    : ar
                      ? 'سعر ثابت'
                      : 'Fixed'}
                </span>
              </div>

              <p className="mt-3 flex-1 text-sm leading-relaxed text-neutral-600">
                {ar ? s.description_ar : s.description_en}
              </p>

              <dl className="mt-5 space-y-2 border-t border-neutral-100 pt-4 text-xs text-neutral-500">
                <div className="flex items-center justify-between">
                  <dt>{t.services.duration}</dt>
                  <dd className="font-semibold text-neutral-700">
                    {s.default_duration_minutes} {t.services.minutes}
                  </dd>
                </div>
                {s.requires_medical_order && (
                  <div className="flex items-center justify-between">
                    <dt>{t.services.requiresOrder}</dt>
                    <dd className="font-semibold text-coral-600">{t.common.yes}</dd>
                  </div>
                )}
              </dl>

              <div className="mt-5 flex items-end justify-between gap-3">
                <div>
                  <span className="block text-2xs text-neutral-400">{t.services.from}</span>
                  <span className="text-lg font-extrabold text-navy">
                    {formatMoney(s.base_price, l)}
                  </span>
                </div>
                <Link href={`/${l}/login`} className="btn-primary !px-4 !py-2 !text-xs">
                  {t.services.book}
                </Link>
              </div>
            </li>
          ))}
        </ul>
      )}

      <p className="mx-auto mt-10 max-w-2xl rounded-xl border border-neutral-200 bg-white px-4 py-3 text-center text-2xs leading-relaxed text-neutral-400">
        {ar
          ? 'الأسعار المعروضة تقديرية وتُحدَّد نهائيًا بعد قبول الممرض وقبل تأكيدك. الخدمات تتطلب ممرضًا معتمدًا لهذا الإجراء تحديدًا.'
          : 'Prices shown are indicative; the final quote is set after a nurse accepts and before you confirm. Each service requires a nurse approved for that specific procedure.'}
      </p>
    </div>
  );
}