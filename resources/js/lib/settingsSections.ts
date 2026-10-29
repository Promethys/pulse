import { edit as editAppearance } from '@/routes/appearance';
import { edit as editProfile } from '@/routes/profile';
import { edit as editSecurity } from '@/routes/security';

export const settingsSections = [
    { label: 'Profile', href: editProfile.url() },
    { label: 'Security', href: editSecurity.url() },
    { label: 'Appearance', href: editAppearance.url() },
];
