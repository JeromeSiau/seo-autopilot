export interface User {
    id: number;
    name: string;
    email: string;
    email_verified_at?: string;
    current_team_id?: number;
    current_team?: Team;
    teams?: UserTeam[];
}

export interface Team {
    id: number;
    name: string;
    owner_id: number;
    articles_limit: number;
    articles_generated_count: number;
    plan: 'free' | 'starter' | 'pro' | 'enterprise';
    is_trial?: boolean;
    trial_ends_at?: string;
}

export interface NotificationSettings {
    email_frequency: 'never' | 'daily' | 'weekly';
    immediate_failures: boolean;
    immediate_quota: boolean;
}

export interface WebhookEndpoint {
    id: number;
    team_id: number;
    url: string;
    events: string[];
    is_active: boolean;
    has_secret: boolean;
    last_error?: string | null;
    last_delivered_at?: string | null;
    created_at?: string;
}

export interface WebhookDelivery {
    id: number;
    webhook_endpoint_id: number;
    endpoint_url?: string | null;
    event_name: string;
    status: 'pending' | 'retrying' | 'success' | 'failed';
    attempt_number?: number | null;
    max_attempts?: number | null;
    response_code?: number | null;
    error_message?: string | null;
    attempted_at?: string | null;
    next_retry_at?: string | null;
    created_at?: string;
}

export interface TeamMember {
    id: number;
    name: string;
    email: string;
    role: 'owner' | 'admin' | 'member';
    joined_at: string;
}

export interface TeamInvitation {
    id: number;
    email: string;
    role: 'admin' | 'member';
    created_at: string;
    expires_at: string;
}

export interface UserTeam {
    id: number;
    name: string;
    role: 'owner' | 'admin' | 'member';
}

export type SiteMode = 'external' | 'hosted';
export type IntegrationType = 'wordpress' | 'webflow' | 'shopify' | 'ghost' | 'hosted';
export type HostedPageKind = 'home' | 'about' | 'legal' | 'custom';

export interface HostedThemeSettings {
    brand_name?: string;
    hero_title?: string;
    hero_description?: string;
    accent_color?: string;
    surface_color?: string;
    text_color?: string;
    logo_asset_id?: number | null;
    social_image_asset_id?: number | null;
    logo_url?: string | null;
    social_image_url?: string | null;
    heading_font?: string;
    body_font?: string;
    footer_text?: string;
    social_links?: Record<string, string>;
}

export interface HostedPageSectionItem {
    title?: string | null;
    body?: string | null;
    question?: string | null;
    answer?: string | null;
    price?: string | null;
    cta_label?: string | null;
    href?: string | null;
    meta?: string | null;
}

export interface HostedPageSection {
    type: 'rich_text' | 'callout' | 'feature_grid' | 'faq' | 'hero' | 'testimonial_grid' | 'stat_grid' | 'cta_banner' | 'pricing_grid';
    heading?: string | null;
    eyebrow?: string | null;
    title?: string | null;
    body_html?: string | null;
    body?: string | null;
    cta_label?: string | null;
    cta_href?: string | null;
    secondary_cta_label?: string | null;
    secondary_cta_href?: string | null;
    items?: HostedPageSectionItem[];
}

export interface SiteHosting {
    id?: number;
    site_id?: number;
    staging_domain?: string | null;
    custom_domain?: string | null;
    canonical_domain?: string | null;
    domain_status: 'none' | 'dns_pending' | 'tenant_pending' | 'ssl_pending' | 'active' | 'error';
    ssl_status: 'none' | 'pending' | 'active' | 'error';
    template_key: 'editorial' | 'magazine' | 'minimal';
    theme_settings?: HostedThemeSettings | null;
    ploi_tenant_id?: string | null;
    staging_certificate_requested_at?: string | null;
    custom_domain_verified_at?: string | null;
    custom_certificate_requested_at?: string | null;
    last_error?: string | null;
    last_exported_at?: string | null;
    created_at?: string;
    updated_at?: string;
}

