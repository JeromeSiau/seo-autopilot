import { Head, Link } from '@inertiajs/react';
import { useState, useEffect, useRef } from 'react';
import {
    Wind,
    Compass,
    Anchor,
    Map,
    Ship,
    ArrowRight,
    Play,
    Star,
    Menu,
    X,
    Check,
    ChevronDown,
    Globe,
    Search,
    BarChart2,
    PenTool,
    ShieldCheck,
    Link2,
    Target,
    Rocket,
    Plug,
} from 'lucide-react';
import Logo from '@/Components/Logo';
import ThemeToggle from '@/Components/ThemeToggle';
import { useTheme } from '@/Contexts/ThemeContext';

// Animated wave SVG component
function WaveBackground({ className = '', opacity = 0.1 }: { className?: string; opacity?: number }) {
    return (
        <div className={`absolute inset-0 overflow-hidden pointer-events-none ${className}`}>
            <svg
                className="absolute bottom-0 left-0 w-full"
                style={{ opacity }}
                viewBox="0 0 1440 320"
                preserveAspectRatio="none"
            >
                <path
                    className="animate-wave-slow"
                    fill="currentColor"
                    d="M0,192L48,197.3C96,203,192,213,288,229.3C384,245,480,267,576,250.7C672,235,768,181,864,181.3C960,181,1056,235,1152,234.7C1248,235,1344,181,1392,154.7L1440,128L1440,320L1392,320C1344,320,1248,320,1152,320C1056,320,960,320,864,320C768,320,672,320,576,320C480,320,384,320,288,320C192,320,96,320,48,320L0,320Z"
                />
            </svg>
            <svg
                className="absolute bottom-0 left-0 w-full"
                style={{ opacity: opacity * 0.7 }}
                viewBox="0 0 1440 320"
                preserveAspectRatio="none"
            >
                <path
                    className="animate-wave-medium"
                    fill="currentColor"
                    d="M0,256L48,240C96,224,192,192,288,181.3C384,171,480,181,576,186.7C672,192,768,192,864,208C960,224,1056,256,1152,261.3C1248,267,1344,245,1392,234.7L1440,224L1440,320L1392,320C1344,320,1248,320,1152,320C1056,320,960,320,864,320C768,320,672,320,576,320C480,320,384,320,288,320C192,320,96,320,48,320L0,320Z"
                />
            </svg>
        </div>
    );
}

// Wave separator between sections
function WaveSeparator({ flip = false, lightColor = '#fafaf8', darkColor = '#1a1a1a' }: { flip?: boolean; lightColor?: string; darkColor?: string }) {
    return (
        <div className={`relative w-full overflow-hidden ${flip ? 'rotate-180' : ''}`} style={{ height: '50px', marginTop: '-1px', marginBottom: '-1px' }}>
            <svg
                className="absolute bottom-0 w-full"
                viewBox="0 0 1440 50"
                preserveAspectRatio="none"
                style={{ height: '100%' }}
            >
                <path
                    className="fill-current text-surface-50 dark:text-surface-900"
                    style={{ fill: 'var(--wave-fill)' }}
                    d="M0,25 C240,50 480,0 720,25 C960,50 1200,0 1440,25 L1440,50 L0,50 Z"
                />
            </svg>
            <style>{`
                :root { --wave-fill: ${lightColor}; }
                .dark { --wave-fill: ${darkColor}; }
            `}</style>
        </div>
    );
}

// Compass loader/spinner component
function CompassLoader({ size = 40, className = '' }: { size?: number; className?: string }) {
    return (
        <div className={`relative ${className}`} style={{ width: size, height: size }}>
            <svg viewBox="0 0 100 100" className="w-full h-full animate-spin-slow">
                {/* Outer ring */}
                <circle cx="50" cy="50" r="45" fill="none" stroke="currentColor" strokeWidth="2" opacity="0.3" />
                {/* Compass points */}
                <path d="M50 10 L53 20 L50 18 L47 20 Z" fill="currentColor" /> {/* N */}
                <path d="M90 50 L80 53 L82 50 L80 47 Z" fill="currentColor" opacity="0.5" /> {/* E */}
                <path d="M50 90 L47 80 L50 82 L53 80 Z" fill="currentColor" opacity="0.5" /> {/* S */}
                <path d="M10 50 L20 47 L18 50 L20 53 Z" fill="currentColor" opacity="0.5" /> {/* W */}
                {/* Needle */}
                <path d="M50 20 L55 50 L50 55 L45 50 Z" fill="#22c55e" />
                <path d="M50 80 L45 50 L50 45 L55 50 Z" fill="#ef4444" />
                {/* Center */}
                <circle cx="50" cy="50" r="5" fill="currentColor" />
            </svg>
        </div>
    );
}

interface LandingProps {
    locale: string;
    translations: Record<string, any>;
    canLogin: boolean;
    canRegister: boolean;
}

// Animation hook for scroll-triggered animations
function useInView(threshold = 0.1) {
    const ref = useRef<HTMLDivElement>(null);
    const [isInView, setIsInView] = useState(false);

    useEffect(() => {
        const observer = new IntersectionObserver(
            ([entry]) => {
                if (entry.isIntersecting) {
                    setIsInView(true);
                }
            },
            { threshold }
        );

        if (ref.current) {
            observer.observe(ref.current);
        }

        return () => observer.disconnect();
    }, [threshold]);

    return { ref, isInView };
}

// Animated section wrapper
function AnimatedSection({
    children,
    className = '',
    delay = 0,
}: {
    children: React.ReactNode;
    className?: string;
    delay?: number;
}) {
    const { ref, isInView } = useInView();

    return (
        <div
            ref={ref}
            className={`transition-all duration-700 ease-out ${className}`}
            style={{
                opacity: isInView ? 1 : 0,
                transform: isInView ? 'translateY(0)' : 'translateY(24px)',
                transitionDelay: `${delay}ms`,
            }}
        >
            {children}
        </div>
    );
}

