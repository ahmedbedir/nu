import type { Metadata, Viewport } from 'next';
import './globals.css';

export const metadata: Metadata = {
  title: {
    default: 'هوم كير مصر | HomeCare Egypt',
    template: '%s · هوم كير مصر',
  },
  description:
    'منصة رعاية منزلية تربط المرضى بممرضين مرخّصين ومُتحقَّق من مؤهلاتهم في مصر. A home-care marketplace connecting patients with verified licensed nurses in Egypt.',
  applicationName: 'HomeCare Egypt',
  robots: { index: false, follow: false }, // pre-launch: never index
};

export const viewport: Viewport = {
  themeColor: '#1267B2',
  width: 'device-width',
  initialScale: 1,
};

export default function RootLayout({ children }: { children: React.ReactNode }) {
  return children;
}