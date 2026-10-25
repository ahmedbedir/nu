'use client';

import Link from 'next/link';
import { useCallback, useEffect, useState } from 'react';
import { api, getToken, type PatientItem } from '@/lib/api';
import type { Locale } from '@/lib/i18n';
import type { Dictionary } from '@/messages/ar';

export function PatientsManager({ locale, dict }: { locale: Locale; dict: Dictionary }) {
  const ar = locale === 'ar';

  const [token, setLocalToken] = useState<string | null>(null);
  const [checked, setChecked] = useState(false);
  const [patients, setPatients] = useState<PatientItem[]>([]);
  const [loading, setLoading] = useState(true);
  const [showForm, setShowForm] = useState(false);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    setLocalToken(getToken());
    setChecked(true);
  }, []);

  const load = useCallback(async () => {
    setLoading(true);
    const res = await api.patients();
    setLoading(false);

    if (res.ok) setPatients(res.data.data);
    else setError(dict.common.error);
  }, [dict.common.error]);

  useEffect(() => {
    if (checked && token) void load();
    else if (checked && !token) setLoading(false);
  }, [checked, token, load]);

  if (!checked) return <Skeleton />;

  if (!token) {
    return (
      <div className="card mx-auto max-w-lg p-10 text-center">
        <div className="mx-auto grid h-12 w-12 place-items-center rounded-full bg-brand-50 text-brand-600">
          <svg width="22" height="22" viewBox="0 0 24 24" fill="none" aria-hidden="true">
            <path
              d="M12 15a3 3 0 1 0 0-6 3 3 0 0 0 0 6Z"
              stroke="currentColor"
              strokeWidth="1.8"
            />
            <path
              d="M5 20c1.2-3.2 3.9-5 7-5s5.8 1.8 7 5"
              stroke="currentColor"
              strokeWidth="1.8"
              strokeLinecap="round"
            />
          </svg>
        </div>
        <h2 className="mt-4 text-base font-bold text-navy">
          {ar ? 'سجّل الدخول لعرض المرضى' : 'Sign in to view patients'}
        </h2>
        <p className="mt-2 text-sm text-neutral-500">
          {ar
            ? 'ملفات المرضى محمية ولا تظهر إلا لصاحب الحساب أو المفوّضين.'
            : 'Patient records are protected and visible only to the account owner or delegates.'}
        </p>
        <Link href={`/${locale}/login`} className="btn-primary mt-6">
          {dict.nav.login}
        </Link>
      </div>
    );
  }

  return (
    <div>
      <div className="mb-6 flex items-center justify-between gap-4">
        <p className="text-sm text-neutral-500">
          {loading ? dict.common.loading : `${patients.length} ${ar ? 'ملف' : 'profiles'}`}
        </p>
        <button type="button" onClick={() => setShowForm((v) => !v)} className="btn-primary !py-2 !text-xs">
          {showForm ? dict.patients.cancel : `+ ${dict.patients.add}`}
        </button>
      </div>

      {showForm && (
        <NewPatientForm
          locale={locale}
          dict={dict}
          onCreated={() => {
            setShowForm(false);
            void load();
          }}
        />
      )}

      {error && (
        <p role="alert" className="mb-4 rounded-lg border border-coral-100 bg-coral-50 px-3 py-2 text-sm text-coral-700">
          {error}
        </p>
      )}

      {loading ? (
        <Skeleton />
      ) : patients.length === 0 ? (
        <div className="card p-10 text-center">
          <p className="text-sm text-neutral-500">{dict.patients.empty}</p>
        </div>
      ) : (
        <ul className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
          {patients.map((p) => (
            <li key={p.public_id} className="card p-5">
              <div className="flex items-start justify-between gap-3">
                <div>
                  <h3 className="font-bold text-navy">{p.full_name}</h3>
                  <p className="mt-1 text-xs text-neutral-500">
                    {p.age !== null && p.age !== undefined
                      ? `${p.age} ${dict.patients.years}`
                      : p.birth_date || '—'}
                    {' · '}
                    {p.sex === 'male'
                      ? dict.patients.male
                      : p.sex === 'female'
                        ? dict.patients.female
                        : dict.patients.unspecified}
                  </p>
                </div>
                <span className="badge-neutral">
                  {p.status === 'active' ? (ar ? 'نشط' : 'Active') : p.status}
                </span>
              </div>

              <dl className="mt-4 space-y-1.5 border-t border-neutral-100 pt-3 text-xs">
                <ClinicalRow
                  label={ar ? 'الحساسية' : 'Allergies'}
                  state={p.allergies_state}
                  dict={dict}
                />
                <ClinicalRow
                  label={ar ? 'الأدوية' : 'Medications'}
                  state={p.medications_state}
                  dict={dict}
                />
              </dl>

              <p className="mt-3 text-2xs leading-relaxed text-neutral-400">{dict.patients.clinicalHidden}</p>
            </li>
          ))}
        </ul>
      )}
    </div>
  );
}

