'use client';

import { useRouter } from 'next/navigation';
import { useState } from 'react';
import { api, setToken } from '@/lib/api';
import type { Locale } from '@/lib/i18n';
import type { Dictionary } from '@/messages/ar';

type Stage = 'phone' | 'code';

export function LoginForm({ locale, dict }: { locale: Locale; dict: Dictionary }) {
  const router = useRouter();
  const [stage, setStage] = useState<Stage>('phone');
  const [phone, setPhone] = useState('');
  const [code, setCode] = useState('');
  const [devCode, setDevCode] = useState<string | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [busy, setBusy] = useState(false);

  const validPhone = /^0?1[0-9]{9}$/.test(phone.replace(/\s/g, ''));

  async function sendCode(e: React.FormEvent) {
    e.preventDefault();
    if (!validPhone || busy) return;

    setBusy(true);
    setError(null);

    const res = await api.requestOtp(phone.replace(/\s/g, ''));

    setBusy(false);

    if (!res.ok) {
      setError(res.status === 429 ? dict.auth.errorRate : dict.auth.errorGeneric);
      return;
    }

    setDevCode(res.data.dev_code);
    if (res.data.dev_code) setCode(res.data.dev_code);
    setStage('code');
  }

  async function verify(e: React.FormEvent) {
    e.preventDefault();
    if (code.length !== 6 || busy) return;

    setBusy(true);
    setError(null);

    const res = await api.verifyOtp(phone.replace(/\s/g, ''), code);

    setBusy(false);

    if (!res.ok) {
      setError(dict.auth.errorCode);
      return;
    }

    setToken(res.data.token);
    router.push(`/${locale}/patients`);
    router.refresh();
  }

  return (
    <form onSubmit={stage === 'phone' ? sendCode : verify} noValidate>
      {stage === 'phone' ? (
        <div>
          <label htmlFor="phone" className="label">
            {dict.auth.phone}
          </label>
          <input
            id="phone"
            name="phone"
            type="tel"
            inputMode="numeric"
            autoComplete="tel"
            dir="ltr"
            required
            aria-describedby="phone-hint"
            value={phone}
            onChange={(e) => setPhone(e.target.value)}
            placeholder="01012345678"
            className="field text-lg tracking-wide"
            autoFocus
          />
          <p id="phone-hint" className="mt-2 text-xs text-neutral-400">
            {dict.auth.phoneHint}
          </p>

          <button type="submit" disabled={!validPhone || busy} className="btn-primary mt-6 w-full py-3">
            {busy ? dict.common.loading : dict.auth.send}
          </button>
        </div>
      ) : (
        <div>
          <div className="mb-4 flex items-center justify-between rounded-xl bg-neutral-50 px-3.5 py-2.5">
            <span className="text-sm font-medium text-neutral-700" dir="ltr">
              {phone}
            </span>
            <button
              type="button"
              onClick={() => {
                setStage('phone');
                setCode('');
                setError(null);
              }}
              className="text-xs font-semibold text-brand-600 hover:text-brand-700"
            >
              {dict.auth.changePhone}
            </button>
          </div>

          <label htmlFor="code" className="label">
            {dict.auth.code}
          </label>
          <input
            id="code"
            name="code"
            type="text"
            inputMode="numeric"
            autoComplete="one-time-code"
            dir="ltr"
            maxLength={6}
            required
            aria-describedby={devCode ? 'dev-hint' : undefined}
            value={code}
            onChange={(e) => setCode(e.target.value.replace(/\D/g, '').slice(0, 6))}
            placeholder="••••••"
            className="field text-center text-2xl font-bold tracking-[0.5em]"
            autoFocus
          />
          <p className="mt-2 text-xs text-neutral-400">{dict.auth.codeHint}</p>

          {devCode && (
            <p
              id="dev-hint"
              className="mt-3 rounded-lg border border-coral-100 bg-coral-50 px-3 py-2 text-xs font-medium text-coral-700"
            >
              {dict.auth.devCode}: <span className="font-mono">{devCode}</span>
            </p>
          )}

          <button
            type="submit"
            disabled={code.length !== 6 || busy}
            className="btn-primary mt-6 w-full py-3"
          >
            {busy ? dict.common.loading : dict.auth.verify}
          </button>

          <button
            type="button"
            onClick={sendCode}
            className="mt-3 w-full text-center text-xs font-semibold text-brand-600 hover:text-brand-700"
          >
            {dict.auth.resend}
          </button>
        </div>
      )}

      {error && (
        <p role="alert" className="mt-4 rounded-lg border border-coral-100 bg-coral-50 px-3 py-2 text-sm text-coral-700">
          {error}
        </p>
      )}
    </form>
  );
}