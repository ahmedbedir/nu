import { notFound } from 'next/navigation';
import { PatientsManager } from '@/components/patients-manager';
import { getDictionary } from '@/lib/dictionary';
import { isLocale, type Locale } from '@/lib/i18n';

export async function generateMetadata({ params }: { params: Promise<{ locale: string }> }) {
  const { locale } = await params;
  return { title: isLocale(locale) && locale === 'ar' ? 'ملفات المرضى' : 'Patients' };
}

export default async function PatientsPage({ params }: { params: Promise<{ locale: string }> }) {
  const { locale } = await params;
  if (!isLocale(locale)) notFound();

  const l = locale as Locale;
  const t = getDictionary(l);

  return (
    <div className="container-page py-12">
      <header className="mb-8">
        <h1 className="text-3xl font-extrabold tracking-tight text-navy">{t.patients.title}</h1>
        <p className="mt-2 max-w-2xl text-sm leading-relaxed text-neutral-600">{t.patients.subtitle}</p>
      </header>

      <PatientsManager locale={l} dict={t} />
    </div>
  );
}