export interface HostedPage {
    id?: number;
    site_id?: number;
    kind: HostedPageKind;
    slug?: string | null;
    title: string;
    navigation_label?: string | null;
    body_html?: string | null;
    sections?: HostedPageSection[];
    meta_title?: string | null;
    meta_description?: string | null;
    canonical_url?: string | null;
    social_title?: string | null;
    social_description?: string | null;
    social_image_asset_id?: number | null;
    social_image_url?: string | null;
    robots_noindex?: boolean;
    schema_enabled?: boolean;
    show_in_sitemap?: boolean;
    show_in_feed?: boolean;
    breadcrumbs_enabled?: boolean;
    show_in_navigation?: boolean;
    sort_order?: number;
    is_published: boolean;
    created_at?: string;
    updated_at?: string;
}

export interface HostedRedirect {
    id: number;
    site_id: number;
    source_path: string;
    destination_url: string;
    http_status: 301 | 302;
    hit_count: number;
    last_used_at?: string | null;
    created_at?: string;
    updated_at?: string;
}

export interface HostedAuthor {
    id: number;
    site_id: number;
    name: string;
    slug: string;
    bio?: string | null;
    avatar_url?: string | null;
    sort_order: number;
    is_active: boolean;
    created_at?: string;
    updated_at?: string;
}

export interface HostedCategory {
    id: number;
    site_id: number;
    name: string;
    slug: string;
    description?: string | null;
    sort_order: number;
    is_active: boolean;
    created_at?: string;
    updated_at?: string;
}

export interface HostedTag {
    id: number;
    site_id: number;
    name: string;
    slug: string;
    sort_order: number;
    is_active: boolean;
    created_at?: string;
    updated_at?: string;
}

export interface HostedAsset {
    id: number;
    site_id: number;
    type: 'logo' | 'social' | 'image' | 'document';
    name: string;
    disk: string;
    path: string;
    public_url: string;
    export_path: string;
    mime_type?: string | null;
    size_bytes?: number | null;
    alt_text?: string | null;
    source_url?: string | null;
    is_active: boolean;
    created_at?: string;
    updated_at?: string;
}

export interface HostedNavigationItem {
    id: number;
    site_id: number;
    placement: 'header' | 'footer';
    type: 'path' | 'url';
    label: string;
    path?: string | null;
    url?: string | null;
    target: string;
    open_in_new_tab: boolean;
    is_active: boolean;
    sort_order: number;
    created_at?: string;
    updated_at?: string;
}

export interface HostedExportRun {
    id: number;
    status: 'pending' | 'running' | 'completed' | 'failed';
    target_path?: string | null;
    size_bytes?: number | null;
    error_message?: string | null;
    metadata?: Record<string, unknown> | null;
    started_at?: string | null;
    completed_at?: string | null;
    created_at?: string;
}

export interface HostedDeployEvent {
    id: number;
    type: string;
    status: 'info' | 'success' | 'warning' | 'error';
    title: string;
    message?: string | null;
    metadata?: Record<string, unknown> | null;
    occurred_at?: string | null;
    created_at?: string;
}

export interface HostedHealthCheck {
    key: string;
    label: string;
    status: 'neutral' | 'healthy' | 'warning' | 'critical';
    value: string;
    detail?: string | null;
}

export interface HostedDnsCheck {
    domain?: string | null;
    matched: boolean;
    records: string[];
    expected?: {
        type: string;
        value: string | null;
    } | null;
}

export interface HostedHealthSummary {
    overall_status: 'neutral' | 'healthy' | 'warning' | 'critical';
    checks: HostedHealthCheck[];
    dns_check?: HostedDnsCheck | null;
}

export interface BrandAsset {
    id: number;
    site_id: number;
    type: 'pillar_page' | 'offer' | 'faq' | 'proof' | 'case_study' | 'style_sample' | 'claim' | 'policy';
    title: string;
    source_url?: string | null;
    content: string;
    priority: number;
    is_active: boolean;
    metadata?: Record<string, unknown> | null;
    created_at?: string;
    updated_at?: string;
}

