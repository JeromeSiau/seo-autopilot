<?php

namespace App\Services\Hosted;

use App\Models\HostedPage;
use App\Models\Site;

class HostedPageGenerator
{
    public function __construct(
        private readonly HostedContentSanitizer $sanitizer,
    ) {}

    public function ensureDefaults(Site $site): void
    {
        foreach ($this->defaultsFor($site) as $kind => $page) {
            $site->hostedPages()->firstOrCreate(
                ['kind' => $kind],
                [
                    'slug' => $page['slug'],
                    'title' => $page['title'],
                    'navigation_label' => $page['navigation_label'],
                    'body_html' => $this->sanitizer->sanitize($page['body_html']),
                    'meta_title' => $page['meta_title'],
                    'meta_description' => $page['meta_description'],
                    'show_in_sitemap' => $page['show_in_sitemap'],
                    'show_in_feed' => $page['show_in_feed'],
                    'breadcrumbs_enabled' => $page['breadcrumbs_enabled'],
                    'show_in_navigation' => $page['show_in_navigation'],
                    'sort_order' => $page['sort_order'],
                    'is_published' => true,
                ]
            );
        }
    }

    public function defaultTheme(Site $site): array
    {
        return [
            'brand_name' => $site->name,
            'accent_color' => '#0f766e',
            'surface_color' => '#f8fafc',
            'text_color' => '#0f172a',
            'hero_title' => $site->name,
            'hero_description' => $site->business_description ?: "Insights and articles from {$site->name}.",
            'footer_text' => "Published by {$site->name}",
            'logo_asset_id' => null,
            'social_image_asset_id' => null,
            'logo_url' => null,
            'social_image_url' => null,
            'heading_font' => 'Georgia, serif',
            'body_font' => 'system-ui, sans-serif',
            'social_links' => [],
        ];
    }

    private function defaultsFor(Site $site): array
    {
        $description = $site->business_description ?: "Welcome to {$site->name}.";
        $audience = $site->target_audience ?: 'our readers and customers';

        return [
            HostedPage::KIND_HOME => [
                'slug' => 'home',
                'title' => 'Home',
                'navigation_label' => 'Home',
                'meta_title' => $site->name,
                'meta_description' => str($description)->limit(150)->value(),
                'show_in_sitemap' => true,
                'show_in_feed' => false,
                'breadcrumbs_enabled' => true,
                'show_in_navigation' => true,
                'sort_order' => 0,
                'body_html' => <<<HTML
<div class="hero-copy">
    <p>{$description}</p>
    <p>We publish practical content for {$audience}.</p>
</div>
HTML,
            ],
            HostedPage::KIND_ABOUT => [
                'slug' => 'about',
                'title' => 'About',
                'navigation_label' => 'About',
                'meta_title' => "About {$site->name}",
                'meta_description' => "Learn more about {$site->name}.",
                'show_in_sitemap' => true,
                'show_in_feed' => false,
                'breadcrumbs_enabled' => true,
                'show_in_navigation' => true,
                'sort_order' => 200,
                'body_html' => <<<HTML
<p>{$site->name} exists to help {$audience} with clear, useful content.</p>
<p>{$description}</p>
HTML,
            ],
            HostedPage::KIND_LEGAL => [
                'slug' => 'legal',
                'title' => 'Legal',
                'navigation_label' => 'Legal',
                'meta_title' => "{$site->name} legal information",
                'meta_description' => "Legal information and publisher details for {$site->name}.",
                'show_in_sitemap' => false,
                'show_in_feed' => false,
                'breadcrumbs_enabled' => true,
                'show_in_navigation' => false,
                'sort_order' => 900,
                'body_html' => <<<HTML
<p>This website is published by {$site->name}.</p>
<p>Please replace this placeholder with your company details, terms, privacy information, and legal notices.</p>
HTML,
            ],
        ];
    }
}
