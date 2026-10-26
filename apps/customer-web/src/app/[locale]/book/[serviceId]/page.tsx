import { notFound } from 'next/navigation';
import { BookingWizard } from '@/components/booking-wizard';
import { api } from '@/lib/api';
import { getDictionary } from '@/lib/dictionary';
import { isLocale, type Locale } from '@/lib/i18n';

export const dynamic = 'force-dynamic';

export default async function BookPage({
  params,
}: {
  params: Promise<{ locale: string; serviceId: string }>;
}) {
  const { locale, serviceId } = await params;
  if (!isLocale(locale)) notFound();

  const services = await api.services();
  if (!services.ok) notFound();

  const service = services.data.data.find((item) => item.public_id === serviceId);
  if (!service) notFound();

  return <BookingWizard locale={locale as Locale} dict={getDictionary(locale as Locale)} service={service} />;
}