export interface BrandRule {
    id: number;
    site_id: number;
    category: 'must_include' | 'must_avoid' | 'tone' | 'persona' | 'cta' | 'compliance';
    label: string;
    value: string;
    priority: number;
    is_active: boolean;
    created_at?: string;
    updated_at?: string;
}

export interface Site {
    id: number;
    team_id: number;
    domain: string;
    mode: SiteMode;
    public_url?: string | null;
    name: string;
    language: string;
    business_description?: string;
    target_audience?: string;
    topics?: string[];
    tone?: string | null;
    writing_style?: string | null;
    vocabulary?: { use?: string[]; avoid?: string[] } | null;
    brand_examples?: string[] | null;
    gsc_connected: boolean;
    ga4_connected: boolean;
    crawl_status: 'pending' | 'running' | 'partial' | 'completed' | 'failed';
    crawl_pages_count: number;
    keywords_count?: number;
    articles_count?: number;
    integrations_count?: number;
    onboarding_completed_at?: string;
    autopilot_status: 'active' | 'paused' | 'not_configured' | 'error';
    articles_per_week?: number;
    articles_in_review?: number;
    articles_this_week?: number;
    onboarding_complete?: boolean;
    hosting?: SiteHosting | null;
    hosted_pages?: HostedPage[];
    hosted_redirects?: HostedRedirect[];
    hosted_authors?: HostedAuthor[];
    hosted_categories?: HostedCategory[];
    hosted_tags?: HostedTag[];
    hosted_assets?: HostedAsset[];
    hosted_navigation_items?: HostedNavigationItem[];
    hosted_export_runs?: HostedExportRun[];
    hosted_deploy_events?: HostedDeployEvent[];
    hosting_health?: HostedHealthSummary | null;
    brand_assets?: BrandAsset[];
    brand_rules?: BrandRule[];
    site_export_available?: boolean;
    dns_expectation?: {
        type: string;
        value: string | null;
    } | null;
    created_at: string;
    updated_at: string;
}

export interface DashboardSite {
    id: number;
    domain: string;
    name: string;
    autopilot_status: 'active' | 'paused' | 'not_configured' | 'error';
    articles_per_week: number;
    articles_in_review: number;
    articles_this_week: number;
    onboarding_complete: boolean;
    is_generating: boolean;
}

