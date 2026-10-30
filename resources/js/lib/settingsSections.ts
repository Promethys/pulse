import { index as indexAiKeys } from '@/routes/ai-keys';
import { edit as editAppearance } from '@/routes/appearance';
import { index as indexDelivery } from '@/routes/delivery';
import { edit as editDigest } from '@/routes/digest';
import { edit as editProfile } from '@/routes/profile';
import { edit as editSecurity } from '@/routes/security';

export const settingsSections = [
    { label: 'Profile', href: editProfile.url() },
    { label: 'Digest', href: editDigest.url() },
    { label: 'Delivery', href: indexDelivery.url() },
    { label: 'AI keys', href: indexAiKeys.url() },
    { label: 'Security', href: editSecurity.url() },
    { label: 'Appearance', href: editAppearance.url() },
];