function ClinicalRow({
  label,
  state,
  dict,
}: {
  label: string;
  state: 'known' | 'unknown';
  dict: Dictionary;
}) {
  return (
    <div className="flex items-center justify-between">
      <dt className="text-neutral-500">{label}</dt>
      <dd className={state === 'known' ? 'font-semibold text-success-600' : 'font-semibold text-neutral-400'}>
        {state === 'known' ? dict.patients.knownState : dict.patients.unknownState}
      </dd>
    </div>
  );
}

function NewPatientForm({
  locale,
  dict,
  onCreated,
}: {
  locale: Locale;
  dict: Dictionary;
  onCreated: () => void;
}) {
  const ar = locale === 'ar';
  const [name, setName] = useState('');
  const [birthDate, setBirthDate] = useState('');
  const [sex, setSex] = useState('unspecified');
  const [relationship, setRelationship] = useState('self');
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState<string | null>(null);

  async function submit(e: React.FormEvent) {
    e.preventDefault();
    if (!name.trim() || busy) return;

    setBusy(true);
    setError(null);

    const res = await api.createPatient({
      full_name: name.trim(),
      birth_date: birthDate || undefined,
      sex,
      relationship,
    });

    setBusy(false);

    if (!res.ok) {
      const first = res.errors ? Object.values(res.errors)[0]?.[0] : undefined;
      setError(first || dict.common.error);
      return;
    }

    onCreated();
  }

  return (
    <form onSubmit={submit} className="card mb-6 p-6">
      <div className="grid gap-4 sm:grid-cols-2">
        <div className="sm:col-span-2">
          <label htmlFor="p-name" className="label">
            {dict.patients.name}
          </label>
          <input
            id="p-name"
            required
            value={name}
            onChange={(e) => setName(e.target.value)}
            className="field"
            placeholder={ar ? 'مثال: أم محمد' : 'e.g. Mohamed’s mother'}
          />
        </div>

        <div>
          <label htmlFor="p-birth" className="label">
            {dict.patients.birthDate}
          </label>
          <input
            id="p-birth"
            type="date"
            max={new Date().toISOString().slice(0, 10)}
            value={birthDate}
            onChange={(e) => setBirthDate(e.target.value)}
            className="field"
          />
        </div>

        <div>
          <label htmlFor="p-sex" className="label">
            {dict.patients.sex}
          </label>
          <select id="p-sex" value={sex} onChange={(e) => setSex(e.target.value)} className="field">
            <option value="female">{dict.patients.female}</option>
            <option value="male">{dict.patients.male}</option>
            <option value="unspecified">{dict.patients.unspecified}</option>
          </select>
        </div>

        <div className="sm:col-span-2">
          <label htmlFor="p-rel" className="label">
            {dict.patients.relationship}
          </label>
          <select
            id="p-rel"
            value={relationship}
            onChange={(e) => setRelationship(e.target.value)}
            className="field"
          >
            <option value="self">{dict.patients.self}</option>
            <option value="parent">{dict.patients.parent}</option>
            <option value="guardian">{dict.patients.guardian}</option>
            <option value="relative">{dict.patients.relative}</option>
            <option value="caregiver">{dict.patients.caregiver}</option>
          </select>
        </div>
      </div>

      {error && (
        <p role="alert" className="mt-4 rounded-lg border border-coral-100 bg-coral-50 px-3 py-2 text-sm text-coral-700">
          {error}
        </p>
      )}

      <div className="mt-6 flex gap-3">
        <button type="submit" disabled={busy || !name.trim()} className="btn-primary">
          {busy ? dict.common.loading : dict.patients.save}
        </button>
      </div>
    </form>
  );
}

function Skeleton() {
  return (
    <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3" aria-busy="true">
      {[0, 1, 2].map((i) => (
        <div key={i} className="card animate-pulse p-5">
          <div className="h-4 w-2/3 rounded bg-neutral-100" />
          <div className="mt-3 h-3 w-1/3 rounded bg-neutral-100" />
          <div className="mt-6 h-3 w-full rounded bg-neutral-100" />
          <div className="mt-2 h-3 w-4/5 rounded bg-neutral-100" />
        </div>
      ))}
    </div>
  );
}