export interface Keyword {
    id: number;
    site_id: number;
    keyword: string;
    volume: number | null;
    difficulty: number | null;
    score: number;
    priority: number | null;
    position: number | null;
    impressions: number | null;
    clicks: number | null;
    ctr: number | null;
    source: 'manual' | 'search_console' | 'ai_generated' | 'dataforseo' | 'llm';
    cluster_id: number | null;
    status: 'pending' | 'queued' | 'generating' | 'completed' | 'scheduled' | 'failed' | 'skipped';
    queued_at: string | null;
    processed_at: string | null;
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
    featured_image_url?: string | null;
    hosted_author?: HostedAuthor | null;
    hosted_category?: HostedCategory | null;
    hosted_tags?: HostedTag[];
    word_count: number;
    status: 'draft' | 'generating' | 'review' | 'approved' | 'published' | 'failed';
    published_url: string | null;
    published_at: string | null;
    published_via: IntegrationType | string | null;
    generation_cost: number;
    score?: {
        readiness_score: number;
        brand_fit_score: number;
        seo_score: number;
        citation_score: number;
        internal_link_score: number;
        fact_confidence_score: number;
        warnings: string[];
        checklist: Array<{
            label: string;
            done: boolean;
        }>;
        updated_at?: string;
    } | null;
    citations?: Array<{
        id: number;
        source_type: 'brand' | 'serp' | string;
        title: string;
        url: string | null;
        domain: string | null;
        excerpt: string | null;
        metadata?: Record<string, unknown> | null;
    }>;
    editorial_comments?: Array<{
        id: number;
        body: string;
        resolved_at?: string | null;
        created_at: string;
        updated_at?: string;
        user?: {
            id: number;
            name: string;
            email: string;
        };
    }>;
    assignments?: Array<{
        id: number;
        role: 'writer' | 'reviewer' | 'approver';
        assigned_at?: string | null;
        created_at?: string;
        user?: {
            id: number;
            name: string;
            email: string;
        };
    }>;
    approval_requests?: Array<{
        id: number;
        status: 'pending' | 'approved' | 'rejected';
        decision_note?: string | null;
        decided_at?: string | null;
        created_at: string;
        requested_by: number;
        requested_to: number;
        requested_by_user?: {
            id: number;
            name: string;
            email: string;
        };
        requested_to_user?: {
            id: number;
            name: string;
            email: string;
        };
    }>;
    refresh_recommendations?: Array<{
        id: number;
        site_id: number;
        article_id: number;
        trigger_type: 'position_drop' | 'traffic_drop' | 'ctr_drop' | 'ai_visibility_drop' | 'content_decay' | 'competitor_gap';
        severity: 'low' | 'medium' | 'high';
        reason: string;
        recommended_actions: string[];
        metrics_snapshot?: Record<string, unknown>;
        status: 'open' | 'accepted' | 'dismissed' | 'executed';
        detected_at?: string | null;
        executed_at?: string | null;
    }>;
    activity_timeline?: Array<{
        type: 'comment' | 'assignment' | 'approval' | 'refresh' | string;
        title: string;
        body?: string | null;
        created_at?: string | null;
        actor?: {
            id: number;
            name: string;
            email: string;
        } | null;
    }>;
    latest_refresh_run?: {
        id: number;
        refresh_recommendation_id?: number | null;
        old_score_snapshot?: Record<string, unknown>;
        new_score_snapshot?: {
            readiness_score: number;
            brand_fit_score: number;
            seo_score: number;
            citation_score: number;
            internal_link_score: number;
            fact_confidence_score: number;
            warnings: string[];
            checklist: Array<{
                label: string;
                done: boolean;
            }>;
        };
        status: string;
        summary?: string | null;
        metadata?: {
            draft_title?: string;
            draft_meta_title?: string;
            draft_meta_description?: string;
            draft_content?: string;
            diff?: {
                old_meta_title?: string | null;
                new_meta_title?: string | null;
                old_meta_description?: string | null;
                new_meta_description?: string | null;
                meta_title_changed?: boolean;
                meta_description_changed?: boolean;
                old_word_count?: number | null;
                new_word_count?: number | null;
                word_delta?: number | null;
                sections_added?: string[];
            };
            focus_sections?: string[];
            business_case?: {
                traffic_value?: number | null;
                estimated_conversions?: number | null;
                conversion_source?: string | null;
                roi?: number | null;
                traffic_value_delta?: number | null;
                conversion_delta?: number | null;
                click_delta?: number | null;
                session_delta?: number | null;
                trigger_type?: string | null;
            };
        };
        created_at: string;
    } | null;
    permissions?: {
        update: boolean;
        delete: boolean;
        approve: boolean;
        publish: boolean;
        comment: boolean;
        assign: boolean;
        request_approval: boolean;
    } | null;
    analytics?: {
        total_clicks: number;
        total_impressions: number;
        total_sessions: number;
        total_page_views: number;
        total_conversions: number;
        estimated_conversions: number;
        conversion_source: 'tracked' | 'modeled' | string;
        conversion_rate?: number | null;
        avg_position?: number | null;
        estimated_value: number;
        roi?: number | null;
    };
    business_attribution?: {
        lookback_days: number;
        totals: {
            clicks: number;
            sessions: number;
            page_views: number;
            conversions: number;
            estimated_conversions: number;
            conversion_source: 'tracked' | 'modeled' | string;
            traffic_value: number;
            conversion_rate?: number | null;
        };
        recent: {
            clicks: number;
            sessions: number;
            page_views: number;
            conversions: number;
            estimated_conversions: number;
            conversion_source: 'tracked' | 'modeled' | string;
            traffic_value: number;
            conversion_rate?: number | null;
        };
        previous: {
            clicks: number;
            sessions: number;
            page_views: number;
            conversions: number;
            estimated_conversions: number;
            conversion_source: 'tracked' | 'modeled' | string;
            traffic_value: number;
            conversion_rate?: number | null;
        };
        deltas: {
            clicks: { absolute: number; percentage?: number | null };
            sessions: { absolute: number; percentage?: number | null };
            estimated_conversions: { absolute: number; percentage?: number | null };
            traffic_value: { absolute: number; percentage?: number | null };
        };
        generation_cost: number;
        roi?: number | null;
        performance_label: 'accelerating' | 'at_risk' | 'steady' | string;
    };
    site?: Site;
    keyword?: Keyword;
    created_at: string;
    updated_at: string;
}

