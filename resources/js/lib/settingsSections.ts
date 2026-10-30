import { edit as editAppearance } from '@/routes/appearance';
import { edit as editProfile } from '@/routes/profile';
import { edit as editSecurity } from '@/routes/security';

export const settingsSections = [
    { label: 'Profile', href: editProfile.url() },
    { label: 'Digest', href: '/settings/digest' },
    { label: 'Delivery', href: '/settings/delivery' },
    { label: 'AI keys', href: '/settings/ai-keys' },
    { label: 'Security', href: editSecurity.url() },
    { label: 'Appearance', href: editAppearance.url() },
];
