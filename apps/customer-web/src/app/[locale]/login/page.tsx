import { notFound } from 'next/navigation';
import { LoginForm } from '@/components/login-form';
import { getDictionary } from '@/lib/dictionary';
import { isLocale, type Locale } from '@/lib/i18n';

export async function generateMetadata({ params }: { params: Promise<{ locale: string }> }) {
  const { locale } = await params;
  return { title: isLocale(locale) && locale === 'ar' ? 'تسجيل الدخول' : 'Sign in' };
}

export default async function LoginPage({ params }: { params: Promise<{ locale: string }> }) {
  const { locale } = await params;
  if (!isLocale(locale)) notFound();

  const l = locale as Locale;
  const t = getDictionary(l);

  return (
    <div className="container-page flex justify-center py-14">
      <div className="w-full max-w-md">
        <div className="mb-8 text-center">
          <h1 className="text-2xl font-extrabold text-navy">{t.auth.title}</h1>
          <p className="mt-2 text-sm text-neutral-500">{t.auth.subtitle}</p>
        </div>

        <div className="card p-6 sm:p-8">
          <LoginForm locale={l} dict={t} />
        </div>

        <p className="mt-6 text-center text-2xs leading-relaxed text-neutral-400">
          {l === 'ar'
            ? 'بالمتابعة أنت توافق على شروط الاستخدام وسياسة الخصوصية. تُستخدم بياناتك لتقديم الخدمة فقط.'
            : 'By continuing you agree to the terms and privacy policy. Your data is used only to provide the service.'}
        </p>
      </div>
    </div>
  );
}