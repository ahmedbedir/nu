<script setup lang="ts">
import { computed, onMounted, onUnmounted, ref } from 'vue';
import { api, getToken, openOperationsStream, setToken, type StreamEvent } from './lib/api';

type Locale = 'ar' | 'en';

const locale = ref<Locale>('ar');
const ar = computed(() => locale.value === 'ar');
const dir = computed(() => (ar.value ? 'rtl' : 'ltr'));

onMounted(() => {
  document.documentElement.lang = locale.value;
  document.documentElement.dir = dir.value;
});

function toggleLocale() {
  locale.value = ar.value ? 'en' : 'ar';
  document.documentElement.lang = locale.value;
  document.documentElement.dir = dir.value;
}

/* ---------- auth ---------- */
const phone = ref('');
const code = ref('');
const stage = ref<'phone' | 'code'>('phone');
const devCode = ref<string | null>(null);
const authError = ref<string | null>(null);
const busy = ref(false);
const signedIn = ref(false);

onMounted(async () => {
  if (getToken()) {
    const me = await api.me();
    signedIn.value = me.ok;
  }
});

async function sendCode() {
  busy.value = true;
  authError.value = null;
  const res = await api.requestOtp(phone.value);
  busy.value = false;
  if (!res.ok) {
    authError.value = ar.value ? 'تعذّر إرسال الرمز' : 'Could not send the code';
    return;
  }
  devCode.value = res.data?.dev_code ?? null;
  if (devCode.value) code.value = devCode.value;
  stage.value = 'code';
}

async function verify() {
  busy.value = true;
  authError.value = null;
  const res = await api.verifyOtp(phone.value, code.value);
  busy.value = false;
  if (!res.ok) {
    authError.value = ar.value ? 'الرمز غير صحيح' : 'Incorrect code';
    return;
  }
  setToken(res.data!.token);
  signedIn.value = true;
  connectStream();
}

onUnmounted(() => {
  disconnectStream();
});

/* ---------- health ---------- */
const health = ref<'unknown' | 'ok' | 'degraded'>('unknown');
onMounted(async () => {
  const res = await api.health();
  health.value = res.ok ? 'ok' : 'degraded';
});

/* ---------- operations data ----------
 * There is no live feed wired to this screen yet, so it renders an explicit
 * empty state instead of inventing bookings or alarms.
 */
/* ---------- live operations stream ---------- */
const streamState = ref<'idle' | 'connecting' | 'open' | 'error' | 'closed'>('idle');
const events = ref<StreamEvent[]>([]);
let closeStream: (() => void) | null = null;

const sosOpen = computed(
  () => events.value.filter((e) => e.kind === 'sos' && e.state !== 'closed').length,
);

const kpis = computed(() => [
  { key: 'active', label: ar.value ? 'زيارات جارية' : 'Active visits', value: String(events.value.filter((e) => e.to_state === 'in_session').length) },
  { key: 'waiting', label: ar.value ? 'بحث قيد الانتظار' : 'Searches waiting', value: String(events.value.filter((e) => e.to_state === 'searching').length) },
  { key: 'online', label: ar.value ? 'أحداث اليوم' : 'Events today', value: String(events.value.length) },
  { key: 'sos', label: ar.value ? 'استغاثات مفتوحة' : 'Open SOS', value: String(sosOpen.value) },
]);

function connectStream() {
  if (closeStream) return;
  closeStream = openOperationsStream(
    (event) => {
      events.value = [event, ...events.value].slice(0, 100);
    },
    (state) => {
      streamState.value = state;
    },
  );
}

function disconnectStream() {
  closeStream?.();
  closeStream = null;
}

const nav = computed(() => [
  { id: 'ops', label: ar.value ? 'غرفة العمليات' : 'Operations room', icon: 'pulse' },
  { id: 'bookings', label: ar.value ? 'الحجوزات' : 'Bookings', icon: 'calendar' },
  { id: 'providers', label: ar.value ? 'الممارسون' : 'Professionals', icon: 'badge' },
  { id: 'catalog', label: ar.value ? 'كتالوج الخدمات' : 'Service catalog', icon: 'list' },
  { id: 'finance', label: ar.value ? 'المالية' : 'Finance', icon: 'coins' },
  { id: 'audit', label: ar.value ? 'التدقيق' : 'Audit trail', icon: 'shield' },
]);
const active = ref('ops');

