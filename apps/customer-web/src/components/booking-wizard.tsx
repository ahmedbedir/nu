'use client';

import Link from 'next/link';
import { useEffect, useMemo, useState } from 'react';
import { api, getToken, type AddressItem, type PatientItem, type ServiceItem } from '@/lib/api';
import type { Locale } from '@/lib/i18n';
import type { Dictionary } from '@/messages/ar';

const labels = {
  ar: {
    title: 'احجز خدمة منزلية', subtitle: 'نبدأ بالبيانات اللازمة للسلامة، ثم نبحث عن ممارس مؤهل.',
    patient: 'اختر المريض', address: 'عنوان الزيارة', gender: 'جنس الممارس', female: 'ممرضة', male: 'ممرض',
    addAddress: 'إضافة عنوان', addressLine: 'العنوان بالتفصيل', label: 'اسم العنوان', latitude: 'خط العرض', longitude: 'خط الطول',
    next: 'التالي', back: 'رجوع', save: 'حفظ العنوان', search: 'ابدأ البحث', searching: 'جارٍ البحث عن ممارس مؤهل…',
    quote: 'راجع السعر', confirm: 'تأكيد الحجز', confirmed: 'تم تأكيد الحجز', noPatients: 'أضف ملف مريض أولًا', noAddresses: 'أضف عنوان زيارة أولًا',
    login: 'تسجيل الدخول', unknown: 'لا توجد بيانات', redFlag: 'لا يمكن متابعة الحجز العادي لهذه الحالة. يرجى اتباع إرشادات الطوارئ.',
    info: 'سيتم استكمال بيانات السلامة قبل تنفيذ الإجراء.', review: 'الطلب يحتاج مراجعة مؤهلة قبل التنفيذ.', noMatch: 'لم نجد ممارسًا مؤهلًا قريبًا الآن.',
    price: 'الإجمالي', addressVerified: 'عنوان مؤكد', select: 'اختر',
    searchingBody: 'نستبعد أي ممارس غير مرخص أو غير مؤهل لهذه الخدمة.',
    waitingTitle: 'بانتظار قبول ممارس مؤهل',
    waitingBody: 'يصل الطلب فقط للممارسين المؤهلين في نطاق عنوان الزيارة. سنعرض السعر النهائي بعد القبول.',
  },
  en: {
    title: 'Book home care', subtitle: 'We collect the safety details first, then search for a qualified professional.',
    patient: 'Choose patient', address: 'Visit address', gender: 'Professional gender', female: 'Female nurse', male: 'Male nurse',
    addAddress: 'Add address', addressLine: 'Detailed address', label: 'Address label', latitude: 'Latitude', longitude: 'Longitude',
    next: 'Continue', back: 'Back', save: 'Save address', search: 'Start search', searching: 'Searching for a qualified professional…',
    quote: 'Review price', confirm: 'Confirm booking', confirmed: 'Booking confirmed', noPatients: 'Add a patient profile first', noAddresses: 'Add a visit address first',
    login: 'Sign in', unknown: 'No data', redFlag: 'This case cannot continue through normal booking. Follow emergency guidance.',
    info: 'Safety details will be completed before the procedure is executed.', review: 'This request needs qualified review before execution.', noMatch: 'No qualified professional was found nearby right now.',
    price: 'Total', addressVerified: 'Verified address', select: 'Choose',
    searchingBody: 'Unlicensed or service-ineligible professionals are excluded.',
    waitingTitle: 'Waiting for a qualified professional',
    waitingBody: 'Only qualified professionals in the visit area receive the request. The final quote appears after acceptance.',
  },
} as const;

type Stage = 'patient' | 'address' | 'details' | 'search' | 'waiting' | 'quote' | 'confirmed';