export default function Landing({ locale, translations: t, canLogin, canRegister }: LandingProps) {
    const [mobileMenuOpen, setMobileMenuOpen] = useState(false);
    const [isScrolled, setIsScrolled] = useState(false);
    const [openFaq, setOpenFaq] = useState<number | null>(null);
    const [langMenuOpen, setLangMenuOpen] = useState(false);
    const langMenuRef = useRef<HTMLDivElement>(null);

    useEffect(() => {
        const handleScroll = () => {
            setIsScrolled(window.scrollY > 20);
        };
        window.addEventListener('scroll', handleScroll);
        return () => window.removeEventListener('scroll', handleScroll);
    }, []);

    // Close language menu when clicking outside
    useEffect(() => {
        const handleClickOutside = (event: MouseEvent) => {
            if (langMenuRef.current && !langMenuRef.current.contains(event.target as Node)) {
                setLangMenuOpen(false);
            }
        };
        document.addEventListener('mousedown', handleClickOutside);
        return () => document.removeEventListener('mousedown', handleClickOutside);
    }, []);

    const languages = [
        { code: 'en', label: 'EN', name: 'English', flag: '🇺🇸' },
        { code: 'fr', label: 'FR', name: 'Français', flag: '🇫🇷' },
        { code: 'es', label: 'ES', name: 'Español', flag: '🇪🇸' },
    ];

    const currentLang = languages.find(l => l.code === locale) || languages[0];

    const workflowSteps = [
        { icon: Search, title: t.features.workflow?.step1?.title || 'Research', description: t.features.workflow?.step1?.description || 'Gathers insights from top-ranking pages' },
        { icon: BarChart2, title: t.features.workflow?.step2?.title || 'Analysis', description: t.features.workflow?.step2?.description || 'Studies competitor content' },
        { icon: PenTool, title: t.features.workflow?.step3?.title || 'Writing', description: t.features.workflow?.step3?.description || 'Crafts SEO-optimized content' },
        { icon: ShieldCheck, title: t.features.workflow?.step4?.title || 'Verification', description: t.features.workflow?.step4?.description || 'Fact-checks claims' },
        { icon: Link2, title: t.features.workflow?.step5?.title || 'Optimization', description: t.features.workflow?.step5?.description || 'Adds smart internal links' },
    ];

    const features = [
        { icon: Target, title: t.features.feature1.title, description: t.features.feature1.description },
        { icon: Rocket, title: t.features.feature2.title, description: t.features.feature2.description },
        { icon: Plug, title: t.features.feature3.title, description: t.features.feature3.description },
    ];

    const steps = [
        { number: '1', title: t.howItWorks.step1.title, description: t.howItWorks.step1.description },
        { number: '2', title: t.howItWorks.step2.title, description: t.howItWorks.step2.description },
        { number: '3', title: t.howItWorks.step3.title, description: t.howItWorks.step3.description },
        { number: '4', title: t.howItWorks.step4?.title || 'Watch rankings grow', description: t.howItWorks.step4?.description || 'Track your progress as you climb.' },
    ];

    const testimonials = [
        t.testimonials.testimonial1,
        t.testimonials.testimonial2,
        t.testimonials.testimonial3,
    ];

    const faqs = [
        t.faq.q1,
        t.faq.q2,
        t.faq.q7,
        t.faq.q8,
        t.faq.q3,
        t.faq.q4,
        t.faq.q5,
        t.faq.q6,
    ];

    const pricingPlans = [
        { ...t.pricing.starter, featured: false },
        { ...t.pricing.pro, featured: true },
        { ...t.pricing.agency, featured: false },
    ];

    return (
        <>
            <Head title={t.meta.title}>
                <meta name="description" content={t.meta.description} />
            </Head>

            <div className="min-h-screen bg-surface-50 dark:bg-surface-900 text-surface-700 dark:text-surface-300 transition-colors duration-300">

                {/* Header */}
                <header
                    className={`fixed top-0 left-0 right-0 z-50 transition-all duration-300 ${
                        isScrolled
                            ? 'bg-surface-50/85 dark:bg-surface-900/90 backdrop-blur-xl border-b border-black/5 dark:border-white/5'
                            : 'bg-transparent'
                    }`}
                >
                    <div className="max-w-[1200px] mx-auto px-8">
                        <div className="flex items-center justify-between h-16">
                            {/* Left: Logo + Nav */}
                            <div className="flex items-center gap-12">
                                <Link href={`/${locale}`} className="flex items-center">
                                    <Logo size="lg" />
                                </Link>

                                <nav className="hidden lg:flex items-center gap-1">
                                    <a
                                        href="#features"
                                        className="px-4 py-2 text-[0.9rem] font-medium text-surface-500 dark:text-surface-400 hover:text-surface-900 dark:hover:text-white hover:bg-white dark:hover:bg-surface-800 rounded-lg transition-all"
                                    >
                                        {t.nav.features}
                                    </a>
                                    <a
                                        href="#how-it-works"
                                        className="px-4 py-2 text-[0.9rem] font-medium text-surface-500 dark:text-surface-400 hover:text-surface-900 dark:hover:text-white hover:bg-white dark:hover:bg-surface-800 rounded-lg transition-all"
                                    >
                                        {t.nav.howItWorks}
                                    </a>
                                    <a
                                        href="#pricing"
                                        className="px-4 py-2 text-[0.9rem] font-medium text-surface-500 dark:text-surface-400 hover:text-surface-900 dark:hover:text-white hover:bg-white dark:hover:bg-surface-800 rounded-lg transition-all"
                                    >
                                        {t.nav.pricing}
                                    </a>
                                    <a
                                        href="#faq"
                                        className="px-4 py-2 text-[0.9rem] font-medium text-surface-500 dark:text-surface-400 hover:text-surface-900 dark:hover:text-white hover:bg-white dark:hover:bg-surface-800 rounded-lg transition-all"
                                    >
                                        {t.nav.faq}
                                    </a>
                                </nav>
                            </div>

                            {/* Right: Theme Toggle + Language Switcher + CTAs */}
                            <div className="hidden lg:flex items-center gap-4">
                                {/* Theme Toggle */}
                                <ThemeToggle />

                                {/* Language Switcher Dropdown */}
                                <div className="relative" ref={langMenuRef}>
                                    <button
                                        onClick={() => setLangMenuOpen(!langMenuOpen)}
                                        className="flex items-center gap-2 px-3 py-2 text-[0.9rem] font-medium text-surface-600 dark:text-surface-400 hover:text-surface-900 dark:hover:text-white hover:bg-white/50 dark:hover:bg-surface-800 rounded-lg transition-all"
                                    >
                                        <Globe className="w-4 h-4" />
                                        <span>{currentLang.label}</span>
                                        <ChevronDown className={`w-4 h-4 transition-transform ${langMenuOpen ? 'rotate-180' : ''}`} />
                                    </button>

                                    {langMenuOpen && (
                                        <div className="absolute right-0 mt-2 w-44 bg-white dark:bg-surface-900 rounded-xl shadow-lg dark:shadow-card-dark border border-surface-200 dark:border-surface-800 py-2 z-50">
                                            {languages.map((lang) => (
                                                <Link
                                                    key={lang.code}
                                                    href={`/${lang.code}`}
                                                    onClick={() => setLangMenuOpen(false)}
                                                    className={`flex items-center gap-3 px-4 py-2.5 text-[0.9rem] transition-colors ${
                                                        lang.code === locale
                                                            ? 'bg-primary-50 dark:bg-primary-500/10 text-primary-600 dark:text-primary-400 font-medium'
                                                            : 'text-surface-600 dark:text-surface-400 hover:bg-surface-50 dark:hover:bg-surface-800'
                                                    }`}
                                                >
                                                    <span className="text-base">{lang.flag}</span>
                                                    <span>{lang.name}</span>
                                                    {lang.code === locale && (
                                                        <Check className="w-4 h-4 ml-auto text-primary-500 dark:text-primary-400" />
                                                    )}
                                                </Link>
                                            ))}
                                        </div>
                                    )}
                                </div>

                                {canLogin && (
                                    <Link
                                        href={route('login')}
                                        className="px-4 py-2.5 text-[0.9rem] font-medium text-surface-900 dark:text-white hover:text-primary-600 dark:hover:text-primary-400 transition-colors"
                                    >
                                        {t.nav.login}
                                    </Link>
                                )}
                                {canRegister && (
                                    <Link
                                        href={route('register')}
                                        className="inline-flex items-center gap-2 px-5 py-2.5 bg-gradient-to-r from-primary-500 to-primary-600 text-white text-[0.9rem] font-semibold rounded-xl shadow-green dark:shadow-green-glow hover:shadow-green-lg dark:hover:shadow-green-glow-lg hover:-translate-y-0.5 transition-all"
                                    >
                                        {t.nav.getStarted}
                                        <ArrowRight className="w-4 h-4" />
                                    </Link>
                                )}
                            </div>

                            {/* Mobile menu button */}
                            <div className="lg:hidden flex items-center gap-2">
                                <ThemeToggle />
                                <button
                                    onClick={() => setMobileMenuOpen(!mobileMenuOpen)}
                                    className="p-2 text-surface-600 dark:text-surface-400"
                                >
                                    {mobileMenuOpen ? <X className="w-6 h-6" /> : <Menu className="w-6 h-6" />}
                                </button>
                            </div>
                        </div>
                    </div>

                    {/* Mobile menu */}
                    {mobileMenuOpen && (
                        <div className="lg:hidden bg-white dark:bg-surface-900 border-t border-surface-200 dark:border-surface-800">
                            <div className="px-6 py-4 space-y-2">
                                <a href="#features" className="block py-3 text-surface-600 dark:text-surface-300 font-medium">
                                    {t.nav.features}
                                </a>
                                <a href="#how-it-works" className="block py-3 text-surface-600 dark:text-surface-300 font-medium">
                                    {t.nav.howItWorks}
                                </a>
                                <a href="#pricing" className="block py-3 text-surface-600 dark:text-surface-300 font-medium">
                                    {t.nav.pricing}
                                </a>
                                <a href="#faq" className="block py-3 text-surface-600 dark:text-surface-300 font-medium">
                                    {t.nav.faq}
                                </a>
                                {/* Mobile Language Switcher */}
                                <div className="py-3 border-t border-surface-200 dark:border-surface-800 mt-2">
                                    <p className="text-xs font-medium text-surface-400 uppercase tracking-wide mb-2">Language</p>
                                    <div className="flex flex-col gap-1">
                                        {languages.map((lang) => (
                                            <Link
                                                key={lang.code}
                                                href={`/${lang.code}`}
                                                className={`flex items-center gap-3 px-3 py-2.5 rounded-lg transition-all ${
                                                    lang.code === locale
                                                        ? 'bg-primary-50 dark:bg-primary-500/10 text-primary-600 dark:text-primary-400 font-medium'
                                                        : 'text-surface-600 dark:text-surface-400 hover:bg-surface-50 dark:hover:bg-surface-800'
                                                }`}
                                            >
                                                <span className="text-lg">{lang.flag}</span>
                                                <span>{lang.name}</span>
                                                {lang.code === locale && (
                                                    <Check className="w-4 h-4 ml-auto text-primary-500 dark:text-primary-400" />
                                                )}
                                            </Link>
                                        ))}
                                    </div>
                                </div>
                                <div className="pt-4 border-t border-surface-200 dark:border-surface-800 space-y-3">
                                    {canLogin && (
                                        <Link href={route('login')} className="block py-2 text-surface-900 dark:text-white font-medium">
                                            {t.nav.login}
                                        </Link>
                                    )}
                                    {canRegister && (
                                        <Link
                                            href={route('register')}
                                            className="block w-full text-center py-3 bg-primary-500 text-white font-semibold rounded-xl"
                                        >
                                            {t.nav.getStarted}
                                        </Link>
                                    )}
                                </div>
                            </div>
                        </div>
                    )}
                </header>

                {/* Hero Section */}
                <section className="pt-24 pb-28 lg:pt-32 lg:pb-32 relative overflow-hidden">
                    {/* Animated waves background */}
                    <WaveBackground className="text-primary-500" opacity={0.08} />

                    <div className="max-w-[1200px] mx-auto px-8 relative z-10">
                        <div className="grid lg:grid-cols-2 gap-16 items-center">
                            {/* Left: Content */}
                            <div>
                                <AnimatedSection>
                                    <div className="inline-flex items-center gap-2 px-4 py-2 bg-primary-50 dark:bg-primary-500/10 dark:border dark:border-primary-500/20 text-primary-600 dark:text-primary-400 text-[0.85rem] font-semibold rounded-full mb-6">
                                        <Compass className="w-4 h-4" />
                                        {t.hero.badge}
                                    </div>
                                </AnimatedSection>

                                <AnimatedSection delay={100}>
                                    <h1 className="font-display text-[3.75rem] font-bold leading-[1.1] text-surface-900 dark:text-white mb-6 tracking-tight">
                                        {t.hero.title}{' '}
                                        <span className="text-primary-500 dark:text-primary-400">{t.hero.titleAccent}</span>
                                    </h1>
                                </AnimatedSection>

                                <AnimatedSection delay={150}>
                                    <p className="text-[1.2rem] text-surface-500 dark:text-surface-400 leading-relaxed mb-8 max-w-[480px]">
                                        {t.hero.subtitle}
                                    </p>
                                </AnimatedSection>

                                <AnimatedSection delay={200}>
                                    <div className="flex flex-wrap items-center gap-4 mb-10">
                                        <Link
                                            href={route('register')}
                                            className="inline-flex items-center gap-2 px-8 py-4 bg-gradient-to-r from-primary-500 to-primary-600 text-white text-base font-semibold rounded-xl shadow-green dark:shadow-green-glow hover:shadow-green-lg dark:hover:shadow-green-glow-lg hover:-translate-y-0.5 transition-all"
                                        >
                                            {t.hero.cta}
                                            <ArrowRight className="w-5 h-5" />
                                        </Link>
                                        <a
                                            href="#how-it-works"
                                            className="inline-flex items-center gap-2 px-6 py-4 text-surface-900 dark:text-white text-base font-medium hover:text-primary-600 dark:hover:text-primary-400 transition-colors"
                                        >
                                            <Play className="w-5 h-5" />
                                            {t.hero.ctaSecondary}
                                        </a>
                                    </div>
                                </AnimatedSection>

                                <AnimatedSection delay={250}>
                                    <div className="flex gap-10 pt-6 border-t border-surface-200 dark:border-surface-800">
                                        <div>
                                            <div className="font-display text-[1.75rem] font-bold text-surface-900 dark:text-white">50K+</div>
                                            <div className="text-[0.9rem] text-surface-500 dark:text-surface-400">{t.hero.stats.articles}</div>
                                        </div>
                                        <div>
                                            <div className="font-display text-[1.75rem] font-bold text-surface-900 dark:text-white">2.5K+</div>
                                            <div className="text-[0.9rem] text-surface-500 dark:text-surface-400">{t.hero.stats.users || 'Happy users'}</div>
                                        </div>
                                        <div>
                                            <div className="font-display text-[1.75rem] font-bold text-surface-900 dark:text-white">85%</div>
                                            <div className="text-[0.9rem] text-surface-500 dark:text-surface-400">{t.hero.stats.ranking || 'Ranking boost'}</div>
                                        </div>
                                    </div>
                                </AnimatedSection>
                            </div>

                            {/* Right: Bento Grid */}
                            <AnimatedSection delay={300} className="hidden lg:block">
                                <div className="grid grid-cols-2 gap-4">
                                    <div className="bg-white dark:bg-surface-900/70 dark:backdrop-blur-xl rounded-2xl p-6 shadow-md dark:shadow-card-dark border border-black/5 dark:border-surface-800 hover:-translate-y-1 hover:shadow-lg dark:hover:border-primary-500/30 dark:hover:shadow-green-glow hover:animate-sway transition-all">
                                        <div className="w-10 h-10 bg-primary-100 dark:bg-primary-500/15 rounded-xl flex items-center justify-center text-primary-600 dark:text-primary-400 mb-4">
                                            <Ship className="w-5 h-5" />
                                        </div>
                                        <div className="text-[0.9rem] font-medium text-surface-900 dark:text-surface-200 mb-1">Articles Today</div>
                                        <div className="font-display text-[2rem] font-bold text-primary-500 dark:text-primary-400">12</div>
                                    </div>
                                    <div className="bg-white dark:bg-surface-900/70 dark:backdrop-blur-xl rounded-2xl p-6 shadow-md dark:shadow-card-dark border border-black/5 dark:border-surface-800 hover:-translate-y-1 hover:shadow-lg dark:hover:border-primary-500/30 dark:hover:shadow-green-glow hover:animate-sway transition-all">
                                        <div className="w-10 h-10 bg-primary-100 dark:bg-primary-500/15 rounded-xl flex items-center justify-center text-primary-600 dark:text-primary-400 mb-4">
                                            <Anchor className="w-5 h-5" />
                                        </div>
                                        <div className="text-[0.9rem] font-medium text-surface-900 dark:text-surface-200 mb-1">Avg. Position</div>
                                        <div className="font-display text-[2rem] font-bold text-primary-500 dark:text-primary-400">#4.2</div>
                                    </div>
                                    <div className="col-span-2 bg-gradient-to-br from-primary-50 to-primary-100/50 dark:from-primary-500/10 dark:to-primary-500/5 rounded-2xl p-6 shadow-md dark:shadow-card-dark border border-primary-200/50 dark:border-primary-500/20 hover:-translate-y-1 hover:shadow-lg dark:hover:shadow-green-glow transition-all relative overflow-hidden">
                                        <div className="text-[0.9rem] font-medium text-surface-900 dark:text-surface-200 mb-1">Organic Traffic Growth</div>
                                        <div className="text-[0.85rem] text-surface-500 dark:text-surface-400 mb-4">Last 7 days • +34% from previous week</div>
                                        <div className="flex items-end gap-2 h-[60px]">
                                            {[40, 55, 45, 70, 60, 85, 95].map((height, i) => (
                                                <div
                                                    key={i}
                                                    className={`flex-1 rounded-t transition-all duration-1000 ${i >= 5 ? 'bg-primary-500 dark:shadow-[0_0_10px_rgba(16,185,129,0.5)]' : 'bg-primary-200 dark:bg-primary-500/25'}`}
                                                    style={{
                                                        height: `${height}%`,
                                                        animation: `float ${3 + i * 0.3}s ease-in-out infinite`,
                                                        animationDelay: `${i * 0.15}s`
                                                    }}
                                                />
                                            ))}
                                        </div>
                                    </div>
                                </div>
                            </AnimatedSection>
                        </div>
                    </div>
                </section>

                {/* Social Proof */}
                <section className="py-12 border-y border-surface-200 dark:border-surface-800">
                    <div className="max-w-[1200px] mx-auto px-8">
                        <AnimatedSection>
                            <div className="flex flex-col md:flex-row items-center justify-between gap-8">
                                <span className="text-[0.9rem] font-medium text-surface-400">{t.socialProof.title}</span>
                                <div className="flex flex-wrap items-center justify-center gap-12">
                                    {['Acme Inc', 'TechCorp', 'StartupXYZ', 'MediaGroup', 'GlobalCo'].map((company) => (
                                        <span key={company} className="font-display text-xl font-semibold text-surface-300 dark:text-surface-600">
                                            {company}
                                        </span>
                                    ))}
                                </div>
                            </div>
                        </AnimatedSection>
                    </div>
                </section>

                {/* Wave separator */}
                <WaveSeparator lightColor="#fafaf8" darkColor="#1a1a1a" />

                {/* Problem/Solution */}
                <section className="py-24 bg-surface-50 dark:bg-[#1a1a1a]">
                    <div className="max-w-[1200px] mx-auto px-8">
                        <AnimatedSection>
                            <div className="text-center mb-16">
                                <span className="inline-flex items-center px-4 py-1.5 bg-primary-50 dark:bg-primary-500/10 dark:border dark:border-primary-500/20 text-primary-600 dark:text-primary-400 text-[0.8rem] font-semibold rounded-full uppercase tracking-wide mb-4">
                                    {t.problem.label}
                                </span>
                                <h2 className="font-display text-display-sm font-bold text-surface-900 dark:text-white mb-4">
                                    {t.problem.title}
                                </h2>
                                <p className="text-lg text-surface-500 dark:text-surface-400 max-w-[600px] mx-auto">
                                    {t.problem.subtitle}
                                </p>
                            </div>
                        </AnimatedSection>

                        <div className="grid md:grid-cols-3 gap-8 items-start">
                            {/* Problem Card */}
                            <AnimatedSection delay={100}>
                                <div className="bg-gradient-to-br from-red-50 to-white dark:from-red-500/10 dark:to-surface-900/70 rounded-[20px] p-10 border border-red-200 dark:border-red-500/20 shadow-md dark:shadow-card-dark dark:backdrop-blur-xl">
                                    <h3 className="flex items-center gap-3 font-display text-[1.35rem] font-semibold text-red-600 dark:text-red-400 mb-6">
                                        <X className="w-6 h-6" />
                                        {t.problem.cardTitle || 'Without SEO Autopilot'}
                                    </h3>
                                    <ul className="space-y-4">
                                        {(t.problem.pains || ['Hours spent researching keywords', 'Expensive freelance writers', 'Inconsistent content quality', 'Slow publishing schedule', 'No SEO optimization']).map((pain: string, i: number) => (
                                            <li key={i} className="flex items-start gap-3 text-[0.95rem] text-surface-700 dark:text-surface-300 pb-4 border-b border-surface-200 dark:border-surface-800 last:border-0 last:pb-0">
                                                <span className="text-red-500 dark:text-red-400 font-semibold">✕</span>
                                                {pain}
                                            </li>
                                        ))}
                                    </ul>
                                </div>
                            </AnimatedSection>

                            {/* Arrow */}
                            <div className="hidden md:flex items-center justify-center pt-20">
                                <ArrowRight className="w-10 h-10 text-primary-500 dark:text-primary-400" />
                            </div>

                            {/* Solution Card */}
                            <AnimatedSection delay={200}>
                                <div className="bg-gradient-to-br from-primary-50 to-white dark:from-primary-500/10 dark:to-surface-900/70 rounded-[20px] p-10 border border-primary-200 dark:border-primary-500/20 shadow-md dark:shadow-card-dark dark:backdrop-blur-xl">
                                    <h3 className="flex items-center gap-3 font-display text-[1.35rem] font-semibold text-primary-600 dark:text-primary-400 mb-6">
                                        <Check className="w-6 h-6" />
                                        {t.solution.cardTitle || 'With SEO Autopilot'}
                                    </h3>
                                    <ul className="space-y-4">
                                        {(t.solution.points || ['AI finds winning keywords', 'Unlimited content generation', 'Consistent, high-quality output', 'Publish daily on autopilot', 'Built-in SEO best practices']).map((point: string, i: number) => (
                                            <li key={i} className="flex items-start gap-3 text-[0.95rem] text-surface-700 dark:text-surface-300 pb-4 border-b border-surface-200 dark:border-surface-800 last:border-0 last:pb-0">
                                                <span className="text-primary-500 dark:text-primary-400 font-semibold">✓</span>
                                                {point}
                                            </li>
                                        ))}
                                    </ul>
                                </div>
                            </AnimatedSection>
                        </div>
                    </div>
                </section>

                {/* Wave separator */}
                <WaveSeparator lightColor="#f5f4f0" darkColor="#1a1a1a" />

                {/* Features - Multi-Agent Workflow */}
                <section id="features" className="py-24 bg-surface-100 dark:bg-surface-900">
                    <div className="max-w-[1200px] mx-auto px-8">
                        <AnimatedSection>
                            <div className="text-center mb-16">
                                <span className="inline-flex items-center px-4 py-1.5 bg-primary-50 dark:bg-primary-500/10 dark:border dark:border-primary-500/20 text-primary-600 dark:text-primary-400 text-[0.8rem] font-semibold rounded-full uppercase tracking-wide mb-4">
                                    {t.features.label}
                                </span>
                                <h2 className="font-display text-display-sm font-bold text-surface-900 dark:text-white mb-4">
                                    {t.features.title}
                                </h2>
                                <p className="text-lg text-surface-500 dark:text-surface-400 max-w-[600px] mx-auto">
                                    {t.features.subtitle}
                                </p>
                            </div>
                        </AnimatedSection>

                        {/* Workflow Pipeline */}
                        <AnimatedSection delay={100}>
                            <div className="relative mb-16">
                                {/* Workflow steps */}
                                <div className="grid grid-cols-1 md:grid-cols-3 lg:grid-cols-5 gap-8 lg:gap-3">
                                    {workflowSteps.map((step, i) => (
                                        <div key={i} className="group relative flex flex-col items-center">
                                            {/* Connection line to next step - desktop only */}
                                            {i < workflowSteps.length - 1 && (
                                                <div className="hidden lg:flex absolute top-[40px] left-[calc(50%+52px)] items-center" style={{ width: 'calc(100% - 104px + 12px)' }}>
                                                    <div className="flex-1 h-[3px] bg-gradient-to-r from-primary-400 to-primary-300 dark:from-primary-500 dark:to-primary-600 rounded-full" />
                                                    <ArrowRight className="w-5 h-5 text-primary-400 dark:text-primary-500 -ml-1 flex-shrink-0" />
                                                </div>
                                            )}

                                            {/* Icon container */}
                                            <div className="relative mb-5">
                                                {/* Glow effect on hover */}
                                                <div className="absolute inset-0 bg-primary-400/30 dark:bg-primary-500/40 rounded-full blur-xl scale-75 opacity-0 group-hover:opacity-100 group-hover:scale-110 transition-all duration-500" />

                                                {/* Main icon circle */}
                                                <div className="relative w-20 h-20 bg-gradient-to-br from-white via-white to-surface-100 dark:from-surface-800 dark:via-surface-800 dark:to-surface-900 rounded-full flex items-center justify-center shadow-lg dark:shadow-card-dark border-2 border-primary-200/80 dark:border-primary-500/40 group-hover:border-primary-400 dark:group-hover:border-primary-400 group-hover:shadow-xl dark:group-hover:shadow-green-glow transition-all duration-300 group-hover:-translate-y-1 group-hover:scale-105">
                                                    {/* Step number badge */}
                                                    <div className="absolute -top-1.5 -right-1.5 w-6 h-6 bg-gradient-to-br from-primary-500 to-primary-600 text-white text-[0.7rem] font-bold rounded-full flex items-center justify-center shadow-md ring-2 ring-white dark:ring-surface-900">
                                                        {i + 1}
                                                    </div>
                                                    <step.icon className="w-8 h-8 text-primary-600 dark:text-primary-400 group-hover:scale-110 transition-transform duration-300" />
                                                </div>
                                            </div>

                                            {/* Title */}
                                            <h3 className="font-display text-base font-semibold text-surface-900 dark:text-white mb-2 group-hover:text-primary-600 dark:group-hover:text-primary-400 transition-colors text-center">
                                                {step.title}
                                            </h3>

                                            {/* Description - always visible */}
                                            <p className="text-[0.85rem] text-surface-500 dark:text-surface-400 leading-relaxed text-center max-w-[180px] min-h-[3rem]">
                                                {step.description}
                                            </p>

                                            {/* Arrow connector - mobile only */}
                                            {i < workflowSteps.length - 1 && (
                                                <div className="lg:hidden flex justify-center mt-4 mb-2 md:hidden">
                                                    <div className="w-8 h-8 rounded-full bg-primary-100 dark:bg-primary-500/20 flex items-center justify-center">
                                                        <ArrowRight className="w-4 h-4 text-primary-500 dark:text-primary-400 rotate-90" />
                                                    </div>
                                                </div>
                                            )}
                                        </div>
                                    ))}
                                </div>
                            </div>
                        </AnimatedSection>

                        {/* Additional Features */}
                        <AnimatedSection delay={200}>
                            <div className="text-center mb-8">
                                <span className="text-sm font-medium text-surface-400 dark:text-surface-500 uppercase tracking-wide">
                                    {t.features.moreFeatures}
                                </span>
                            </div>
                        </AnimatedSection>

                        <div className="grid md:grid-cols-3 gap-6">
                            {features.map((feature, i) => (
                                <AnimatedSection key={i} delay={250 + i * 100}>
                                    <div className="bg-white dark:bg-surface-800/50 dark:backdrop-blur-xl rounded-2xl p-8 shadow-sm dark:shadow-card-dark border border-surface-200 dark:border-surface-700 hover:-translate-y-1 hover:shadow-lg dark:hover:shadow-green-glow hover:border-primary-200 dark:hover:border-primary-500/30 transition-all">
                                        <div className="w-12 h-12 bg-gradient-to-br from-primary-50 to-primary-100 dark:from-primary-500/15 dark:to-primary-500/5 rounded-xl flex items-center justify-center text-primary-600 dark:text-primary-400 mb-5">
                                            <feature.icon className="w-6 h-6" />
                                        </div>
                                        <h3 className="font-display text-lg font-semibold text-surface-900 dark:text-white mb-2">
                                            {feature.title}
                                        </h3>
                                        <p className="text-[0.95rem] text-surface-500 dark:text-surface-400 leading-relaxed">
                                            {feature.description}
                                        </p>
                                    </div>
                                </AnimatedSection>
                            ))}
                        </div>
                    </div>
                </section>

                {/* Wave separator */}
                <WaveSeparator lightColor="#fafaf8" darkColor="#1a1a1a" flip />

                {/* How It Works */}
                <section id="how-it-works" className="py-24 dark:bg-[#1a1a1a]">
                    <div className="max-w-[1200px] mx-auto px-8">
                        <AnimatedSection>
                            <div className="text-center mb-16">
                                <span className="inline-flex items-center px-4 py-1.5 bg-primary-50 dark:bg-primary-500/10 dark:border dark:border-primary-500/20 text-primary-600 dark:text-primary-400 text-[0.8rem] font-semibold rounded-full uppercase tracking-wide mb-4">
                                    {t.howItWorks.label}
                                </span>
                                <h2 className="font-display text-display-sm font-bold text-surface-900 dark:text-white mb-4">
                                    {t.howItWorks.title}
                                </h2>
                                <p className="text-lg text-surface-500 dark:text-surface-400 max-w-[600px] mx-auto">
                                    {t.howItWorks.subtitle}
                                </p>
                            </div>
                        </AnimatedSection>

                        <div className="grid md:grid-cols-2 lg:grid-cols-4 gap-8">
                            {steps.map((step, i) => (
                                <AnimatedSection key={i} delay={i * 100}>
                                    <div className="text-center relative">
                                        {i < steps.length - 1 && (
                                            <div className="hidden lg:block absolute top-7 left-1/2 w-full h-0.5 bg-gradient-to-r from-primary-200 to-primary-500 dark:from-primary-500/30 dark:to-primary-500" />
                                        )}
                                        <div className="relative w-14 h-14 bg-gradient-to-br from-primary-500 to-primary-600 text-white rounded-full flex items-center justify-center font-display text-2xl font-bold mx-auto mb-5 shadow-green dark:shadow-green-glow">
                                            {step.number}
                                        </div>
                                        <h3 className="font-display text-lg font-semibold text-surface-900 dark:text-white mb-2">
                                            {step.title}
                                        </h3>
                                        <p className="text-[0.9rem] text-surface-500 dark:text-surface-400">
                                            {step.description}
                                        </p>
                                    </div>
                                </AnimatedSection>
                            ))}
                        </div>
                    </div>
                </section>

                {/* Pricing */}
                <section id="pricing" className="py-24 bg-surface-100 dark:bg-surface-900">
                    <div className="max-w-[1000px] mx-auto px-8">
                        <AnimatedSection>
                            <div className="text-center mb-16">
                                <span className="inline-flex items-center px-4 py-1.5 bg-primary-50 dark:bg-primary-500/10 dark:border dark:border-primary-500/20 text-primary-600 dark:text-primary-400 text-[0.8rem] font-semibold rounded-full uppercase tracking-wide mb-4">
                                    {t.pricing.label}
                                </span>
                                <h2 className="font-display text-display-sm font-bold text-surface-900 dark:text-white mb-4">
                                    {t.pricing.title}
                                </h2>
                                <p className="text-lg text-surface-500 dark:text-surface-400 max-w-[600px] mx-auto">
                                    {t.pricing.subtitle}
                                </p>
                            </div>
                        </AnimatedSection>

                        <div className="grid lg:grid-cols-3 gap-6">
                            {pricingPlans.map((plan, i) => (
                                <AnimatedSection key={i} delay={i * 100}>
                                    <div
                                        className={`relative bg-white dark:bg-surface-800/50 dark:backdrop-blur-xl rounded-[20px] p-10 shadow-md dark:shadow-card-dark transition-all hover:-translate-y-1 ${
                                            plan.featured
                                                ? 'border-2 border-primary-500 shadow-lg dark:shadow-green-glow ring-4 ring-primary-100 dark:ring-primary-500/10'
                                                : 'border border-surface-200 dark:border-surface-700'
                                        }`}
                                    >
                                        {plan.popular && (
                                            <div className="absolute -top-3 left-1/2 -translate-x-1/2 px-4 py-1 bg-gradient-to-r from-primary-500 to-primary-600 text-white text-xs font-semibold rounded-full uppercase tracking-wide shadow-green dark:shadow-green-glow">
                                                {plan.popular}
                                            </div>
                                        )}
                                        <div className="mb-6">
                                            <h3 className="font-display text-[1.35rem] font-semibold text-surface-900 dark:text-white mb-1">
                                                {plan.name}
                                            </h3>
                                            <p className="text-[0.9rem] text-surface-500 dark:text-surface-400">{plan.description}</p>
                                        </div>
                                        <div className="mb-6">
                                            <span className="font-display text-[3rem] font-bold text-surface-900 dark:text-white">
                                                ${plan.price}
                                            </span>
                                            <span className="text-surface-500 dark:text-surface-400">{t.pricing.perMonth}</span>
                                        </div>
                                        <ul className="space-y-3 mb-8">
                                            {plan.features.map((feature: string, j: number) => (
                                                <li key={j} className="flex items-center gap-3 text-[0.95rem] text-surface-700 dark:text-surface-300">
                                                    <Check className="w-5 h-5 text-primary-500 dark:text-primary-400 flex-shrink-0" />
                                                    {feature}
                                                </li>
                                            ))}
                                        </ul>
                                        <Link
                                            href={route('register')}
                                            className={`block w-full text-center py-3.5 font-semibold rounded-xl transition-all ${
                                                plan.featured
                                                    ? 'bg-gradient-to-r from-primary-500 to-primary-600 text-white shadow-green dark:shadow-green-glow hover:shadow-green-lg dark:hover:shadow-green-glow-lg'
                                                    : 'border-2 border-surface-200 dark:border-surface-700 text-surface-900 dark:text-white hover:border-primary-500 dark:hover:border-primary-400 hover:text-primary-600 dark:hover:text-primary-400'
                                            }`}
                                        >
                                            {plan.cta}
                                        </Link>
                                    </div>
                                </AnimatedSection>
                            ))}
                        </div>
                    </div>
                </section>

                {/* Wave separator */}
                <WaveSeparator lightColor="#fafaf8" darkColor="#1a1a1a" flip />

                {/* Testimonials */}
                <section className="py-24 dark:bg-[#1a1a1a]">
                    <div className="max-w-[1200px] mx-auto px-8">
                        <AnimatedSection>
                            <div className="text-center mb-16">
                                <span className="inline-flex items-center px-4 py-1.5 bg-primary-50 dark:bg-primary-500/10 dark:border dark:border-primary-500/20 text-primary-600 dark:text-primary-400 text-[0.8rem] font-semibold rounded-full uppercase tracking-wide mb-4">
                                    {t.testimonials.label}
                                </span>
                                <h2 className="font-display text-display-sm font-bold text-surface-900 dark:text-white mb-4">
                                    {t.testimonials.title}
                                </h2>
                                <p className="text-lg text-surface-500 dark:text-surface-400 max-w-[600px] mx-auto">
                                    {t.testimonials.subtitle}
                                </p>
                            </div>
                        </AnimatedSection>

                        <div className="grid md:grid-cols-3 gap-6">
                            {testimonials.map((testimonial, i) => (
                                <AnimatedSection key={i} delay={i * 100}>
                                    <div className="bg-white dark:bg-surface-800/50 dark:backdrop-blur-xl rounded-2xl p-8 shadow-sm dark:shadow-card-dark border border-surface-200 dark:border-surface-700">
                                        <div className="flex gap-1 mb-4 text-yellow-400">
                                            {[...Array(5)].map((_, j) => (
                                                <Star key={j} className="w-5 h-5 fill-current" />
                                            ))}
                                        </div>
                                        <p className="text-surface-700 dark:text-surface-300 leading-relaxed mb-6">
                                            "{testimonial.quote}"
                                        </p>
                                        <div className="flex items-center gap-3">
                                            <div className="w-11 h-11 bg-gradient-to-br from-primary-100 to-primary-200 dark:from-primary-500/20 dark:to-primary-500/10 rounded-full flex items-center justify-center font-semibold text-primary-700 dark:text-primary-400">
                                                {testimonial.author
                                                    .split(' ')
                                                    .map((n: string) => n[0])
                                                    .join('')}
                                            </div>
                                            <div>
                                                <div className="font-semibold text-surface-900 dark:text-white">{testimonial.author}</div>
                                                <div className="text-[0.85rem] text-surface-500 dark:text-surface-400">
                                                    {testimonial.role}, {testimonial.company}
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </AnimatedSection>
                            ))}
                        </div>
                    </div>
                </section>

                {/* Wave separator */}
                <WaveSeparator lightColor="#f5f4f0" darkColor="#1a1a1a" />

                {/* FAQ */}
                <section id="faq" className="py-24 bg-surface-100 dark:bg-surface-900">
                    <div className="max-w-[900px] mx-auto px-8">
                        <AnimatedSection>
                            <div className="text-center mb-16">
                                <span className="inline-flex items-center px-4 py-1.5 bg-primary-50 dark:bg-primary-500/10 dark:border dark:border-primary-500/20 text-primary-600 dark:text-primary-400 text-[0.8rem] font-semibold rounded-full uppercase tracking-wide mb-4">
                                    {t.faq.label}
                                </span>
                                <h2 className="font-display text-display-sm font-bold text-surface-900 dark:text-white">
                                    {t.faq.title}
                                </h2>
                            </div>
                        </AnimatedSection>

                        <div className="grid md:grid-cols-2 gap-6">
                            {faqs.map((faq, i) => (
                                <AnimatedSection key={i} delay={i * 50}>
                                    <div className="bg-white dark:bg-surface-800/50 dark:backdrop-blur-xl rounded-xl p-6 shadow-sm dark:shadow-card-dark border border-transparent dark:border-surface-700">
                                        <h3 className="font-display text-[1.05rem] font-semibold text-surface-900 dark:text-white mb-3">
                                            {faq.question}
                                        </h3>
                                        <p className="text-[0.95rem] text-surface-500 dark:text-surface-400 leading-relaxed">
                                            {faq.answer}
                                        </p>
                                    </div>
                                </AnimatedSection>
                            ))}
                        </div>
                    </div>
                </section>

                {/* CTA */}
                <section className="py-24 dark:bg-[#1a1a1a]">
                    <div className="max-w-[1200px] mx-auto px-8">
                        <AnimatedSection>
                            <div className="bg-surface-900 dark:bg-surface-800/80 dark:backdrop-blur-xl dark:border dark:border-surface-700 rounded-3xl p-16 text-center relative overflow-hidden">
                                {/* Background glow */}
                                <div className="absolute inset-0 opacity-15 dark:opacity-25">
                                    <div className="absolute top-0 left-1/4 w-96 h-96 bg-primary-500 rounded-full blur-3xl" />
                                    <div className="absolute bottom-0 right-1/4 w-96 h-96 bg-primary-500 rounded-full blur-3xl" />
                                </div>
                                <div className="relative">
                                    <h2 className="font-display text-[2.5rem] font-bold text-white mb-4">
                                        {t.cta.title}
                                    </h2>
                                    <p className="text-lg text-white/70 mb-8">
                                        {t.cta.subtitle}
                                    </p>
                                    <Link
                                        href={route('register')}
                                        className="inline-flex items-center gap-2 px-10 py-4 bg-gradient-to-r from-primary-500 to-primary-600 text-white text-base font-semibold rounded-xl shadow-green dark:shadow-green-glow hover:shadow-green-lg dark:hover:shadow-green-glow-lg hover:-translate-y-0.5 transition-all"
                                    >
                                        {t.cta.button}
                                        <ArrowRight className="w-5 h-5" />
                                    </Link>
                                </div>
                            </div>
                        </AnimatedSection>
                    </div>
                </section>

                {/* Footer */}
                <footer className="bg-surface-900 text-surface-300 py-16">
                    <div className="max-w-[1200px] mx-auto px-8">
                        <div className="grid grid-cols-2 md:grid-cols-4 gap-8 mb-12">
                            {/* Brand */}
                            <div className="col-span-2 md:col-span-1">
                                <Link href={`/${locale}`} className="inline-block mb-4">
                                    <Logo size="lg" variant="white" />
                                </Link>
                                <p className="text-[0.95rem] text-surface-400 leading-relaxed max-w-[280px]">
                                    {t.footer.description}
                                </p>
                            </div>

                            {/* Product */}
                            <div>
                                <h4 className="font-semibold text-white text-sm uppercase tracking-wide mb-4">
                                    {t.footer.product}
                                </h4>
                                <ul className="space-y-2 text-[0.9rem]">
                                    <li><a href="#features" className="hover:text-primary-400 transition-colors">{t.footer.features}</a></li>
                                    <li><a href="#pricing" className="hover:text-primary-400 transition-colors">{t.footer.pricing}</a></li>
                                    <li><a href="#" className="hover:text-primary-400 transition-colors">{t.footer.integrations}</a></li>
                                    <li><a href="#" className="hover:text-primary-400 transition-colors">{t.footer.changelog}</a></li>
                                </ul>
                            </div>

                            {/* Resources */}
                            <div>
                                <h4 className="font-semibold text-white text-sm uppercase tracking-wide mb-4">
                                    {t.footer.resources || 'Resources'}
                                </h4>
                                <ul className="space-y-2 text-[0.9rem]">
                                    <li><a href="#" className="hover:text-primary-400 transition-colors">{t.footer.blog}</a></li>
                                    <li><a href="#" className="hover:text-primary-400 transition-colors">{t.footer.documentation || 'Documentation'}</a></li>
                                    <li><a href="#" className="hover:text-primary-400 transition-colors">{t.footer.helpCenter || 'Help Center'}</a></li>
                                    <li><a href="#" className="hover:text-primary-400 transition-colors">{t.footer.api || 'API'}</a></li>
                                </ul>
                            </div>

                            {/* Company */}
                            <div>
                                <h4 className="font-semibold text-white text-sm uppercase tracking-wide mb-4">
                                    {t.footer.company}
                                </h4>
                                <ul className="space-y-2 text-[0.9rem]">
                                    <li><a href="#" className="hover:text-primary-400 transition-colors">{t.footer.about}</a></li>
                                    <li><a href="#" className="hover:text-primary-400 transition-colors">{t.footer.careers}</a></li>
                                    <li><a href="#" className="hover:text-primary-400 transition-colors">{t.footer.contact}</a></li>
                                    <li><a href="#" className="hover:text-primary-400 transition-colors">{t.footer.legal || 'Legal'}</a></li>
                                </ul>
                            </div>
                        </div>

                        {/* Bottom */}
                        <div className="flex flex-col md:flex-row items-center justify-between gap-4 pt-8 border-t border-surface-800 text-[0.85rem]">
                            <span className="text-surface-500">{t.footer.copyright}</span>
                            <div className="flex items-center gap-6">
                                <a href="#" className="text-surface-500 hover:text-white transition-colors">{t.footer.privacy}</a>
                                <a href="#" className="text-surface-500 hover:text-white transition-colors">{t.footer.terms}</a>
                            </div>
                            <div className="flex items-center gap-4">
                                {languages.map((lang) => (
                                    <Link
                                        key={lang.code}
                                        href={`/${lang.code}`}
                                        className={`transition-colors ${
                                            lang.code === locale
                                                ? 'text-white font-medium'
                                                : 'text-surface-500 hover:text-white'
                                        }`}
                                    >
                                        {lang.label}
                                    </Link>
                                ))}
                            </div>
                        </div>
                    </div>
                </footer>
            </div>
        </>
    );
}