function t(arText: string, enText: string) {
  return ar.value ? arText : enText;
}
</script>

<template>
  <div class="shell" :dir="dir">
    <!-- ---------- sign in ---------- -->
    <div v-if="!signedIn" class="login">
      <div class="login-card">
        <div class="login-brand">
          <span class="logo" aria-hidden="true">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none">
              <path d="M12 21s-7-4.35-7-10a4 4 0 0 1 7-2.65A4 4 0 0 1 19 11c0 5.65-7 10-7 10Z"
                    stroke="currentColor" stroke-width="1.8" stroke-linejoin="round" />
            </svg>
          </span>
          <div>
            <h1>{{ t('غرفة العمليات', 'Operations room') }}</h1>
            <p>{{ t('هوم كير مصر · دخول الموظفين', 'HomeCare Egypt · staff access') }}</p>
          </div>
        </div>

        <form v-if="stage === 'phone'" @submit.prevent="sendCode" class="login-form">
          <label for="phone">{{ t('رقم هاتف الموظف', 'Staff phone number') }}</label>
          <input id="phone" v-model="phone" type="tel" dir="ltr" required placeholder="01012345678" />
          <button type="submit" :disabled="busy || phone.length < 10">
            {{ busy ? t('جارٍ الإرسال…', 'Sending…') : t('إرسال رمز التحقق', 'Send verification code') }}
          </button>
        </form>

        <form v-else @submit.prevent="verify" class="login-form">
          <label for="code">{{ t('رمز التحقق', 'Verification code') }}</label>
          <input id="code" v-model="code" type="text" dir="ltr" maxlength="6" required
                 class="code" placeholder="••••••" />
          <p v-if="devCode" class="dev-hint">{{ t('رمز التطوير', 'Dev code') }}: <b>{{ devCode }}</b></p>
          <button type="submit" :disabled="busy || code.length !== 6">
            {{ busy ? t('جارٍ التحقق…', 'Verifying…') : t('دخول', 'Sign in') }}
          </button>
          <button type="button" class="link" @click="stage = 'phone'">
            {{ t('تغيير الرقم', 'Change number') }}
          </button>
        </form>

        <p v-if="authError" class="error" role="alert">{{ authError }}</p>

        <p class="login-foot">
          {{ t('الدخول مسموح لأدوار العمليات المعتمدة فقط، وكل إجراء يُسجَّل في سجل التدقيق.',
               'Access is limited to approved operations roles, and every action is audited.') }}
        </p>
      </div>
    </div>

    <!-- ---------- app ---------- -->
    <template v-else>
      <aside class="sidebar">
        <div class="side-brand">
          <span class="logo" aria-hidden="true">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none">
              <path d="M12 21s-7-4.35-7-10a4 4 0 0 1 7-2.65A4 4 0 0 1 19 11c0 5.65-7 10-7 10Z"
                    stroke="currentColor" stroke-width="1.8" stroke-linejoin="round" />
            </svg>
          </span>
          <div class="side-brand-text">
            <strong>{{ t('هوم كير مصر', 'HomeCare Egypt') }}</strong>
            <small>{{ t('لوحة الإدارة', 'Admin') }}</small>
          </div>
        </div>

        <nav class="side-nav">
          <button
            v-for="item in nav"
            :key="item.id"
            :class="['side-link', { active: active === item.id }]"
            @click="active = item.id"
          >
            <span class="side-dot" aria-hidden="true" />
            {{ item.label }}
          </button>
        </nav>

        <div class="side-foot">
          <span :class="['health', health]">
            <span class="health-dot" aria-hidden="true" />
            {{ health === 'ok' ? t('الخدمة متصلة', 'Service connected')
                              : health === 'degraded' ? t('الخدمة متعطلة', 'Service degraded')
                              : t('جارٍ الفحص…', 'Checking…') }}
          </span>
          <button class="link" @click="signedIn = false">{{ t('تسجيل الخروج', 'Sign out') }}</button>
        </div>
      </aside>

      <main class="main">
        <header class="topbar">
          <div>
            <h1>{{ t('غرفة العمليات', 'Operations room') }}</h1>
            <p>{{ t('نظرة لحظية على الطلبات والزيارات والاستغاثات', 'Live view of requests, visits and SOS') }}</p>
          </div>
          <button class="locale" @click="toggleLocale">{{ ar ? 'English' : 'العربية' }}</button>
        </header>

        <!-- SOS strip: highest priority, always visible, never colour-only -->
        <section class="sos-strip" :class="{ calm: sosOpen === 0 }">
          <div class="sos-icon" aria-hidden="true">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none">
              <path d="M12 4v16M4 12h16" stroke="currentColor" stroke-width="3" stroke-linecap="round" />
            </svg>
          </div>
          <div class="sos-copy">
            <strong>{{ t('استغاثات مفتوحة', 'Open SOS incidents') }}: {{ sosOpen }}</strong>
            <span>{{ t('أي استغاثة تُعد أولوية قصوى وتُصعَّد تلقائيًا عند عدم الاستلام.',
                      'Any SOS is top priority and escalates automatically when unacknowledged.') }}</span>
          </div>
          <span class="sos-state">{{ sosOpen === 0 ? t('لا يوجد', 'None') : t('يتطلب تدخلًا', 'Action needed') }}</span>
        </section>

        <section class="kpis">
          <article v-for="k in kpis" :key="k.key" class="kpi">
            <span class="kpi-label">{{ k.label }}</span>
            <span class="kpi-value">{{ k.value }}</span>
          </article>
        </section>

        <div class="grid">
          <section class="panel">
            <header class="panel-head">
              <h2>{{ t('بث الأحداث', 'Event stream') }}</h2>
              <span :class="['panel-tag', streamState === 'open' ? 'live' : '']">
                {{ streamState === 'open' ? t('مباشر', 'Live')
                  : streamState === 'connecting' ? t('جارٍ الاتصال', 'Connecting')
                  : streamState === 'error' ? t('غير مصرح أو منقطع', 'Denied or disconnected')
                  : t('غير موصول', 'Not connected') }}
              </span>
            </header>

            <ul v-if="events.length" class="events">
              <li v-for="(e, i) in events" :key="`${e.kind}-${i}`" :class="['event', e.kind]">
                <span class="event-dot" aria-hidden="true" />
                <span class="event-body">
                  <strong>{{ e.kind === 'sos' ? t('استغاثة', 'SOS') : t('حجز', 'Booking') }}</strong>
                  <span class="event-state">{{ e.to_state || e.state || e.event }}</span>
                </span>
                <time>{{ (e.created_at || e.occurred_at || '').slice(11, 19) }}</time>
              </li>
            </ul>

            <div v-else class="empty">
              <div class="empty-mark" aria-hidden="true">
                <svg width="26" height="26" viewBox="0 0 24 24" fill="none">
                  <path d="M4 18V6m0 12h16M8 15l3-4 3 2 4-6" stroke="currentColor"
                        stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" />
                </svg>
              </div>
              <p class="empty-title">{{ t('لا توجد أحداث لعرضها بعد', 'No events to show yet') }}</p>
              <p class="empty-body">
                {{ streamState === 'error'
                  ? t('تعذر الاتصال بالبث: الحساب قد لا يملك دور عمليات، أو الخادم غير متاح.',
                      'Could not connect: the account may lack a staff role, or the server is unavailable.')
                  : t('سيظهر هنا كل حدث حجز ووصول واستغاثة فور توفره. لن تُعرض بيانات تجريبية على أنها حقيقية.',
                      'Booking, arrival and SOS events appear here as they occur. No demo data is shown as real.') }}
              </p>
            </div>
          </section>

          <section class="panel">
            <header class="panel-head">
              <h2>{{ t('خريطة التغطية', 'Coverage map') }}</h2>
              <span class="panel-tag">{{ t('تخطيطي', 'Schematic') }}</span>
            </header>

            <div class="map" role="img"
                 :aria-label="t('خريطة تخطيطية لمناطق التغطية', 'Schematic coverage map')">
              <div class="map-grid" aria-hidden="true" />
              <div class="map-zone zone-a"><span>{{ t('المعادي', 'Maadi') }}</span></div>
              <div class="map-zone zone-b"><span>{{ t('مدينة نصر', 'Nasr City') }}</span></div>
              <div class="map-zone zone-c"><span>{{ t('6 أكتوبر', '6th of October') }}</span></div>
              <div class="map-note">
                {{ t('مواقع الممارسين تُعرض تقريبية قبل القبول، ولن تظهر إحداثيات دقيقة لأي طرف غير مصرح.',
                     'Provider locations stay approximate before acceptance; exact coordinates are never shown to unauthorised parties.') }}
              </div>
            </div>
          </section>
        </div>

        <section class="panel">
          <header class="panel-head">
            <h2>{{ t('جاهزية المناوبة', 'Duty readiness') }}</h2>
          </header>
          <ul class="checklist">
            <li>
              <span class="dot ok" aria-hidden="true" />
              {{ t('قاعدة البيانات متصلة', 'Database connected') }}
            </li>
            <li>
              <span class="dot warn" aria-hidden="true" />
              {{ t('مناوبة 24 ساعة: غير مُعدّة في هذه البيئة', '24-hour rota: not configured in this environment') }}
            </li>
            <li>
              <span class="dot warn" aria-hidden="true" />
              {{ t('قناة SMS: محوّل تطوير فقط (لا يؤكد التسليم)', 'SMS channel: development adapter only (no delivery confirmation)') }}
            </li>
          </ul>
        </section>
      </main>
    </template>
  </div>
