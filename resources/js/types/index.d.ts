export interface User {
    id: number;
    name: string;
    email: string;
    email_verified_at?: string;
    current_team_id?: number;
    current_team?: Team;
}

export interface Team {
    id: number;
    name: string;
    owner_id: number;
    articles_limit: number;
    articles_generated_count: number;
    plan: 'free' | 'starter' | 'pro' | 'enterprise';
}

export interface Site {
    id: number;
    team_id: number;
    domain: string;
    name: string;
    language: string;
    gsc_connected: boolean;
    ga4_connected: boolean;
    keywords_count?: number;
    articles_count?: number;
    created_at: string;
    updated_at: string;
}

export interface Keyword {
    id: number;
    site_id: number;
    keyword: string;
    volume: number | null;
    difficulty: number | null;
    score: number;
    position: number | null;
    impressions: number | null;
    clicks: number | null;
    ctr: number | null;
    source: 'manual' | 'search_console' | 'ai_generated' | 'dataforseo';
    cluster_id: number | null;
    status: 'pending' | 'processing' | 'completed' | 'failed';
    site?: Site;
    article?: Article;
    created_at: string;
}

export interface Article {
    id: number;
    site_id: number;
    keyword_id: number;
    title: string;
    slug: string;
    content: string | null;
    excerpt: string | null;
    meta_title: string | null;
    meta_description: string | null;
    word_count: number;
    status: 'draft' | 'review' | 'approved' | 'published' | 'failed';
    published_url: string | null;
    published_at: string | null;
    published_via: string | null;
    generation_cost: number;
    site?: Site;
    keyword?: Keyword;
    created_at: string;
    updated_at: string;
}

export interface Integration {
    id: number;
    site_id: number;
    type: 'wordpress' | 'webflow' | 'shopify';
    name: string;
    is_active: boolean;
    site?: Site;
    created_at: string;
}

export interface BrandVoice {
    id: number;
    team_id: number;
    name: string;
    tone: string;
    style: string;
    target_audience: string;
    vocabulary_preferences: string[];
    is_default: boolean;
}

export interface AnalyticsData {
    date: string;
    clicks: number;
    impressions: number;
    position: number;
    sessions?: number;
    page_views?: number;
    bounce_rate?: number;
}

export interface DashboardStats {
    total_sites: number;
    total_keywords: number;
    total_articles: number;
    articles_published: number;
    articles_this_month: number;
    total_clicks: number;
    total_impressions: number;
    avg_position: number;
    articles_limit: number;
    articles_used: number;
}

export interface PaginatedData<T> {
    data: T[];
    links: {
        first: string | null;
        last: string | null;
        prev: string | null;
        next: string | null;
    };
    meta: {
        current_page: number;
        from: number | null;
        last_page: number;
        path: string;
        per_page: number;
        to: number | null;
        total: number;
    };
}

export type PageProps<
    T extends Record<string, unknown> = Record<string, unknown>,
> = T & {
    auth: {
        user: User;
    };
    flash?: {
        success?: string;
        error?: string;
    };
};
