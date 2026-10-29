<script setup lang="ts">
import {
    Brain,
    BarChart3,
    Shield,
    Smartphone,
    Wrench,
    Palette,
    SquareKanban,
    Link2,
    Building2,
    Code2,
    Braces,
    Server,
    Terminal,
    Briefcase,
    Megaphone,
    Users,
    Laptop,
    Camera,
    Gamepad2,
    Film,
    Music,
    PenLine,
    Brush,
    Mic,
    Tv,
    Newspaper,
    Smile,
    FlaskConical,
    TrendingUp,
    Wallet,
    GraduationCap,
    Landmark,
    Scale,
    TrainFront,
    Sigma,
    HeartHandshake,
    Languages,
    Trophy,
    BookOpen,
    Shirt,
    Sparkles,
    Church,
    Globe,
    Heart,
    UtensilsCrossed,
    Baby,
    House,
    PawPrint,
    CheckCircle2,
    Leaf,
    Flower2,
    Plane,
    Mountain,
    Check,
    Tag,
} from '@lucide/vue';
import type { Component } from 'vue';
import { cn } from '@/lib/utils';
import type { OnboardingInterest } from '@/types/onboarding';

defineProps<{ interest: OnboardingInterest; selected: boolean }>();
defineEmits<{ toggle: [] }>();
const icons: Record<string, Component> = {
    brain: Brain,
    chart: BarChart3,
    shield: Shield,
    smartphone: Smartphone,
    wrench: Wrench,
    palette: Palette,
    kanban: SquareKanban,
    link: Link2,
    building: Building2,
    code: Code2,
    braces: Braces,
    server: Server,
    terminal: Terminal,
    briefcase: Briefcase,
    megaphone: Megaphone,
    users: Users,
    laptop: Laptop,
    camera: Camera,
    gamepad: Gamepad2,
    film: Film,
    music: Music,
    pen: PenLine,
    brush: Brush,
    mic: Mic,
    tv: Tv,
    newspaper: Newspaper,
    smile: Smile,
    flask: FlaskConical,
    trending: TrendingUp,
    wallet: Wallet,
    graduation: GraduationCap,
    landmark: Landmark,
    scale: Scale,
    train: TrainFront,
    sigma: Sigma,
    'heart-handshake': HeartHandshake,
    languages: Languages,
    trophy: Trophy,
    book: BookOpen,
    shirt: Shirt,
    sparkles: Sparkles,
    church: Church,
    globe: Globe,
    heart: Heart,
    utensils: UtensilsCrossed,
    baby: Baby,
    home: House,
    paw: PawPrint,
    check: CheckCircle2,
    leaf: Leaf,
    flower: Flower2,
    plane: Plane,
    mountain: Mountain,
};
</script>

<template>
    <button
        type="button"
        :disabled="interest.status !== 'live'"
        :aria-pressed="interest.status === 'live' && selected"
        :class="
            cn(
                'inline-flex h-10 items-center gap-2 rounded-full border px-3.5 text-sm font-medium transition-colors focus-visible:outline-2 focus-visible:outline-ring',
                selected && interest.status === 'live'
                    ? 'border-primary-strong bg-primary text-primary-foreground'
                    : 'border-border bg-card hover:bg-secondary',
                interest.status !== 'live' &&
                    'cursor-not-allowed border-dashed bg-transparent text-muted-foreground opacity-70 hover:bg-transparent',
            )
        "
        @click="$emit('toggle')"
    >
        <component
            :is="
                selected && interest.status === 'live'
                    ? Check
                    : (icons[interest.icon] ?? Tag)
            "
            class="size-4"
            aria-hidden="true"
        />
        {{ interest.name }}
        <span
            v-if="interest.status !== 'live'"
            class="rounded-full bg-muted px-1.5 py-0.5 text-[10px] font-semibold tracking-wide uppercase"
            >Coming soon</span
        >
    </button>
</template>
