import { Head, Link } from '@inertiajs/react';
import { useState, useEffect, useRef } from 'react';
import {
    Zap,
    FileText,
    TrendingUp,
    Calendar,
    Globe,
    Shield,
    ArrowRight,
    Play,
    Star,
    Menu,
    X,
    Check,
    ChevronDown,
} from 'lucide-react';
import Logo from '@/Components/Logo';

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

    const features = [
        { icon: Zap, title: t.features.feature1.title, description: t.features.feature1.description },
        { icon: FileText, title: t.features.feature2.title, description: t.features.feature2.description },
        { icon: TrendingUp, title: t.features.feature3.title, description: t.features.feature3.description },
        { icon: Calendar, title: t.features.feature4.title, description: t.features.feature4.description },
        { icon: Globe, title: t.features.feature5?.title || 'Multi-Language', description: t.features.feature5?.description || 'Generate content in 50+ languages.' },
        { icon: Shield, title: t.features.feature6?.title || 'Quality Guarantee', description: t.features.feature6?.description || 'Every article passes plagiarism checks.' },
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
        t.faq.q3,
        t.faq.q4,
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

            <div className="min-h-screen bg-surface-50 text-surface-700">
                {/* Header */}
                <header
                    className={`fixed top-0 left-0 right-0 z-50 transition-all duration-300 ${
                        isScrolled
                            ? 'bg-surface-50/85 backdrop-blur-xl border-b border-black/5'
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
                                        className="px-4 py-2 text-[0.9rem] font-medium text-surface-500 hover:text-surface-900 hover:bg-white rounded-lg transition-all"
                                    >
                                        {t.nav.features}
                                    </a>
                                    <a
                                        href="#how-it-works"
                                        className="px-4 py-2 text-[0.9rem] font-medium text-surface-500 hover:text-surface-900 hover:bg-white rounded-lg transition-all"
                                    >
                                        {t.nav.howItWorks}
                                    </a>
                                    <a
                                        href="#pricing"
                                        className="px-4 py-2 text-[0.9rem] font-medium text-surface-500 hover:text-surface-900 hover:bg-white rounded-lg transition-all"
                                    >
                                        {t.nav.pricing}
                                    </a>
                                    <a
                                        href="#faq"
                                        className="px-4 py-2 text-[0.9rem] font-medium text-surface-500 hover:text-surface-900 hover:bg-white rounded-lg transition-all"
                                    >
                                        {t.nav.faq}
                                    </a>
                                </nav>
                            </div>

                            {/* Right: Language Switcher + CTAs */}
                            <div className="hidden lg:flex items-center gap-4">
                                {/* Language Switcher Dropdown */}
                                <div className="relative" ref={langMenuRef}>
                                    <button
                                        onClick={() => setLangMenuOpen(!langMenuOpen)}
                                        className="flex items-center gap-2 px-3 py-2 text-[0.9rem] font-medium text-surface-600 hover:text-surface-900 hover:bg-white/50 rounded-lg transition-all"
                                    >
                                        <Globe className="w-4 h-4" />
                                        <span>{currentLang.label}</span>
                                        <ChevronDown className={`w-4 h-4 transition-transform ${langMenuOpen ? 'rotate-180' : ''}`} />
                                    </button>

                                    {langMenuOpen && (
                                        <div className="absolute right-0 mt-2 w-44 bg-white rounded-xl shadow-lg border border-surface-200 py-2 z-50">
                                            {languages.map((lang) => (
                                                <Link
                                                    key={lang.code}
                                                    href={`/${lang.code}`}
                                                    onClick={() => setLangMenuOpen(false)}
                                                    className={`flex items-center gap-3 px-4 py-2.5 text-[0.9rem] transition-colors ${
                                                        lang.code === locale
                                                            ? 'bg-primary-50 text-primary-600 font-medium'
                                                            : 'text-surface-600 hover:bg-surface-50'
                                                    }`}
                                                >
                                                    <span className="text-base">{lang.flag}</span>
                                                    <span>{lang.name}</span>
                                                    {lang.code === locale && (
                                                        <Check className="w-4 h-4 ml-auto text-primary-500" />
                                                    )}
                                                </Link>
                                            ))}
                                        </div>
                                    )}
                                </div>

                                {canLogin && (
                                    <Link
                                        href={route('login')}
                                        className="px-4 py-2.5 text-[0.9rem] font-medium text-surface-900 hover:text-primary-600 transition-colors"
                                    >
                                        {t.nav.login}
                                    </Link>
                                )}
                                {canRegister && (
                                    <Link
                                        href={route('register')}
                                        className="inline-flex items-center gap-2 px-5 py-2.5 bg-gradient-to-r from-primary-500 to-primary-600 text-white text-[0.9rem] font-semibold rounded-xl shadow-green hover:shadow-green-lg hover:-translate-y-0.5 transition-all"
                                    >
                                        {t.nav.getStarted}
                                        <ArrowRight className="w-4 h-4" />
                                    </Link>
                                )}
                            </div>

                            {/* Mobile menu button */}
                            <button
                                onClick={() => setMobileMenuOpen(!mobileMenuOpen)}
                                className="lg:hidden p-2 text-surface-600"
                            >
                                {mobileMenuOpen ? <X className="w-6 h-6" /> : <Menu className="w-6 h-6" />}
                            </button>
                        </div>
                    </div>

                    {/* Mobile menu */}
                    {mobileMenuOpen && (
                        <div className="lg:hidden bg-white border-t border-surface-200">
                            <div className="px-6 py-4 space-y-2">
                                <a href="#features" className="block py-3 text-surface-600 font-medium">
                                    {t.nav.features}
                                </a>
                                <a href="#how-it-works" className="block py-3 text-surface-600 font-medium">
                                    {t.nav.howItWorks}
                                </a>
                                <a href="#pricing" className="block py-3 text-surface-600 font-medium">
                                    {t.nav.pricing}
                                </a>
                                <a href="#faq" className="block py-3 text-surface-600 font-medium">
                                    {t.nav.faq}
                                </a>
                                {/* Mobile Language Switcher */}
                                <div className="py-3 border-t border-surface-200 mt-2">
                                    <p className="text-xs font-medium text-surface-400 uppercase tracking-wide mb-2">Language</p>
                                    <div className="flex flex-col gap-1">
                                        {languages.map((lang) => (
                                            <Link
                                                key={lang.code}
                                                href={`/${lang.code}`}
                                                className={`flex items-center gap-3 px-3 py-2.5 rounded-lg transition-all ${
                                                    lang.code === locale
                                                        ? 'bg-primary-50 text-primary-600 font-medium'
                                                        : 'text-surface-600 hover:bg-surface-50'
                                                }`}
                                            >
                                                <span className="text-lg">{lang.flag}</span>
                                                <span>{lang.name}</span>
                                                {lang.code === locale && (
                                                    <Check className="w-4 h-4 ml-auto text-primary-500" />
                                                )}
                                            </Link>
                                        ))}
                                    </div>
                                </div>
                                <div className="pt-4 border-t border-surface-200 space-y-3">
                                    {canLogin && (
                                        <Link href={route('login')} className="block py-2 text-surface-900 font-medium">
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
                <section className="pt-32 pb-20 lg:pt-40 lg:pb-24">
                    <div className="max-w-[1200px] mx-auto px-8">
                        <div className="grid lg:grid-cols-2 gap-16 items-center">
                            {/* Left: Content */}
                            <div>
                                <AnimatedSection>
                                    <div className="inline-flex items-center gap-2 px-4 py-2 bg-primary-50 text-primary-600 text-[0.85rem] font-semibold rounded-full mb-6">
                                        <Zap className="w-4 h-4" />
                                        {t.hero.badge}
                                    </div>
                                </AnimatedSection>

                                <AnimatedSection delay={100}>
                                    <h1 className="font-display text-[3.75rem] font-bold leading-[1.1] text-surface-900 mb-6 tracking-tight">
                                        {t.hero.title}{' '}
                                        <span className="text-primary-500">{t.hero.titleAccent}</span>
                                    </h1>
                                </AnimatedSection>

                                <AnimatedSection delay={150}>
                                    <p className="text-[1.2rem] text-surface-500 leading-relaxed mb-8 max-w-[480px]">
                                        {t.hero.subtitle}
                                    </p>
                                </AnimatedSection>

                                <AnimatedSection delay={200}>
                                    <div className="flex flex-wrap items-center gap-4 mb-10">
                                        <Link
                                            href={route('register')}
                                            className="inline-flex items-center gap-2 px-8 py-4 bg-gradient-to-r from-primary-500 to-primary-600 text-white text-base font-semibold rounded-xl shadow-green hover:shadow-green-lg hover:-translate-y-0.5 transition-all"
                                        >
                                            {t.hero.cta}
                                            <ArrowRight className="w-5 h-5" />
                                        </Link>
                                        <a
                                            href="#how-it-works"
                                            className="inline-flex items-center gap-2 px-6 py-4 text-surface-900 text-base font-medium hover:text-primary-600 transition-colors"
                                        >
                                            <Play className="w-5 h-5" />
                                            {t.hero.ctaSecondary}
                                        </a>
                                    </div>
                                </AnimatedSection>

                                <AnimatedSection delay={250}>
                                    <div className="flex gap-10 pt-6 border-t border-surface-200">
                                        <div>
                                            <div className="font-display text-[1.75rem] font-bold text-surface-900">50K+</div>
                                            <div className="text-[0.9rem] text-surface-500">{t.hero.stats.articles}</div>
                                        </div>
                                        <div>
                                            <div className="font-display text-[1.75rem] font-bold text-surface-900">2.5K+</div>
                                            <div className="text-[0.9rem] text-surface-500">{t.hero.stats.users || 'Happy users'}</div>
                                        </div>
                                        <div>
                                            <div className="font-display text-[1.75rem] font-bold text-surface-900">85%</div>
                                            <div className="text-[0.9rem] text-surface-500">{t.hero.stats.ranking || 'Ranking boost'}</div>
                                        </div>
                                    </div>
                                </AnimatedSection>
                            </div>

                            {/* Right: Bento Grid */}
                            <AnimatedSection delay={300} className="hidden lg:block">
                                <div className="grid grid-cols-2 gap-4">
                                    <div className="bg-white rounded-2xl p-6 shadow-md border border-black/5 hover:-translate-y-1 hover:shadow-lg transition-all">
                                        <div className="w-10 h-10 bg-primary-100 rounded-xl flex items-center justify-center text-primary-600 mb-4">
                                            <FileText className="w-5 h-5" />
                                        </div>
                                        <div className="text-[0.9rem] font-medium text-surface-900 mb-1">Articles Today</div>
                                        <div className="font-display text-[2rem] font-bold text-primary-500">12</div>
                                    </div>
                                    <div className="bg-white rounded-2xl p-6 shadow-md border border-black/5 hover:-translate-y-1 hover:shadow-lg transition-all">
                                        <div className="w-10 h-10 bg-primary-100 rounded-xl flex items-center justify-center text-primary-600 mb-4">
                                            <TrendingUp className="w-5 h-5" />
                                        </div>
                                        <div className="text-[0.9rem] font-medium text-surface-900 mb-1">Avg. Position</div>
                                        <div className="font-display text-[2rem] font-bold text-primary-500">#4.2</div>
                                    </div>
                                    <div className="col-span-2 bg-gradient-to-br from-primary-50 to-primary-100/50 rounded-2xl p-6 shadow-md border border-primary-200/50 hover:-translate-y-1 hover:shadow-lg transition-all">
                                        <div className="text-[0.9rem] font-medium text-surface-900 mb-1">Organic Traffic Growth</div>
                                        <div className="text-[0.85rem] text-surface-500 mb-4">Last 7 days • +34% from previous week</div>
                                        <div className="flex items-end gap-2 h-[60px]">
                                            {[40, 55, 45, 70, 60, 85, 95].map((height, i) => (
                                                <div
                                                    key={i}
                                                    className={`flex-1 rounded ${i >= 5 ? 'bg-primary-500' : 'bg-primary-200'}`}
                                                    style={{ height: `${height}%` }}
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
                <section className="py-12 border-y border-surface-200">
                    <div className="max-w-[1200px] mx-auto px-8">
                        <AnimatedSection>
                            <div className="flex flex-col md:flex-row items-center justify-between gap-8">
                                <span className="text-[0.9rem] font-medium text-surface-400">{t.socialProof.title}</span>
                                <div className="flex flex-wrap items-center justify-center gap-12">
                                    {['Acme Inc', 'TechCorp', 'StartupXYZ', 'MediaGroup', 'GlobalCo'].map((company) => (
                                        <span key={company} className="font-display text-xl font-semibold text-surface-300">
                                            {company}
                                        </span>
                                    ))}
                                </div>
                            </div>
                        </AnimatedSection>
                    </div>
                </section>

                {/* Problem/Solution */}
                <section className="py-24">
                    <div className="max-w-[1200px] mx-auto px-8">
                        <AnimatedSection>
                            <div className="text-center mb-16">
                                <span className="inline-flex items-center px-4 py-1.5 bg-primary-50 text-primary-600 text-[0.8rem] font-semibold rounded-full uppercase tracking-wide mb-4">
                                    {t.problem.label}
                                </span>
                                <h2 className="font-display text-display-sm font-bold text-surface-900 mb-4">
                                    {t.problem.title}
                                </h2>
                                <p className="text-lg text-surface-500 max-w-[600px] mx-auto">
                                    {t.problem.subtitle}
                                </p>
                            </div>
                        </AnimatedSection>

                        <div className="grid md:grid-cols-3 gap-8 items-start">
                            {/* Problem Card */}
                            <AnimatedSection delay={100}>
                                <div className="bg-gradient-to-br from-red-50 to-white rounded-[20px] p-10 border border-red-200 shadow-md">
                                    <h3 className="flex items-center gap-3 font-display text-[1.35rem] font-semibold text-red-600 mb-6">
                                        <X className="w-6 h-6" />
                                        {t.problem.cardTitle || 'Without SEO Autopilot'}
                                    </h3>
                                    <ul className="space-y-4">
                                        {(t.problem.pains || ['Hours spent researching keywords', 'Expensive freelance writers', 'Inconsistent content quality', 'Slow publishing schedule', 'No SEO optimization']).map((pain: string, i: number) => (
                                            <li key={i} className="flex items-start gap-3 text-[0.95rem] text-surface-700 pb-4 border-b border-surface-200 last:border-0 last:pb-0">
                                                <span className="text-red-500 font-semibold">✕</span>
                                                {pain}
                                            </li>
                                        ))}
                                    </ul>
                                </div>
                            </AnimatedSection>

                            {/* Arrow */}
                            <div className="hidden md:flex items-center justify-center pt-20">
                                <ArrowRight className="w-10 h-10 text-primary-500" />
                            </div>

                            {/* Solution Card */}
                            <AnimatedSection delay={200}>
                                <div className="bg-gradient-to-br from-primary-50 to-white rounded-[20px] p-10 border border-primary-200 shadow-md">
                                    <h3 className="flex items-center gap-3 font-display text-[1.35rem] font-semibold text-primary-600 mb-6">
                                        <Check className="w-6 h-6" />
                                        {t.solution.cardTitle || 'With SEO Autopilot'}
                                    </h3>
                                    <ul className="space-y-4">
                                        {(t.solution.points || ['AI finds winning keywords', 'Unlimited content generation', 'Consistent, high-quality output', 'Publish daily on autopilot', 'Built-in SEO best practices']).map((point: string, i: number) => (
                                            <li key={i} className="flex items-start gap-3 text-[0.95rem] text-surface-700 pb-4 border-b border-surface-200 last:border-0 last:pb-0">
                                                <span className="text-primary-500 font-semibold">✓</span>
                                                {point}
                                            </li>
                                        ))}
                                    </ul>
                                </div>
                            </AnimatedSection>
                        </div>
                    </div>
                </section>

                {/* Features */}
                <section id="features" className="py-24 bg-surface-100">
                    <div className="max-w-[1200px] mx-auto px-8">
                        <AnimatedSection>
                            <div className="text-center mb-16">
                                <span className="inline-flex items-center px-4 py-1.5 bg-primary-50 text-primary-600 text-[0.8rem] font-semibold rounded-full uppercase tracking-wide mb-4">
                                    {t.features.label}
                                </span>
                                <h2 className="font-display text-display-sm font-bold text-surface-900 mb-4">
                                    {t.features.title}
                                </h2>
                                <p className="text-lg text-surface-500 max-w-[600px] mx-auto">
                                    {t.features.subtitle}
                                </p>
                            </div>
                        </AnimatedSection>

                        <div className="grid md:grid-cols-2 lg:grid-cols-3 gap-6">
                            {features.map((feature, i) => (
                                <AnimatedSection key={i} delay={i * 100}>
                                    <div className="bg-white rounded-2xl p-8 shadow-sm border border-surface-200 hover:-translate-y-1 hover:shadow-lg hover:border-primary-200 transition-all">
                                        <div className="w-12 h-12 bg-gradient-to-br from-primary-50 to-primary-100 rounded-xl flex items-center justify-center text-primary-600 mb-5">
                                            <feature.icon className="w-6 h-6" />
                                        </div>
                                        <h3 className="font-display text-lg font-semibold text-surface-900 mb-2">
                                            {feature.title}
                                        </h3>
                                        <p className="text-[0.95rem] text-surface-500 leading-relaxed">
                                            {feature.description}
                                        </p>
                                    </div>
                                </AnimatedSection>
                            ))}
                        </div>
                    </div>
                </section>

                {/* How It Works */}
                <section id="how-it-works" className="py-24">
                    <div className="max-w-[1200px] mx-auto px-8">
                        <AnimatedSection>
                            <div className="text-center mb-16">
                                <span className="inline-flex items-center px-4 py-1.5 bg-primary-50 text-primary-600 text-[0.8rem] font-semibold rounded-full uppercase tracking-wide mb-4">
                                    {t.howItWorks.label}
                                </span>
                                <h2 className="font-display text-display-sm font-bold text-surface-900 mb-4">
                                    {t.howItWorks.title}
                                </h2>
                                <p className="text-lg text-surface-500 max-w-[600px] mx-auto">
                                    {t.howItWorks.subtitle}
                                </p>
                            </div>
                        </AnimatedSection>

                        <div className="grid md:grid-cols-2 lg:grid-cols-4 gap-8">
                            {steps.map((step, i) => (
                                <AnimatedSection key={i} delay={i * 100}>
                                    <div className="text-center relative">
                                        {i < steps.length - 1 && (
                                            <div className="hidden lg:block absolute top-7 left-1/2 w-full h-0.5 bg-gradient-to-r from-primary-200 to-primary-500" />
                                        )}
                                        <div className="relative w-14 h-14 bg-gradient-to-br from-primary-500 to-primary-600 text-white rounded-full flex items-center justify-center font-display text-2xl font-bold mx-auto mb-5 shadow-green">
                                            {step.number}
                                        </div>
                                        <h3 className="font-display text-lg font-semibold text-surface-900 mb-2">
                                            {step.title}
                                        </h3>
                                        <p className="text-[0.9rem] text-surface-500">
                                            {step.description}
                                        </p>
                                    </div>
                                </AnimatedSection>
                            ))}
                        </div>
                    </div>
                </section>

                {/* Pricing */}
                <section id="pricing" className="py-24 bg-surface-100">
                    <div className="max-w-[1000px] mx-auto px-8">
                        <AnimatedSection>
                            <div className="text-center mb-16">
                                <span className="inline-flex items-center px-4 py-1.5 bg-primary-50 text-primary-600 text-[0.8rem] font-semibold rounded-full uppercase tracking-wide mb-4">
                                    {t.pricing.label}
                                </span>
                                <h2 className="font-display text-display-sm font-bold text-surface-900 mb-4">
                                    {t.pricing.title}
                                </h2>
                                <p className="text-lg text-surface-500 max-w-[600px] mx-auto">
                                    {t.pricing.subtitle}
                                </p>
                            </div>
                        </AnimatedSection>

                        <div className="grid lg:grid-cols-3 gap-6">
                            {pricingPlans.map((plan, i) => (
                                <AnimatedSection key={i} delay={i * 100}>
                                    <div
                                        className={`relative bg-white rounded-[20px] p-10 shadow-md transition-all hover:-translate-y-1 ${
                                            plan.featured
                                                ? 'border-2 border-primary-500 shadow-lg ring-4 ring-primary-100'
                                                : 'border border-surface-200'
                                        }`}
                                    >
                                        {plan.popular && (
                                            <div className="absolute -top-3 left-1/2 -translate-x-1/2 px-4 py-1 bg-gradient-to-r from-primary-500 to-primary-600 text-white text-xs font-semibold rounded-full uppercase tracking-wide">
                                                {plan.popular}
                                            </div>
                                        )}
                                        <div className="mb-6">
                                            <h3 className="font-display text-[1.35rem] font-semibold text-surface-900 mb-1">
                                                {plan.name}
                                            </h3>
                                            <p className="text-[0.9rem] text-surface-500">{plan.description}</p>
                                        </div>
                                        <div className="mb-6">
                                            <span className="font-display text-[3rem] font-bold text-surface-900">
                                                ${plan.price}
                                            </span>
                                            <span className="text-surface-500">{t.pricing.perMonth}</span>
                                        </div>
                                        <ul className="space-y-3 mb-8">
                                            {plan.features.map((feature: string, j: number) => (
                                                <li key={j} className="flex items-center gap-3 text-[0.95rem] text-surface-700">
                                                    <Check className="w-5 h-5 text-primary-500 flex-shrink-0" />
                                                    {feature}
                                                </li>
                                            ))}
                                        </ul>
                                        <Link
                                            href={route('register')}
                                            className={`block w-full text-center py-3.5 font-semibold rounded-xl transition-all ${
                                                plan.featured
                                                    ? 'bg-gradient-to-r from-primary-500 to-primary-600 text-white shadow-green hover:shadow-green-lg'
                                                    : 'border-2 border-surface-200 text-surface-900 hover:border-primary-500 hover:text-primary-600'
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

                {/* Testimonials */}
                <section className="py-24">
                    <div className="max-w-[1200px] mx-auto px-8">
                        <AnimatedSection>
                            <div className="text-center mb-16">
                                <span className="inline-flex items-center px-4 py-1.5 bg-primary-50 text-primary-600 text-[0.8rem] font-semibold rounded-full uppercase tracking-wide mb-4">
                                    {t.testimonials.label}
                                </span>
                                <h2 className="font-display text-display-sm font-bold text-surface-900 mb-4">
                                    {t.testimonials.title}
                                </h2>
                                <p className="text-lg text-surface-500 max-w-[600px] mx-auto">
                                    {t.testimonials.subtitle}
                                </p>
                            </div>
                        </AnimatedSection>

                        <div className="grid md:grid-cols-3 gap-6">
                            {testimonials.map((testimonial, i) => (
                                <AnimatedSection key={i} delay={i * 100}>
                                    <div className="bg-white rounded-2xl p-8 shadow-sm border border-surface-200">
                                        <div className="flex gap-1 mb-4 text-yellow-400">
                                            {[...Array(5)].map((_, j) => (
                                                <Star key={j} className="w-5 h-5 fill-current" />
                                            ))}
                                        </div>
                                        <p className="text-surface-700 leading-relaxed mb-6">
                                            "{testimonial.quote}"
                                        </p>
                                        <div className="flex items-center gap-3">
                                            <div className="w-11 h-11 bg-gradient-to-br from-primary-100 to-primary-200 rounded-full flex items-center justify-center font-semibold text-primary-700">
                                                {testimonial.author
                                                    .split(' ')
                                                    .map((n: string) => n[0])
                                                    .join('')}
                                            </div>
                                            <div>
                                                <div className="font-semibold text-surface-900">{testimonial.author}</div>
                                                <div className="text-[0.85rem] text-surface-500">
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

                {/* FAQ */}
                <section id="faq" className="py-24 bg-surface-100">
                    <div className="max-w-[900px] mx-auto px-8">
                        <AnimatedSection>
                            <div className="text-center mb-16">
                                <span className="inline-flex items-center px-4 py-1.5 bg-primary-50 text-primary-600 text-[0.8rem] font-semibold rounded-full uppercase tracking-wide mb-4">
                                    {t.faq.label}
                                </span>
                                <h2 className="font-display text-display-sm font-bold text-surface-900">
                                    {t.faq.title}
                                </h2>
                            </div>
                        </AnimatedSection>

                        <div className="grid md:grid-cols-2 gap-6">
                            {faqs.map((faq, i) => (
                                <AnimatedSection key={i} delay={i * 50}>
                                    <div className="bg-white rounded-xl p-6 shadow-sm">
                                        <h3 className="font-display text-[1.05rem] font-semibold text-surface-900 mb-3">
                                            {faq.question}
                                        </h3>
                                        <p className="text-[0.95rem] text-surface-500 leading-relaxed">
                                            {faq.answer}
                                        </p>
                                    </div>
                                </AnimatedSection>
                            ))}
                        </div>
                    </div>
                </section>

                {/* CTA */}
                <section className="py-24">
                    <div className="max-w-[1200px] mx-auto px-8">
                        <AnimatedSection>
                            <div className="bg-surface-900 rounded-3xl p-16 text-center relative overflow-hidden">
                                {/* Background glow */}
                                <div className="absolute inset-0 opacity-15">
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
                                        className="inline-flex items-center gap-2 px-10 py-4 bg-gradient-to-r from-primary-500 to-primary-600 text-white text-base font-semibold rounded-xl shadow-green hover:shadow-green-lg hover:-translate-y-0.5 transition-all"
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