export function BookingWizard({ locale, dict, service }: { locale: Locale; dict: Dictionary; service: ServiceItem }) {
  const t = labels[locale];
  const ar = locale === 'ar';
  const [stage, setStage] = useState<Stage>('patient');
  const [patients, setPatients] = useState<PatientItem[]>([]);
  const [addresses, setAddresses] = useState<AddressItem[]>([]);
  const [patientId, setPatientId] = useState('');
  const [addressId, setAddressId] = useState('');
  const [gender, setGender] = useState<'female' | 'male'>('female');
  const [bookingId, setBookingId] = useState('');
  const [quote, setQuote] = useState<{ total: string; currency: string; lines: any[] } | null>(null);
  const [error, setError] = useState<{ message: string; code?: string; details?: any } | null>(null);
  const [busy, setBusy] = useState(false);
  const [showAddress, setShowAddress] = useState(false);
  const [addressForm, setAddressForm] = useState({ label: '', address_line: '', latitude: '30.0444', longitude: '31.2357' });

  useEffect(() => {
    if (!getToken()) return;
    void loadPatients();
  }, []);

  async function loadPatients() {
    const res = await api.patients();
    if (res.ok) {
      setPatients(res.data.data);
      if (res.data.data[0]) {
        setPatientId(res.data.data[0].public_id);
        void loadAddresses(res.data.data[0].public_id);
      }
    }
  }

  async function loadAddresses(id: string) {
    const res = await api.addresses(id);
    if (res.ok) {
      setAddresses(res.data.data);
      if (res.data.data[0]) setAddressId(res.data.data[0].public_id);
    }
  }

  function selectPatient(id: string) {
    setPatientId(id);
    setAddressId('');
    void loadAddresses(id);
  }

  async function saveAddress(e: React.FormEvent) {
    e.preventDefault();
    if (!patientId) return;
    setBusy(true); setError(null);
    const res = await api.createAddress(patientId, {
      label: addressForm.label || undefined,
      address_line: addressForm.address_line,
      latitude: Number(addressForm.latitude), longitude: Number(addressForm.longitude), is_default: addresses.length === 0,
    });
    setBusy(false);
    if (!res.ok) { setError({ message: res.message }); return; }
    setAddresses((old) => [...old, res.data.address]);
    setAddressId(res.data.address.public_id);
    setShowAddress(false);
  }

  async function createBooking() {
    setBusy(true); setError(null); setStage('search');
    const result = await api.createBooking({
      patient_public_id: patientId, address_public_id: addressId, service_public_id: service.public_id, required_gender: gender,
    });
    if (!result.ok) { setBusy(false); setStage('details'); setError(result); return; }
    setBookingId(result.data.booking.public_id);
    const search = await api.searchBooking(result.data.booking.public_id);
    if (!search.ok) { setBusy(false); setStage('details'); setError(search); return; }
    if (search.data.booking.state === 'no_match') { setBusy(false); setStage('details'); setError({ message: t.noMatch, code: 'no_match' }); return; }

    // A customer cannot see a final quote until a qualified provider accepts.
    // The real product will replace this poll with the authenticated realtime stream.
    setStage('waiting');
    for (let attempt = 0; attempt < 20; attempt += 1) {
      await new Promise((resolve) => setTimeout(resolve, 3000));
      const current = await api.booking(result.data.booking.public_id);
      if (!current.ok) continue;
      if (current.data.booking.state === 'awaiting_customer_confirmation') break;
      if (['no_match', 'cancelled_customer', 'customer_confirmation_expired'].includes(current.data.booking.state)) {
        setBusy(false); setStage('details'); setError({ message: t.noMatch, code: current.data.booking.state }); return;
      }
    }

    const quoteResult = await api.quoteBooking(result.data.booking.public_id);
    setBusy(false);
    if (!quoteResult.ok) { setStage('details'); setError(quoteResult); return; }
    setQuote(quoteResult.data.quote);
    setStage('quote');
  }

  async function confirm() {
    if (!bookingId || !quote) return;
    setBusy(true); setError(null);
    const result = await api.confirmBooking(bookingId, (quote as any).version);
    setBusy(false);
    if (!result.ok) { setError(result); return; }
    setStage('confirmed');
  }

  const selectedPatient = useMemo(() => patients.find((p) => p.public_id === patientId), [patients, patientId]);
  const selectedAddress = useMemo(() => addresses.find((a) => a.public_id === addressId), [addresses, addressId]);

  if (!getToken()) {
    return <div className="container-page py-16"><div className="card mx-auto max-w-lg p-10 text-center"><h1 className="text-xl font-bold text-navy">{t.login}</h1><p className="mt-3 text-sm text-neutral-500">{ar ? 'سجّل الدخول لإنشاء حجز.' : 'Sign in to create a booking.'}</p><Link href={`/${locale}/login`} className="btn-primary mt-6">{t.login}</Link></div></div>;
  }

  return (
    <div className="container-page max-w-4xl py-10">
      <div className="mb-8 flex items-start justify-between gap-4">
        <div><Link href={`/${locale}/services`} className="text-xs font-semibold text-brand-600">← {dict.common.back}</Link><h1 className="mt-4 text-3xl font-extrabold text-navy">{t.title}</h1><p className="mt-2 text-sm text-neutral-600">{t.subtitle}</p></div>
        <span className="chip hidden border-brand-200 bg-brand-50 text-brand-700 sm:inline-flex">{ar ? service.name_ar : service.name_en}</span>
      </div>

      <div className="mb-8 flex items-center gap-2 overflow-x-auto">
        {(['patient', 'address', 'details', 'search', 'quote'] as Stage[]).map((key, i) => <span key={key} className={`h-1.5 min-w-12 rounded-full ${stage === key || (['search','quote','confirmed'].includes(stage) && i < 3) ? 'bg-brand-500' : 'bg-neutral-200'}`} />)}
      </div>

      {error && <div role="alert" className={`mb-6 rounded-xl border px-4 py-3 text-sm ${error.code === 'clinical_blocked' ? 'border-coral-200 bg-coral-50 text-coral-700' : 'border-neutral-200 bg-white text-neutral-700'}`}><strong>{error.message}</strong>{error.details?.guidance && <p className="mt-1 text-xs">{error.details.guidance}</p>}{error.details?.missing?.length > 0 && <p className="mt-1 text-xs">{t.info}</p>}</div>}

      {stage === 'patient' && <section className="card p-6"><h2 className="text-lg font-bold text-navy">{t.patient}</h2>{patients.length === 0 ? <div className="py-8 text-center text-sm text-neutral-500">{t.noPatients}<Link href={`/${locale}/patients`} className="mx-2 font-semibold text-brand-600">{dict.patients.add}</Link></div> : <><div className="mt-5 grid gap-3 sm:grid-cols-2">{patients.map((p) => <button key={p.public_id} type="button" onClick={() => selectPatient(p.public_id)} className={`rounded-xl border p-4 text-start ${patientId === p.public_id ? 'border-brand-500 bg-brand-50' : 'border-neutral-200 bg-white'}`}><strong className="text-navy">{p.full_name}</strong><span className="mt-1 block text-xs text-neutral-500">{p.age ?? '—'} {dict.patients.years}</span></button>)}</div><button type="button" disabled={!patientId} onClick={() => setStage('address')} className="btn-primary mt-6">{t.next}</button></>}</section>}

      {stage === 'address' && <section className="card p-6"><h2 className="text-lg font-bold text-navy">{t.address}</h2><p className="mt-2 text-sm text-neutral-500">{ar ? 'هذا هو مكان وجود المريض، وليس موقع جهاز الحجز.' : 'This is where the patient is located, not the booking device position.'}</p><div className="mt-5 grid gap-3">{addresses.map((a) => <button key={a.public_id} type="button" onClick={() => setAddressId(a.public_id)} className={`flex items-center justify-between rounded-xl border p-4 text-start ${addressId === a.public_id ? 'border-brand-500 bg-brand-50' : 'border-neutral-200'}`}><span><strong className="text-navy">{a.label || t.address}</strong><span className="mt-1 block text-xs text-neutral-500">{a.address_line}</span></span><span className="badge-success">{t.addressVerified}</span></button>)}</div>{showAddress ? <form onSubmit={saveAddress} className="mt-5 grid gap-3 rounded-xl bg-neutral-50 p-4"><input required className="field" placeholder={t.label} value={addressForm.label} onChange={(e) => setAddressForm({ ...addressForm, label: e.target.value })} /><input required className="field" placeholder={t.addressLine} value={addressForm.address_line} onChange={(e) => setAddressForm({ ...addressForm, address_line: e.target.value })} /><div className="grid grid-cols-2 gap-3"><input required className="field" dir="ltr" placeholder={t.latitude} value={addressForm.latitude} onChange={(e) => setAddressForm({ ...addressForm, latitude: e.target.value })} /><input required className="field" dir="ltr" placeholder={t.longitude} value={addressForm.longitude} onChange={(e) => setAddressForm({ ...addressForm, longitude: e.target.value })} /></div><button type="submit" disabled={busy} className="btn-primary">{busy ? dict.common.loading : t.save}</button></form> : <button type="button" onClick={() => setShowAddress(true)} className="btn-secondary mt-5">+ {t.addAddress}</button>}<div className="mt-6 flex gap-3"><button type="button" onClick={() => setStage('patient')} className="btn-secondary">{t.back}</button><button type="button" disabled={!addressId} onClick={() => setStage('details')} className="btn-primary">{t.next}</button></div></section>}

      {stage === 'details' && <section className="card p-6"><h2 className="text-lg font-bold text-navy">{ar ? 'تفاصيل الطلب' : 'Request details'}</h2><div className="mt-5"><label className="label">{t.gender}</label><div className="grid gap-3 sm:grid-cols-2"><button type="button" onClick={() => setGender('female')} className={`rounded-xl border p-4 text-start ${gender === 'female' ? 'border-brand-500 bg-brand-50' : 'border-neutral-200'}`}>{t.female}</button><button type="button" onClick={() => setGender('male')} className={`rounded-xl border p-4 text-start ${gender === 'male' ? 'border-brand-500 bg-brand-50' : 'border-neutral-200'}`}>{t.male}</button></div></div><div className="mt-6 rounded-xl bg-neutral-50 p-4 text-sm text-neutral-600">{ar ? 'سيتم إرسال الطلب فقط لممارسين مؤهلين لهذه الخدمة وفي نطاق العنوان.' : 'The request will only reach professionals qualified for this service and area.'}</div><div className="mt-6 flex gap-3"><button type="button" onClick={() => setStage('address')} className="btn-secondary">{t.back}</button><button type="button" onClick={createBooking} className="btn-primary">{t.search}</button></div></section>}

      {(stage === 'search' || stage === 'waiting') && (
        <section className="card p-12 text-center">
          <div className="mx-auto h-12 w-12 animate-pulse rounded-full bg-brand-100" />
          <h2 className="mt-5 text-lg font-bold text-navy">{stage === 'search' ? t.searching : t.waitingTitle}</h2>
          <p className="mt-2 text-sm text-neutral-500">
            {stage === 'search' ? t.searchingBody : t.waitingBody}
          </p>
          {stage === 'waiting' && (
            <button type="button" onClick={() => setStage('details')} className="btn-secondary mt-6">
              {t.back}
            </button>
          )}
        </section>
      )}

      {stage === 'quote' && quote && <section className="card p-6"><h2 className="text-lg font-bold text-navy">{t.quote}</h2><div className="mt-5 space-y-3">{quote.lines.map((line, i) => <div key={`${line.kind}-${i}`} className="flex justify-between gap-3 border-b border-neutral-100 pb-3 text-sm"><span className="text-neutral-600">{ar ? line.description_ar : line.description_en}</span><strong className="text-navy">{line.amount} {quote.currency}</strong></div>)}</div><div className="mt-5 flex items-center justify-between text-lg"><strong className="text-navy">{t.price}</strong><strong className="text-brand-700">{quote.total} {quote.currency}</strong></div><div className="mt-6 flex gap-3"><button type="button" onClick={() => setStage('details')} className="btn-secondary">{t.back}</button><button type="button" disabled={busy} onClick={confirm} className="btn-primary">{busy ? dict.common.loading : t.confirm}</button></div></section>}

      {stage === 'confirmed' && <section className="card border-success-100 bg-success-50 p-10 text-center"><div className="mx-auto grid h-14 w-14 place-items-center rounded-full bg-success-500 text-white">✓</div><h2 className="mt-5 text-2xl font-extrabold text-success-600">{t.confirmed}</h2><p className="mt-3 text-sm text-neutral-600">{ar ? 'سيتم إعلامك عند قبول الممارس وتأكيد تفاصيل الزيارة.' : 'You will be notified when the professional accepts and the visit details are confirmed.'}</p><Link href={`/${locale}/patients`} className="btn-primary mt-6">{dict.nav.patients}</Link></section>}
    </div>
  );
}