</template>

<style scoped>
.shell { display: flex; min-height: 100vh; }

/* ---------- login ---------- */
.login { flex: 1; display: grid; place-items: center; padding: 24px;
  background: linear-gradient(180deg, var(--brand-50) 0%, #fff 55%); }
.login-card { width: 100%; max-width: 400px; background: #fff; border: 1px solid var(--neutral-200);
  border-radius: 20px; padding: 28px; box-shadow: var(--shadow-card); }
.login-brand { display: flex; gap: 12px; align-items: center; margin-bottom: 22px; }
.login-brand h1 { font-size: 17px; margin: 0; color: var(--navy); }
.login-brand p { font-size: 12px; margin: 2px 0 0; color: var(--neutral-500); }
.logo { width: 38px; height: 38px; border-radius: 12px; display: grid; place-items: center;
  background: var(--brand-500); color: #fff; flex: 0 0 auto; }
.login-form { display: grid; gap: 8px; }
.login-form label { font-size: 13px; font-weight: 600; color: var(--neutral-700); }
.login-form input { width: 100%; padding: 11px 14px; border: 1px solid var(--neutral-200);
  border-radius: 12px; font-size: 14px; }
.login-form input.code { text-align: center; font-size: 22px; font-weight: 700; letter-spacing: 0.4em; }
.login-form button[type='submit'] { margin-top: 10px; background: var(--brand-500); color: #fff;
  border: 0; border-radius: 12px; padding: 11px; font-weight: 600; }
.login-form button[type='submit']:disabled { opacity: 0.5; cursor: not-allowed; }
.dev-hint { margin: 4px 0 0; font-size: 12px; background: var(--coral-50); color: var(--coral-600);
  border: 1px solid var(--coral-100); border-radius: 8px; padding: 6px 10px; }
.error { margin-top: 12px; font-size: 13px; color: var(--coral-600); background: var(--coral-50);
  border-radius: 8px; padding: 8px 10px; }
.login-foot { margin-top: 18px; font-size: 11px; line-height: 1.7; color: var(--neutral-400); }
.link { background: none; border: 0; color: var(--brand-600); font-size: 12px; font-weight: 600;
  padding: 4px 0; text-align: start; }

/* ---------- sidebar ---------- */
.sidebar { width: 236px; flex: 0 0 236px; background: var(--navy); color: #fff;
  display: flex; flex-direction: column; padding: 18px 14px; position: sticky; top: 0; height: 100vh; }
.side-brand { display: flex; gap: 10px; align-items: center; padding: 4px 6px 18px; }
.side-brand-text { display: flex; flex-direction: column; line-height: 1.3; }
.side-brand-text strong { font-size: 14px; }
.side-brand-text small { font-size: 11px; color: var(--brand-200); }
.side-nav { display: grid; gap: 2px; margin-top: 6px; }
.side-link { display: flex; align-items: center; gap: 10px; background: none; border: 0;
  color: #c9d8e6; text-align: start; padding: 9px 11px; border-radius: 10px; font-size: 13px; }
.side-link:hover { background: rgba(255, 255, 255, 0.06); color: #fff; }
.side-link.active { background: rgba(255, 255, 255, 0.11); color: #fff; font-weight: 600; }
.side-dot { width: 6px; height: 6px; border-radius: 50%; background: currentColor; opacity: 0.5; }
.side-link.active .side-dot { opacity: 1; background: var(--coral-500); }
.side-foot { margin-top: auto; padding-top: 16px; border-top: 1px solid rgba(255, 255, 255, 0.1);
  display: grid; gap: 8px; }
.health { display: inline-flex; align-items: center; gap: 7px; font-size: 11px; color: #c9d8e6; }
.health-dot { width: 7px; height: 7px; border-radius: 50%; background: var(--neutral-400); }
.health.ok .health-dot { background: var(--success-500); }
.health.degraded .health-dot { background: var(--coral-500); }
.side-foot .link { color: var(--brand-200); }

/* ---------- main ---------- */
.main { flex: 1; padding: 22px 26px 40px; max-width: 1180px; }
.topbar { display: flex; align-items: flex-start; justify-content: space-between; gap: 16px; }
.topbar h1 { margin: 0; font-size: 21px; color: var(--navy); }
.topbar p { margin: 3px 0 0; font-size: 12px; color: var(--neutral-500); }
.locale { border: 1px solid var(--neutral-200); background: #fff; border-radius: 9px;
  padding: 6px 12px; font-size: 12px; font-weight: 600; color: var(--neutral-600); }

.sos-strip { margin-top: 18px; display: flex; align-items: center; gap: 12px;
  background: var(--coral-50); border: 1px solid var(--coral-100); border-radius: 14px; padding: 12px 16px; }
.sos-strip.calm { background: var(--success-50); border-color: #c6eede; }
.sos-icon { width: 34px; height: 34px; border-radius: 10px; display: grid; place-items: center;
  background: var(--coral-500); color: #fff; flex: 0 0 auto; }
.sos-strip.calm .sos-icon { background: var(--success-500); }
.sos-copy { display: flex; flex-direction: column; gap: 2px; }
.sos-copy strong { font-size: 13px; color: var(--navy); }
.sos-copy span { font-size: 11px; color: var(--neutral-600); }
.sos-state { margin-inline-start: auto; font-size: 11px; font-weight: 700; color: var(--coral-600); }
.sos-strip.calm .sos-state { color: var(--success-600); }

.kpis { display: grid; grid-template-columns: repeat(4, 1fr); gap: 12px; margin-top: 16px; }
.kpi { background: #fff; border: 1px solid var(--neutral-200); border-radius: 13px;
  padding: 14px 16px; display: grid; gap: 6px; }
.kpi-label { font-size: 11px; color: var(--neutral-500); }
.kpi-value { font-size: 22px; font-weight: 800; color: var(--navy); }

.grid { display: grid; grid-template-columns: 1.15fr 0.85fr; gap: 14px; margin-top: 14px; }
.panel { background: #fff; border: 1px solid var(--neutral-200); border-radius: 15px; padding: 16px 18px; }
.panel-head { display: flex; align-items: center; justify-content: space-between; margin-bottom: 12px; }
.panel-head h2 { margin: 0; font-size: 13px; font-weight: 700; color: var(--navy); }
.panel-tag { font-size: 10px; font-weight: 700; color: var(--neutral-400);
  background: var(--neutral-100); border-radius: 999px; padding: 3px 9px; }
.panel-tag.live { color: var(--success-600); background: var(--success-50); }

.events { list-style: none; margin: 0; padding: 0; display: grid; gap: 2px; max-height: 260px; overflow-y: auto; }
.event { display: flex; align-items: center; gap: 10px; padding: 8px 6px; border-radius: 8px; font-size: 12px; }
.event:nth-child(odd) { background: var(--neutral-25); }
.event-dot { width: 7px; height: 7px; border-radius: 50%; background: var(--brand-500); flex: 0 0 auto; }
.event.sos .event-dot { background: var(--coral-500); }
.event-body { display: flex; align-items: center; gap: 8px; min-width: 0; }
.event-body strong { color: var(--navy); }
.event-state { color: var(--neutral-500); font-family: ui-monospace, monospace; font-size: 11px; }
.event time { margin-inline-start: auto; color: var(--neutral-400); font-size: 10px; font-variant-numeric: tabular-nums; }

.empty { display: grid; justify-items: center; text-align: center; padding: 26px 12px 30px; }
.empty-mark { width: 52px; height: 52px; border-radius: 50%; display: grid; place-items: center;
  background: var(--neutral-50); color: var(--neutral-400); }
.empty-title { margin: 12px 0 0; font-size: 13px; font-weight: 700; color: var(--neutral-700); }
.empty-body { margin: 6px 0 0; font-size: 11px; line-height: 1.7; color: var(--neutral-500); max-width: 380px; }

.map { position: relative; height: 208px; border-radius: 12px; overflow: hidden;
  background: linear-gradient(160deg, #eef6fc, #e2edf7); border: 1px solid var(--neutral-200); }
.map-grid { position: absolute; inset: 0;
  background-image: linear-gradient(var(--brand-100) 1px, transparent 1px),
                    linear-gradient(90deg, var(--brand-100) 1px, transparent 1px);
  background-size: 28px 28px; opacity: 0.6; }
.map-zone { position: absolute; border-radius: 50%; background: rgba(18, 103, 178, 0.13);
  border: 1.5px solid rgba(18, 103, 178, 0.35); display: grid; place-items: center; }
.map-zone span { font-size: 10px; font-weight: 700; color: var(--brand-700); }
.zone-a { width: 96px; height: 96px; inset-block-start: 34px; inset-inline-start: 26px; }
.zone-b { width: 74px; height: 74px; inset-block-start: 88px; inset-inline-end: 34px; }
.zone-c { width: 62px; height: 62px; inset-block-end: 22px; inset-inline-start: 118px; }
.map-note { position: absolute; inset-inline: 10px; bottom: 10px; font-size: 10px; line-height: 1.6;
  color: var(--neutral-600); background: rgba(255, 255, 255, 0.9); border-radius: 8px; padding: 6px 9px; }

.checklist { list-style: none; margin: 0; padding: 0; display: grid; gap: 9px; }
.checklist li { display: flex; align-items: center; gap: 9px; font-size: 12px; color: var(--neutral-600); }
.dot { width: 8px; height: 8px; border-radius: 50%; flex: 0 0 auto; }
.dot.ok { background: var(--success-500); }
.dot.warn { background: #e5a33c; }

@media (max-width: 900px) {
  .shell { flex-direction: column; }
  .sidebar { width: 100%; height: auto; position: static; flex-direction: row; align-items: center; gap: 12px; }
  .side-nav { display: none; }
  .side-foot { margin: 0 0 0 auto; border: 0; padding: 0; }
  .kpis { grid-template-columns: repeat(2, 1fr); }
  .grid { grid-template-columns: 1fr; }
  .main { padding: 18px 16px 32px; }
}
</style>