export interface Integration {
    id: number;
    site_id: number;
    type: IntegrationType;
    name: string;
    is_active: boolean;
    site?: Site;
    created_at: string;
}

export interface CampaignRun {
    id: number;
    site_id: number;
    created_by?: number | null;
    name: string;
    status: 'pending' | 'dispatched' | 'completed' | 'failed' | string;
    input_type: string;
    payload?: Record<string, unknown>;
    processed_count: number;
    succeeded_count: number;
    failed_count: number;
    started_at?: string | null;
    completed_at?: string | null;
    site?: Site;
    creator?: {
        id: number;
        name: string;
        email: string;
    };
    created_at?: string;
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

export interface AiVisibilityEngineSummary {
    engine: 'ai_overviews' | 'chatgpt' | 'perplexity' | 'gemini';
    avg_visibility_score: number;
    covered_prompts: number;
    total_prompts: number;
}

export interface AiVisibilityPromptRow {
    id: number;
    prompt_id: number;
    site_id: number;
    prompt?: string | null;
    topic?: string | null;
    intent?: string | null;
    engine: 'ai_overviews' | 'chatgpt' | 'perplexity' | 'gemini';
    visibility_score: number;
    previous_visibility_score?: number | null;
    visibility_delta?: number;
    appears: boolean;
    rank_bucket?: string | null;
    checked_at?: string | null;
    prompt_priority?: number;
    source_type?: string | null;
    source_label?: string | null;
    article_id?: number | null;
    article_title?: string | null;
    matched_sources_count?: number;
    related_domains?: string[];
    trend?: 'up' | 'down' | 'flat';
}

export interface AiVisibilityRecommendation {
    type: 'create_article' | 'refresh_article' | 'add_citations';
    title: string;
    reason: string;
    prompt_id: number;
    article_id?: number | null;
    article_title?: string | null;
    severity: 'low' | 'medium' | 'high';
    engine: 'ai_overviews' | 'chatgpt' | 'perplexity' | 'gemini';
    topic?: string | null;
    intent?: string | null;
    visibility_score: number;
    previous_visibility_score?: number | null;
    visibility_delta: number;
    related_domains: string[];
    action_label?: string | null;
}

export interface AiVisibilityTrendPoint {
    date: string;
    avg_visibility_score: number;
    covered_prompts: number;
    total_checks: number;
}

export interface AiVisibilityCompetitorRow {
    domain: string;
    brand_name: string;
    mentions: number;
    average_position: number;
    engines: Array<'ai_overviews' | 'chatgpt' | 'perplexity' | 'gemini'>;
}

export interface AiVisibilitySourceRow {
    source_domain?: string | null;
    source_title?: string | null;
    source_url?: string | null;
    mentions: number;
    average_position: number;
    engines: Array<'ai_overviews' | 'chatgpt' | 'perplexity' | 'gemini'>;
}

export interface AiVisibilityIntentSummary {
    intent: string;
    total_prompts: number;
    covered_prompts: number;
    avg_visibility_score: number;
}

export interface AiVisibilityAlert {
    type: 'coverage_drop' | 'competitor_pressure' | 'source_gap' | 'opportunity';
    severity: 'low' | 'medium' | 'high';
    title: string;
    reason: string;
    prompt_id?: number | null;
    engine?: 'ai_overviews' | 'chatgpt' | 'perplexity' | 'gemini';
    article_id?: number | null;
    article_title?: string | null;
    visibility_delta?: number | null;
    related_domains: string[];
}

export interface AiVisibilityPromptSetSummary {
    id: number;
    key: string;
    name: string;
    description?: string | null;
    is_default: boolean;
    prompt_count: number;
    covered_prompts: number;
    avg_visibility_score: number;
    last_synced_at?: string | null;
}

export interface AiVisibilityAlertHistoryItem extends AiVisibilityAlert {
    id: number;
    status: 'open' | 'resolved';
    first_detected_at?: string | null;
    last_detected_at?: string | null;
    resolved_at?: string | null;
}

export interface AiVisibilityPayload {
    summary: {
        total_prompts: number;
        checked_prompts: number;
        covered_prompts: number;
        avg_visibility_score: number;
        avg_visibility_delta: number;
        declining_checks: number;
        improving_checks: number;
        high_risk_prompts: number;
        last_checked_at?: string | null;
    };
    engines: AiVisibilityEngineSummary[];
    top_prompts: AiVisibilityPromptRow[];
    weakest_prompts: AiVisibilityPromptRow[];
    trend: AiVisibilityTrendPoint[];
    alerts: AiVisibilityAlert[];
    movers: AiVisibilityPromptRow[];
    competitors: AiVisibilityCompetitorRow[];
    sources: AiVisibilitySourceRow[];
    intents: AiVisibilityIntentSummary[];
    recommendations: AiVisibilityRecommendation[];
    prompt_sets: AiVisibilityPromptSetSummary[];
    alert_history: AiVisibilityAlertHistoryItem[];
}

export interface RefreshRecommendationListItem {
    id: number;
    article_id: number;
    article_title?: string | null;
    trigger_type: 'position_drop' | 'traffic_drop' | 'ctr_drop' | 'ai_visibility_drop' | 'content_decay' | 'competitor_gap';
    severity: 'low' | 'medium' | 'high';
    reason: string;
    recommended_actions: string[];
    status: 'open' | 'accepted' | 'executed' | 'dismissed';
    detected_at?: string | null;
}

export interface BusinessSummary {
    lookback_days: number;
    business_model: {
        modeled_conversion_rate: number | null;
        average_conversion_value: number | null;
        source: 'default' | 'custom' | 'mixed' | string;
        sites_with_custom_model?: number;
        sites_count?: number;
    };
    totals: {
        traffic_value: number;
        attributed_revenue: number;
        total_value: number;
        estimated_conversions: number;
        tracked_conversions: number;
        sessions: number;
        clicks: number;
        generation_cost: number;
        conversion_source: 'tracked' | 'modeled' | string;
        net_value: number;
        search_click_share?: number | null;
        roi?: number | null;
    };
    deltas: {
        traffic_value: { absolute: number; percentage?: number | null };
        attributed_revenue: { absolute: number; percentage?: number | null };
        total_value: { absolute: number; percentage?: number | null };
        clicks: { absolute: number; percentage?: number | null };
        sessions: { absolute: number; percentage?: number | null };
        estimated_conversions: { absolute: number; percentage?: number | null };
    };
    search_capture: {
        recent_click_share?: number | null;
        previous_click_share?: number | null;
        tracked_site_clicks: number;
    };
    top_articles: Array<{
        article_id: number;
        title: string;
        traffic_value: number;
        attributed_revenue: number;
        total_value: number;
        estimated_conversions: number;
        search_click_share?: number | null;
        roi?: number | null;
        performance_label: string;
    }>;
    refresh_winners: Array<{
        article_id: number;
        title: string;
        traffic_value_delta: number;
        attributed_revenue_delta: number;
        total_value_delta: number;
        conversion_delta: number;
        latest_refresh_at?: string | null;
    }>;
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

export interface GeneratingArticle {
    id: number;
    title: string;
    site_name: string;
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
    locale: string;
    theme: 'light' | 'dark' | 'system';
    availableLocales: string[];
    translations?: {
        app: Record<string, any>;
    };
    generatingArticles?: GeneratingArticle[];
};
