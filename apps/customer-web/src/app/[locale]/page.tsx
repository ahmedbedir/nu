import Link from 'next/link';
import { notFound } from 'next/navigation';
import { getDictionary } from '@/lib/dictionary';
import { isLocale, type Locale } from '@/lib/i18n';

export default async function HomePage({ params }: { params: Promise<{ locale: string }> }) {
  const { locale } = await params;
  if (!isLocale(locale)) notFound();

  const l = locale as Locale;
  const t = getDictionary(l);
  const ar = l === 'ar';

  return (
    <>
      {/* ---------- Hero ---------- */}
      <section className="relative overflow-hidden border-b border-neutral-200 bg-gradient-to-b from-brand-50/70 via-white to-white">
        <div
          className="pointer-events-none absolute -top-40 end-[-10%] h-[420px] w-[420px] rounded-full bg-brand-100/50 blur-3xl"
          aria-hidden="true"
        />
        <div className="container-page relative grid gap-12 py-16 lg:grid-cols-[1.05fr_0.95fr] lg:items-center lg:py-24">
          <div className="animate-fade-up">
            <span className="chip border-brand-200 bg-white text-brand-700">
              <span className="h-1.5 w-1.5 rounded-full bg-success-500" />
              {t.hero.eyebrow}
            </span>

            <h1 className="mt-5 text-balance text-4xl font-extrabold leading-[1.15] tracking-tight text-navy sm:text-5xl">
              {t.hero.title}
            </h1>

            <p className="mt-5 max-w-xl text-pretty text-base leading-relaxed text-neutral-600 sm:text-lg">
              {t.hero.subtitle}
            </p>

            <div className="mt-8 flex flex-wrap items-center gap-3">
              <Link href={`/${l}/login`} className="btn-primary px-6 py-3 text-base">
                {t.hero.ctaPrimary}
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" aria-hidden="true" className="rtl:rotate-180">
                  <path d="M5 12h14M13 6l6 6-6 6" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" />
                </svg>
              </Link>
              <Link href={`/${l}/services`} className="btn-secondary px-6 py-3 text-base">
                {t.hero.ctaSecondary}
              </Link>
            </div>

            <ul className="mt-10 grid gap-3 text-sm text-neutral-600 sm:grid-cols-3">
              {[t.hero.trust1, t.hero.trust2, t.hero.trust3].map((item) => (
                <li key={item} className="flex items-start gap-2">
                  <span className="mt-0.5 grid h-5 w-5 shrink-0 place-items-center rounded-full bg-success-50 text-success-600">
                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                      <path d="m5 13 4 4L19 7" stroke="currentColor" strokeWidth="2.5" strokeLinecap="round" strokeLinejoin="round" />
                    </svg>
                  </span>
                  <span className="leading-snug">{item}</span>
                </li>
              ))}
            </ul>
          </div>

          {/* Verification card — the product promise, shown concretely. */}
          <div className="relative animate-fade-up lg:justify-self-end" style={{ animationDelay: '90ms' }}>
            <div className="card w-full max-w-md p-6">
              <div className="flex items-center justify-between">
                <h2 className="text-sm font-bold text-navy">
                  {ar ? 'ملف الممرض' : 'Professional profile'}
                </h2>
                <span className="badge-success">
                  <svg width="11" height="11" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                    <path d="m5 13 4 4L19 7" stroke="currentColor" strokeWidth="3" strokeLinecap="round" strokeLinejoin="round" />
                  </svg>
                  {ar ? 'موثّق' : 'Verified'}
                </span>
              </div>

              <div className="mt-5 flex items-center gap-4">
                <div className="grid h-14 w-14 place-items-center rounded-2xl bg-brand-100 text-lg font-bold text-brand-700">
                  {ar ? 'ن.م' : 'NM'}
                </div>
                <div>
                  <p className="font-semibold text-navy">{ar ? 'نورا م.' : 'Nora M.'}</p>
                  <p className="text-xs text-neutral-500">
                    {ar ? 'تمريض منزلي · 8 سنوات (معلنة)' : 'Home nursing · 8 yrs (self-declared)'}
                  </p>
                </div>
              </div>

              <dl className="mt-5 space-y-3 border-t border-neutral-100 pt-5 text-sm">
                <Row label={ar ? 'ترخيص مزاولة المهنة' : 'Practice licence'} value={ar ? 'ساري' : 'Valid'} ok />
                <Row label={ar ? 'اعتماد الخدمة' : 'Service approval'} value={ar ? 'غيار جروح' : 'Wound dressing'} ok />
                <Row label={ar ? 'نطاق التغطية' : 'Coverage area'} value={ar ? 'المعادي · 10 كم' : 'Maadi · 10 km'} />
              </dl>

              <div className="mt-5 rounded-xl bg-neutral-50 p-3.5 text-2xs leading-relaxed text-neutral-500">
                {ar
                  ? 'الترخيص الموثّق شرط للعمل، والاعتماد على مستوى كل إجراء منفصل عن حالة الحساب.'
                  : 'A verified licence is required to operate; per-procedure approval is separate from account status.'}
              </div>
            </div>
          </div>
        </div>
      </section>

      {/* ---------- How it works ---------- */}
      <section id="how" className="container-page py-20">
        <div className="mx-auto max-w-2xl text-center">
          <h2 className="text-3xl font-extrabold tracking-tight text-navy">{t.how.title}</h2>
          <div className="mx-auto mt-4 h-1 w-14 rounded-full bg-coral-500" />
        </div>

        <ol className="mt-12 grid gap-5 sm:grid-cols-2 lg:grid-cols-4">
          {[
            [t.how.s1Title, t.how.s1Body],
            [t.how.s2Title, t.how.s2Body],
            [t.how.s3Title, t.how.s3Body],
            [t.how.s4Title, t.how.s4Body],
          ].map(([title, body], i) => (
            <li key={title} className="card relative p-6 pt-8">
              <span className="absolute -top-3 start-6 grid h-7 w-7 place-items-center rounded-full bg-navy text-xs font-bold text-white">
                {i + 1}
              </span>
              <h3 className="text-base font-bold text-navy">{title}</h3>
              <p className="mt-2 text-sm leading-relaxed text-neutral-600">{body}</p>
            </li>
          ))}
        </ol>
      </section>

      {/* ---------- Safety band ---------- */}
      <section className="bg-navy">
        <div className="container-page grid gap-8 py-14 lg:grid-cols-[1fr_auto] lg:items-center">
          <div>
            <div className="flex items-center gap-2 text-coral-300">
              <span className="relative grid h-9 w-9 place-items-center rounded-full bg-coral-500/15">
                <span className="absolute inset-0 animate-pulse-ring rounded-full" />
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                  <path d="M12 3v18M3 12h18" stroke="currentColor" strokeWidth="3" strokeLinecap="round" />
                </svg>
              </span>
              <span className="text-xs font-bold uppercase tracking-wider">{ar ? 'SOS' : 'SOS'}</span>
            </div>

            <h2 className="mt-4 text-2xl font-extrabold text-white sm:text-3xl">{t.safety.title}</h2>
            <p className="mt-3 max-w-2xl text-sm leading-relaxed text-brand-100">{t.safety.body}</p>
            <p className="mt-4 max-w-2xl rounded-lg border border-coral-500/30 bg-coral-500/10 px-4 py-2.5 text-xs font-medium text-coral-100">
              {t.safety.note}
            </p>
          </div>

          <div className="hidden shrink-0 lg:block">
            <div className="grid h-40 w-40 place-items-center rounded-full border border-white/10 bg-white/5">
              <div className="grid h-28 w-28 place-items-center rounded-full bg-coral-500 text-center text-xs font-bold leading-tight text-white shadow-lift">
                {ar ? 'ضغط مطوّل\n3 ثوانٍ' : 'Hold 3s'}
              </div>
            </div>
          </div>
        </div>
      </section>

      {/* ---------- Closing CTA ---------- */}
      <section className="container-page py-20">
        <div className="card relative overflow-hidden px-6 py-12 text-center sm:px-12">
          <div
            className="pointer-events-none absolute inset-x-0 -top-24 mx-auto h-48 w-[520px] rounded-full bg-brand-100/60 blur-3xl"
            aria-hidden="true"
          />
          <h2 className="relative text-2xl font-extrabold text-navy sm:text-3xl">
            {ar ? 'ابدأ بملف المريض أولًا' : 'Start with the patient profile'}
          </h2>
          <p className="relative mx-auto mt-3 max-w-xl text-sm leading-relaxed text-neutral-600">
            {ar
              ? 'أنشئ ملف المريض، ثم اختر الخدمة والموعد. يمكنك إضافة أفراد العائلة وتفويض من ترغب بصلاحيات محددة.'
              : 'Create the patient profile, then choose the service and time. You can add family members and delegate scoped access.'}
          </p>
          <div className="relative mt-7 flex flex-wrap justify-center gap-3">
            <Link href={`/${l}/patients`} className="btn-primary px-6 py-3">
              {t.patients.title}
            </Link>
            <Link href={`/${l}/services`} className="btn-secondary px-6 py-3">
              {t.hero.ctaSecondary}
            </Link>
          </div>
        </div>
      </section>
    </>
  );
}

function Row({ label, value, ok = false }: { label: string; value: string; ok?: boolean }) {
  return (
    <div className="flex items-center justify-between gap-3">
      <dt className="text-neutral-500">{label}</dt>
      <dd className={`font-semibold ${ok ? 'text-success-600' : 'text-navy'}`}>{value}</dd>
    </div>
  );
}