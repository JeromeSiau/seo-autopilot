import { Head, Link } from '@inertiajs/react';
import { useState, useEffect, useRef } from 'react';
import {
    Sparkles,
    Globe,
    Zap,
    BarChart3,
    Clock,
    AlertTriangle,
    TrendingDown,
    Check,
    ChevronDown,
    ArrowRight,
    Play,
    Star,
    Menu,
    X,
    Languages,
} from 'lucide-react';

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
                transform: isInView ? 'translateY(0)' : 'translateY(30px)',
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
    const [billingPeriod, setBillingPeriod] = useState<'monthly' | 'yearly'>('monthly');

    useEffect(() => {
        const handleScroll = () => {
            setIsScrolled(window.scrollY > 20);
        };
        window.addEventListener('scroll', handleScroll);
        return () => window.removeEventListener('scroll', handleScroll);
    }, []);

    const languages = [
        { code: 'en', label: 'EN', name: 'English' },
        { code: 'fr', label: 'FR', name: 'Français' },
        { code: 'es', label: 'ES', name: 'Español' },
    ];

    const currentLang = languages.find((l) => l.code === locale) || languages[0];

    return (
        <>
            <Head title={t.meta.title}>
                <meta name="description" content={t.meta.description} />
                <meta property="og:title" content={t.meta.title} />
                <meta property="og:description" content={t.meta.description} />
            </Head>

            <div className="min-h-screen bg-surface-50 text-surface-900 overflow-x-hidden">
                {/* Subtle background pattern */}
                <div className="fixed inset-0 bg-[radial-gradient(ellipse_at_top,_var(--tw-gradient-stops))] from-primary-50/50 via-transparent to-transparent pointer-events-none" />

                {/* Navigation */}
                <nav
                    className={`fixed top-0 left-0 right-0 z-50 transition-all duration-300 ${
                        isScrolled
                            ? 'bg-white/80 backdrop-blur-xl shadow-sm border-b border-surface-200/50'
                            : 'bg-transparent'
                    }`}
                >
                    <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                        <div className="flex items-center justify-between h-16 lg:h-20">
                            {/* Logo */}
                            <Link href={`/${locale}`} className="flex items-center gap-2 group">
                                <div className="w-8 h-8 rounded-lg bg-gradient-to-br from-primary-500 to-primary-700 flex items-center justify-center shadow-lg shadow-primary-500/25 group-hover:shadow-primary-500/40 transition-shadow">
                                    <Sparkles className="w-4 h-4 text-white" />
                                </div>
                                <span className="font-display text-xl text-surface-900">
                                    SEO Autopilot
                                </span>
                            </Link>

                            {/* Desktop Navigation */}
                            <div className="hidden lg:flex items-center gap-8">
                                <a
                                    href="#features"
                                    className="text-sm font-medium text-surface-600 hover:text-surface-900 transition-colors"
                                >
                                    {t.nav.features}
                                </a>
                                <a
                                    href="#how-it-works"
                                    className="text-sm font-medium text-surface-600 hover:text-surface-900 transition-colors"
                                >
                                    {t.nav.howItWorks}
                                </a>
                                <a
                                    href="#pricing"
                                    className="text-sm font-medium text-surface-600 hover:text-surface-900 transition-colors"
                                >
                                    {t.nav.pricing}
                                </a>
                                <a
                                    href="#faq"
                                    className="text-sm font-medium text-surface-600 hover:text-surface-900 transition-colors"
                                >
                                    {t.nav.faq}
                                </a>
                            </div>

                            {/* Right side */}
                            <div className="hidden lg:flex items-center gap-4">
                                {/* Language Switcher */}
                                <div className="relative group">
                                    <button className="flex items-center gap-1.5 px-3 py-2 text-sm font-medium text-surface-600 hover:text-surface-900 transition-colors">
                                        <Languages className="w-4 h-4" />
                                        {currentLang.label}
                                        <ChevronDown className="w-3 h-3" />
                                    </button>
                                    <div className="absolute right-0 mt-1 w-40 py-2 bg-white rounded-xl shadow-xl border border-surface-200 opacity-0 invisible group-hover:opacity-100 group-hover:visible transition-all">
                                        {languages.map((lang) => (
                                            <Link
                                                key={lang.code}
                                                href={`/${lang.code}`}
                                                className={`block px-4 py-2 text-sm hover:bg-surface-50 transition-colors ${
                                                    lang.code === locale
                                                        ? 'text-primary-600 font-medium'
                                                        : 'text-surface-600'
                                                }`}
                                            >
                                                {lang.name}
                                            </Link>
                                        ))}
                                    </div>
                                </div>

                                {canLogin && (
                                    <Link
                                        href={route('login')}
                                        className="text-sm font-medium text-surface-600 hover:text-surface-900 transition-colors"
                                    >
                                        {t.nav.login}
                                    </Link>
                                )}
                                {canRegister && (
                                    <Link
                                        href={route('register')}
                                        className="inline-flex items-center gap-2 px-5 py-2.5 bg-surface-900 text-white text-sm font-medium rounded-full hover:bg-surface-800 transition-colors shadow-lg shadow-surface-900/10"
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
                            <div className="px-4 py-4 space-y-3">
                                <a href="#features" className="block py-2 text-surface-600">
                                    {t.nav.features}
                                </a>
                                <a href="#how-it-works" className="block py-2 text-surface-600">
                                    {t.nav.howItWorks}
                                </a>
                                <a href="#pricing" className="block py-2 text-surface-600">
                                    {t.nav.pricing}
                                </a>
                                <a href="#faq" className="block py-2 text-surface-600">
                                    {t.nav.faq}
                                </a>
                                <div className="pt-4 border-t border-surface-200 space-y-3">
                                    {canLogin && (
                                        <Link href={route('login')} className="block py-2 text-surface-600">
                                            {t.nav.login}
                                        </Link>
                                    )}
                                    {canRegister && (
                                        <Link
                                            href={route('register')}
                                            className="block w-full text-center py-3 bg-surface-900 text-white rounded-full"
                                        >
                                            {t.nav.getStarted}
                                        </Link>
                                    )}
                                </div>
                            </div>
                        </div>
                    )}
                </nav>

                {/* Hero Section */}
                <section className="relative pt-32 lg:pt-40 pb-20 lg:pb-32">
                    <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                        <div className="max-w-4xl mx-auto text-center">
                            {/* Badge */}
                            <AnimatedSection>
                                <div className="inline-flex items-center gap-2 px-4 py-2 bg-primary-50 border border-primary-100 rounded-full text-primary-700 text-sm font-medium mb-8">
                                    <Star className="w-4 h-4 fill-primary-500 text-primary-500" />
                                    {t.hero.badge}
                                </div>
                            </AnimatedSection>

                            {/* Headline */}
                            <AnimatedSection delay={100}>
                                <h1 className="font-display text-4xl sm:text-5xl lg:text-display-md text-surface-900 mb-4">
                                    {t.hero.title}
                                </h1>
                            </AnimatedSection>
                            <AnimatedSection delay={150}>
                                <p className="font-display text-4xl sm:text-5xl lg:text-display-md text-primary-600 italic mb-8">
                                    {t.hero.titleAccent}
                                </p>
                            </AnimatedSection>

                            {/* Subtitle */}
                            <AnimatedSection delay={200}>
                                <p className="text-lg lg:text-xl text-surface-600 max-w-2xl mx-auto mb-10 leading-relaxed">
                                    {t.hero.subtitle}
                                </p>
                            </AnimatedSection>

                            {/* CTAs */}
                            <AnimatedSection delay={250}>
                                <div className="flex flex-col sm:flex-row items-center justify-center gap-4">
                                    <Link
                                        href={route('register')}
                                        className="inline-flex items-center gap-2 px-8 py-4 bg-primary-600 text-white text-base font-semibold rounded-full hover:bg-primary-700 transition-all shadow-xl shadow-primary-600/25 hover:shadow-primary-600/40 hover:-translate-y-0.5"
                                    >
                                        {t.hero.cta}
                                        <ArrowRight className="w-5 h-5" />
                                    </Link>
                                    <a
                                        href="#how-it-works"
                                        className="inline-flex items-center gap-2 px-8 py-4 bg-white text-surface-700 text-base font-semibold rounded-full border border-surface-200 hover:border-surface-300 hover:bg-surface-50 transition-all"
                                    >
                                        <Play className="w-5 h-5" />
                                        {t.hero.ctaSecondary}
                                    </a>
                                </div>
                            </AnimatedSection>

                            {/* Stats */}
                            <AnimatedSection delay={300}>
                                <div className="flex flex-wrap justify-center gap-8 lg:gap-16 mt-16 pt-16 border-t border-surface-200">
                                    <div className="text-center">
                                        <div className="text-3xl lg:text-4xl font-bold text-surface-900 mb-1">
                                            50K+
                                        </div>
                                        <div className="text-sm text-surface-500">{t.hero.stats.articles}</div>
                                    </div>
                                    <div className="text-center">
                                        <div className="text-3xl lg:text-4xl font-bold text-surface-900 mb-1">
                                            20+
                                        </div>
                                        <div className="text-sm text-surface-500">{t.hero.stats.languages}</div>
                                    </div>
                                    <div className="text-center">
                                        <div className="text-3xl lg:text-4xl font-bold text-surface-900 mb-1">
                                            98%
                                        </div>
                                        <div className="text-sm text-surface-500">{t.hero.stats.satisfaction}</div>
                                    </div>
                                </div>
                            </AnimatedSection>
                        </div>
                    </div>

                    {/* Decorative elements */}
                    <div className="absolute top-1/4 left-0 w-72 h-72 bg-primary-200/30 rounded-full blur-3xl -translate-x-1/2" />
                    <div className="absolute bottom-0 right-0 w-96 h-96 bg-accent-200/20 rounded-full blur-3xl translate-x-1/2" />
                </section>

                {/* Social Proof */}
                <section className="py-12 bg-white border-y border-surface-100">
                    <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                        <AnimatedSection>
                            <p className="text-center text-sm font-medium text-surface-400 mb-8">
                                {t.socialProof.title}
                            </p>
                            <div className="flex flex-wrap items-center justify-center gap-x-12 gap-y-6 opacity-60 grayscale">
                                {/* Placeholder company logos - replace with actual logos */}
                                {['TechFlow', 'GrowthLabs', 'Nordic Digital', 'Startify', 'CloudBase'].map(
                                    (company, i) => (
                                        <div
                                            key={company}
                                            className="text-xl font-bold text-surface-400 tracking-tight"
                                            style={{ animationDelay: `${i * 100}ms` }}
                                        >
                                            {company}
                                        </div>
                                    )
                                )}
                            </div>
                        </AnimatedSection>
                    </div>
                </section>

                {/* Problem Section */}
                <section className="py-24 lg:py-32 bg-surface-900 text-white relative overflow-hidden">
                    <div className="absolute inset-0 bg-[radial-gradient(ellipse_at_center,_var(--tw-gradient-stops))] from-surface-800 via-surface-900 to-surface-900" />
                    <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 relative">
                        <AnimatedSection>
                            <div className="text-center mb-16">
                                <span className="inline-block px-4 py-1.5 bg-white/10 rounded-full text-sm font-medium text-primary-300 mb-6">
                                    {t.problem.label}
                                </span>
                                <h2 className="font-display text-3xl lg:text-display-sm text-white mb-6">
                                    {t.problem.title}
                                </h2>
                                <p className="text-lg text-surface-300 max-w-2xl mx-auto">
                                    {t.problem.subtitle}
                                </p>
                            </div>
                        </AnimatedSection>

                        <div className="grid md:grid-cols-3 gap-8">
                            {[
                                { icon: Clock, ...t.problem.pain1 },
                                { icon: AlertTriangle, ...t.problem.pain2 },
                                { icon: TrendingDown, ...t.problem.pain3 },
                            ].map((pain, i) => (
                                <AnimatedSection key={i} delay={i * 100}>
                                    <div className="p-8 rounded-2xl bg-white/5 border border-white/10 backdrop-blur-sm hover:bg-white/10 transition-colors">
                                        <div className="w-12 h-12 rounded-xl bg-red-500/20 flex items-center justify-center mb-6">
                                            <pain.icon className="w-6 h-6 text-red-400" />
                                        </div>
                                        <h3 className="text-xl font-semibold text-white mb-3">
                                            {pain.title}
                                        </h3>
                                        <p className="text-surface-400">{pain.description}</p>
                                    </div>
                                </AnimatedSection>
                            ))}
                        </div>
                    </div>
                </section>

                {/* Solution Section */}
                <section className="py-24 lg:py-32 bg-gradient-to-b from-primary-50 to-white">
                    <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                        <AnimatedSection>
                            <div className="text-center mb-16">
                                <span className="inline-block px-4 py-1.5 bg-primary-100 rounded-full text-sm font-medium text-primary-700 mb-6">
                                    {t.solution.label}
                                </span>
                                <h2 className="font-display text-3xl lg:text-display-sm text-surface-900 mb-6">
                                    {t.solution.title}
                                </h2>
                                <p className="text-lg text-surface-600 max-w-2xl mx-auto">
                                    {t.solution.subtitle}
                                </p>
                            </div>
                        </AnimatedSection>

                        <div className="grid md:grid-cols-3 gap-8">
                            {[
                                { icon: Sparkles, color: 'primary', ...t.solution.point1 },
                                { icon: Zap, color: 'accent', ...t.solution.point2 },
                                { icon: Globe, color: 'primary', ...t.solution.point3 },
                            ].map((point, i) => (
                                <AnimatedSection key={i} delay={i * 100}>
                                    <div className="group p-8 rounded-2xl bg-white border border-surface-200 shadow-sm hover:shadow-xl hover:border-primary-200 transition-all duration-300">
                                        <div
                                            className={`w-14 h-14 rounded-2xl flex items-center justify-center mb-6 ${
                                                point.color === 'primary'
                                                    ? 'bg-primary-100 text-primary-600'
                                                    : 'bg-accent-100 text-accent-600'
                                            } group-hover:scale-110 transition-transform`}
                                        >
                                            <point.icon className="w-7 h-7" />
                                        </div>
                                        <h3 className="text-xl font-semibold text-surface-900 mb-3">
                                            {point.title}
                                        </h3>
                                        <p className="text-surface-600 leading-relaxed">{point.description}</p>
                                    </div>
                                </AnimatedSection>
                            ))}
                        </div>
                    </div>
                </section>

                {/* Features Section */}
                <section id="features" className="py-24 lg:py-32 bg-white">
                    <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                        <AnimatedSection>
                            <div className="text-center mb-16">
                                <span className="inline-block px-4 py-1.5 bg-surface-100 rounded-full text-sm font-medium text-surface-600 mb-6">
                                    {t.features.label}
                                </span>
                                <h2 className="font-display text-3xl lg:text-display-sm text-surface-900">
                                    {t.features.title}
                                </h2>
                            </div>
                        </AnimatedSection>

                        <div className="grid md:grid-cols-2 gap-6 lg:gap-8">
                            {[
                                { icon: Sparkles, gradient: 'from-primary-500 to-primary-700', ...t.features.feature1 },
                                { icon: BarChart3, gradient: 'from-blue-500 to-blue-700', ...t.features.feature2 },
                                { icon: Zap, gradient: 'from-accent-500 to-accent-700', ...t.features.feature3 },
                                { icon: Globe, gradient: 'from-emerald-500 to-emerald-700', ...t.features.feature4 },
                            ].map((feature, i) => (
                                <AnimatedSection key={i} delay={i * 100}>
                                    <div className="group relative p-8 rounded-3xl bg-surface-50 border border-surface-100 hover:bg-white hover:border-surface-200 hover:shadow-xl transition-all duration-300 overflow-hidden">
                                        {/* Gradient blob on hover */}
                                        <div
                                            className={`absolute -top-24 -right-24 w-48 h-48 bg-gradient-to-br ${feature.gradient} rounded-full blur-3xl opacity-0 group-hover:opacity-10 transition-opacity duration-500`}
                                        />
                                        <div className="relative">
                                            <div
                                                className={`w-12 h-12 rounded-xl bg-gradient-to-br ${feature.gradient} flex items-center justify-center mb-6 shadow-lg`}
                                            >
                                                <feature.icon className="w-6 h-6 text-white" />
                                            </div>
                                            <h3 className="text-xl font-semibold text-surface-900 mb-3">
                                                {feature.title}
                                            </h3>
                                            <p className="text-surface-600 leading-relaxed">{feature.description}</p>
                                        </div>
                                    </div>
                                </AnimatedSection>
                            ))}
                        </div>
                    </div>
                </section>

                {/* How It Works */}
                <section id="how-it-works" className="py-24 lg:py-32 bg-surface-50">
                    <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                        <AnimatedSection>
                            <div className="text-center mb-16">
                                <span className="inline-block px-4 py-1.5 bg-primary-100 rounded-full text-sm font-medium text-primary-700 mb-6">
                                    {t.howItWorks.label}
                                </span>
                                <h2 className="font-display text-3xl lg:text-display-sm text-surface-900">
                                    {t.howItWorks.title}
                                </h2>
                            </div>
                        </AnimatedSection>

                        <div className="relative">
                            {/* Connection line */}
                            <div className="hidden lg:block absolute top-1/2 left-0 right-0 h-0.5 bg-gradient-to-r from-primary-200 via-primary-400 to-primary-200 -translate-y-1/2" />

                            <div className="grid lg:grid-cols-3 gap-8 lg:gap-12">
                                {[t.howItWorks.step1, t.howItWorks.step2, t.howItWorks.step3].map((step, i) => (
                                    <AnimatedSection key={i} delay={i * 150}>
                                        <div className="relative text-center lg:text-left">
                                            {/* Step number */}
                                            <div className="inline-flex items-center justify-center w-16 h-16 rounded-2xl bg-white border-2 border-primary-200 text-primary-600 font-display text-2xl mb-6 shadow-lg relative z-10">
                                                {step.number}
                                            </div>
                                            <h3 className="text-xl font-semibold text-surface-900 mb-3">
                                                {step.title}
                                            </h3>
                                            <p className="text-surface-600 leading-relaxed">{step.description}</p>
                                        </div>
                                    </AnimatedSection>
                                ))}
                            </div>
                        </div>
                    </div>
                </section>

                {/* Product Preview */}
                <section className="py-24 lg:py-32 bg-white overflow-hidden">
                    <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                        <AnimatedSection>
                            <div className="text-center mb-16">
                                <span className="inline-block px-4 py-1.5 bg-surface-100 rounded-full text-sm font-medium text-surface-600 mb-6">
                                    {t.preview.label}
                                </span>
                                <h2 className="font-display text-3xl lg:text-display-sm text-surface-900 mb-6">
                                    {t.preview.title}
                                </h2>
                                <p className="text-lg text-surface-600 max-w-2xl mx-auto">
                                    {t.preview.subtitle}
                                </p>
                            </div>
                        </AnimatedSection>

                        <AnimatedSection delay={200}>
                            <div className="relative">
                                {/* Browser mockup */}
                                <div className="rounded-2xl bg-surface-900 p-2 shadow-2xl shadow-surface-900/20">
                                    {/* Browser chrome */}
                                    <div className="flex items-center gap-2 px-4 py-3 border-b border-surface-800">
                                        <div className="flex gap-1.5">
                                            <div className="w-3 h-3 rounded-full bg-red-500" />
                                            <div className="w-3 h-3 rounded-full bg-yellow-500" />
                                            <div className="w-3 h-3 rounded-full bg-green-500" />
                                        </div>
                                        <div className="flex-1 flex justify-center">
                                            <div className="px-4 py-1.5 bg-surface-800 rounded-lg text-sm text-surface-400">
                                                app.seoautopilot.com/dashboard
                                            </div>
                                        </div>
                                    </div>
                                    {/* Dashboard preview placeholder */}
                                    <div className="aspect-[16/9] bg-gradient-to-br from-surface-100 to-surface-200 rounded-lg overflow-hidden">
                                        <div className="w-full h-full flex items-center justify-center text-surface-400">
                                            <div className="text-center">
                                                <BarChart3 className="w-16 h-16 mx-auto mb-4 opacity-50" />
                                                <p className="text-lg font-medium">Dashboard Preview</p>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                {/* Floating elements */}
                                <div className="absolute -top-6 -right-6 w-24 h-24 bg-primary-500/10 rounded-full blur-2xl" />
                                <div className="absolute -bottom-6 -left-6 w-32 h-32 bg-accent-500/10 rounded-full blur-2xl" />
                            </div>
                        </AnimatedSection>
                    </div>
                </section>

                {/* Testimonials */}
                <section className="py-24 lg:py-32 bg-surface-900 text-white">
                    <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                        <AnimatedSection>
                            <div className="text-center mb-16">
                                <span className="inline-block px-4 py-1.5 bg-white/10 rounded-full text-sm font-medium text-primary-300 mb-6">
                                    {t.testimonials.label}
                                </span>
                                <h2 className="font-display text-3xl lg:text-display-sm text-white">
                                    {t.testimonials.title}
                                </h2>
                            </div>
                        </AnimatedSection>

                        <div className="grid md:grid-cols-3 gap-8">
                            {[t.testimonials.testimonial1, t.testimonials.testimonial2, t.testimonials.testimonial3].map(
                                (testimonial, i) => (
                                    <AnimatedSection key={i} delay={i * 100}>
                                        <div className="p-8 rounded-2xl bg-white/5 border border-white/10 backdrop-blur-sm h-full flex flex-col">
                                            {/* Stars */}
                                            <div className="flex gap-1 mb-6">
                                                {[...Array(5)].map((_, j) => (
                                                    <Star
                                                        key={j}
                                                        className="w-5 h-5 fill-primary-400 text-primary-400"
                                                    />
                                                ))}
                                            </div>
                                            <blockquote className="text-lg text-surface-200 mb-8 flex-grow italic">
                                                "{testimonial.quote}"
                                            </blockquote>
                                            <div className="flex items-center gap-4">
                                                <div className="w-12 h-12 rounded-full bg-gradient-to-br from-primary-400 to-primary-600 flex items-center justify-center text-white font-semibold">
                                                    {testimonial.author
                                                        .split(' ')
                                                        .map((n: string) => n[0])
                                                        .join('')}
                                                </div>
                                                <div>
                                                    <div className="font-semibold text-white">
                                                        {testimonial.author}
                                                    </div>
                                                    <div className="text-sm text-surface-400">
                                                        {testimonial.role}, {testimonial.company}
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </AnimatedSection>
                                )
                            )}
                        </div>
                    </div>
                </section>

                {/* Pricing */}
                <section id="pricing" className="py-24 lg:py-32 bg-white">
                    <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                        <AnimatedSection>
                            <div className="text-center mb-12">
                                <span className="inline-block px-4 py-1.5 bg-surface-100 rounded-full text-sm font-medium text-surface-600 mb-6">
                                    {t.pricing.label}
                                </span>
                                <h2 className="font-display text-3xl lg:text-display-sm text-surface-900 mb-6">
                                    {t.pricing.title}
                                </h2>
                                <p className="text-lg text-surface-600 max-w-2xl mx-auto mb-8">
                                    {t.pricing.subtitle}
                                </p>

                                {/* Billing toggle */}
                                <div className="inline-flex items-center gap-3 p-1.5 bg-surface-100 rounded-full">
                                    <button
                                        onClick={() => setBillingPeriod('monthly')}
                                        className={`px-5 py-2 rounded-full text-sm font-medium transition-all ${
                                            billingPeriod === 'monthly'
                                                ? 'bg-white text-surface-900 shadow-sm'
                                                : 'text-surface-600 hover:text-surface-900'
                                        }`}
                                    >
                                        {t.pricing.monthly}
                                    </button>
                                    <button
                                        onClick={() => setBillingPeriod('yearly')}
                                        className={`px-5 py-2 rounded-full text-sm font-medium transition-all flex items-center gap-2 ${
                                            billingPeriod === 'yearly'
                                                ? 'bg-white text-surface-900 shadow-sm'
                                                : 'text-surface-600 hover:text-surface-900'
                                        }`}
                                    >
                                        {t.pricing.yearly}
                                        <span className="px-2 py-0.5 bg-primary-100 text-primary-700 text-xs font-semibold rounded-full">
                                            {t.pricing.yearlyDiscount}
                                        </span>
                                    </button>
                                </div>
                            </div>
                        </AnimatedSection>

                        <div className="grid lg:grid-cols-3 gap-8">
                            {[
                                { ...t.pricing.starter, featured: false },
                                { ...t.pricing.pro, featured: true },
                                { ...t.pricing.agency, featured: false },
                            ].map((plan, i) => (
                                <AnimatedSection key={i} delay={i * 100}>
                                    <div
                                        className={`relative p-8 rounded-3xl h-full flex flex-col ${
                                            plan.featured
                                                ? 'bg-surface-900 text-white ring-4 ring-primary-500 ring-offset-4'
                                                : 'bg-surface-50 border border-surface-200'
                                        }`}
                                    >
                                        {plan.popular && (
                                            <div className="absolute -top-4 left-1/2 -translate-x-1/2 px-4 py-1.5 bg-primary-500 text-white text-sm font-semibold rounded-full shadow-lg">
                                                {plan.popular}
                                            </div>
                                        )}
                                        <div className="mb-8">
                                            <h3
                                                className={`text-xl font-semibold mb-2 ${
                                                    plan.featured ? 'text-white' : 'text-surface-900'
                                                }`}
                                            >
                                                {plan.name}
                                            </h3>
                                            <p
                                                className={`text-sm ${
                                                    plan.featured ? 'text-surface-300' : 'text-surface-600'
                                                }`}
                                            >
                                                {plan.description}
                                            </p>
                                        </div>
                                        <div className="mb-8">
                                            <span
                                                className={`text-5xl font-bold ${
                                                    plan.featured ? 'text-white' : 'text-surface-900'
                                                }`}
                                            >
                                                ${billingPeriod === 'yearly' ? Math.round(parseInt(plan.price) * 0.8) : plan.price}
                                            </span>
                                            <span
                                                className={plan.featured ? 'text-surface-400' : 'text-surface-500'}
                                            >
                                                {t.pricing.perMonth}
                                            </span>
                                        </div>
                                        <ul className="space-y-4 mb-8 flex-grow">
                                            {plan.features.map((feature: string, j: number) => (
                                                <li key={j} className="flex items-start gap-3">
                                                    <Check
                                                        className={`w-5 h-5 flex-shrink-0 mt-0.5 ${
                                                            plan.featured ? 'text-primary-400' : 'text-primary-600'
                                                        }`}
                                                    />
                                                    <span
                                                        className={
                                                            plan.featured ? 'text-surface-200' : 'text-surface-600'
                                                        }
                                                    >
                                                        {feature}
                                                    </span>
                                                </li>
                                            ))}
                                        </ul>
                                        <Link
                                            href={route('register')}
                                            className={`w-full py-4 rounded-full font-semibold text-center transition-all ${
                                                plan.featured
                                                    ? 'bg-primary-500 text-white hover:bg-primary-600 shadow-lg shadow-primary-500/25'
                                                    : 'bg-surface-900 text-white hover:bg-surface-800'
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

                {/* FAQ */}
                <section id="faq" className="py-24 lg:py-32 bg-surface-50">
                    <div className="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8">
                        <AnimatedSection>
                            <div className="text-center mb-16">
                                <span className="inline-block px-4 py-1.5 bg-surface-200 rounded-full text-sm font-medium text-surface-600 mb-6">
                                    {t.faq.label}
                                </span>
                                <h2 className="font-display text-3xl lg:text-display-sm text-surface-900">
                                    {t.faq.title}
                                </h2>
                            </div>
                        </AnimatedSection>

                        <div className="space-y-4">
                            {[t.faq.q1, t.faq.q2, t.faq.q3, t.faq.q4, t.faq.q5, t.faq.q6].map((faq, i) => (
                                <AnimatedSection key={i} delay={i * 50}>
                                    <div className="bg-white rounded-2xl border border-surface-200 overflow-hidden">
                                        <button
                                            onClick={() => setOpenFaq(openFaq === i ? null : i)}
                                            className="w-full px-6 py-5 flex items-center justify-between text-left hover:bg-surface-50 transition-colors"
                                        >
                                            <span className="font-semibold text-surface-900 pr-4">
                                                {faq.question}
                                            </span>
                                            <ChevronDown
                                                className={`w-5 h-5 text-surface-400 flex-shrink-0 transition-transform ${
                                                    openFaq === i ? 'rotate-180' : ''
                                                }`}
                                            />
                                        </button>
                                        <div
                                            className={`overflow-hidden transition-all duration-300 ${
                                                openFaq === i ? 'max-h-96' : 'max-h-0'
                                            }`}
                                        >
                                            <p className="px-6 pb-5 text-surface-600 leading-relaxed">
                                                {faq.answer}
                                            </p>
                                        </div>
                                    </div>
                                </AnimatedSection>
                            ))}
                        </div>
                    </div>
                </section>

                {/* Final CTA */}
                <section className="py-24 lg:py-32 bg-gradient-to-br from-primary-600 via-primary-700 to-primary-800 text-white relative overflow-hidden">
                    {/* Background pattern */}
                    <div className="absolute inset-0 opacity-10">
                        <div className="absolute inset-0 bg-[radial-gradient(circle_at_30%_20%,white_1px,transparent_1px)] bg-[length:60px_60px]" />
                    </div>

                    <div className="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 relative text-center">
                        <AnimatedSection>
                            <h2 className="font-display text-3xl lg:text-display-sm text-white mb-6">
                                {t.cta.title}
                            </h2>
                            <p className="text-xl text-primary-100 mb-10 max-w-2xl mx-auto">
                                {t.cta.subtitle}
                            </p>
                            <Link
                                href={route('register')}
                                className="inline-flex items-center gap-2 px-10 py-5 bg-white text-primary-700 text-lg font-semibold rounded-full hover:bg-primary-50 transition-all shadow-2xl shadow-primary-900/20 hover:-translate-y-0.5"
                            >
                                {t.cta.button}
                                <ArrowRight className="w-5 h-5" />
                            </Link>
                            <p className="mt-6 text-primary-200 text-sm">{t.cta.note}</p>
                        </AnimatedSection>
                    </div>
                </section>

                {/* Footer */}
                <footer className="bg-surface-900 text-surface-300 py-16 lg:py-20">
                    <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                        <div className="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-5 gap-8 lg:gap-12 mb-12">
                            {/* Brand */}
                            <div className="col-span-2 lg:col-span-1">
                                <Link href={`/${locale}`} className="flex items-center gap-2 mb-4">
                                    <div className="w-8 h-8 rounded-lg bg-gradient-to-br from-primary-500 to-primary-700 flex items-center justify-center">
                                        <Sparkles className="w-4 h-4 text-white" />
                                    </div>
                                    <span className="font-display text-xl text-white">SEO Autopilot</span>
                                </Link>
                                <p className="text-sm text-surface-400 leading-relaxed">
                                    {t.footer.description}
                                </p>
                            </div>

                            {/* Product */}
                            <div>
                                <h4 className="font-semibold text-white mb-4">{t.footer.product}</h4>
                                <ul className="space-y-3 text-sm">
                                    <li>
                                        <a href="#features" className="hover:text-white transition-colors">
                                            {t.footer.features}
                                        </a>
                                    </li>
                                    <li>
                                        <a href="#pricing" className="hover:text-white transition-colors">
                                            {t.footer.pricing}
                                        </a>
                                    </li>
                                    <li>
                                        <a href="#" className="hover:text-white transition-colors">
                                            {t.footer.integrations}
                                        </a>
                                    </li>
                                    <li>
                                        <a href="#" className="hover:text-white transition-colors">
                                            {t.footer.changelog}
                                        </a>
                                    </li>
                                </ul>
                            </div>

                            {/* Company */}
                            <div>
                                <h4 className="font-semibold text-white mb-4">{t.footer.company}</h4>
                                <ul className="space-y-3 text-sm">
                                    <li>
                                        <a href="#" className="hover:text-white transition-colors">
                                            {t.footer.about}
                                        </a>
                                    </li>
                                    <li>
                                        <a href="#" className="hover:text-white transition-colors">
                                            {t.footer.blog}
                                        </a>
                                    </li>
                                    <li>
                                        <a href="#" className="hover:text-white transition-colors">
                                            {t.footer.careers}
                                        </a>
                                    </li>
                                    <li>
                                        <a href="#" className="hover:text-white transition-colors">
                                            {t.footer.contact}
                                        </a>
                                    </li>
                                </ul>
                            </div>

                            {/* Legal */}
                            <div>
                                <h4 className="font-semibold text-white mb-4">{t.footer.legal}</h4>
                                <ul className="space-y-3 text-sm">
                                    <li>
                                        <a href="#" className="hover:text-white transition-colors">
                                            {t.footer.privacy}
                                        </a>
                                    </li>
                                    <li>
                                        <a href="#" className="hover:text-white transition-colors">
                                            {t.footer.terms}
                                        </a>
                                    </li>
                                    <li>
                                        <a href="#" className="hover:text-white transition-colors">
                                            {t.footer.cookies}
                                        </a>
                                    </li>
                                </ul>
                            </div>
                        </div>

                        {/* Bottom */}
                        <div className="pt-8 border-t border-surface-800 flex flex-col md:flex-row items-center justify-between gap-4">
                            <p className="text-sm text-surface-500">{t.footer.copyright}</p>
                            {/* Language switcher */}
                            <div className="flex items-center gap-4">
                                {languages.map((lang) => (
                                    <Link
                                        key={lang.code}
                                        href={`/${lang.code}`}
                                        className={`text-sm transition-colors ${
                                            lang.code === locale
                                                ? 'text-white font-medium'
                                                : 'text-surface-500 hover:text-white'
                                        }`}
                                    >
                                        {lang.name}
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
