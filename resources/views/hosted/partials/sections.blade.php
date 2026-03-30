@if (!empty($sections))
    <div class="hosted-section-stack">
        @foreach ($sections as $section)
            @switch($section['type'] ?? null)
                @case(\App\Models\HostedPage::SECTION_RICH_TEXT)
                    <section class="section-shell">
                        @if (!empty($section['heading']))
                            <h2 class="page-title" style="font-size:clamp(1.5rem, 3vw, 2.4rem); margin-bottom:16px;">{{ $section['heading'] }}</h2>
                        @endif
                        <div class="content" style="padding:0; background:none; border:none; box-shadow:none;">
                            {!! $section['body_html'] ?? '' !!}
                        </div>
                    </section>
                    @break

                @case(\App\Models\HostedPage::SECTION_HERO)
                    <section class="section-shell hero-section">
                        @if (!empty($section['eyebrow']))
                            <span class="eyebrow">{{ $section['eyebrow'] }}</span>
                        @endif
                        @if (!empty($section['title']))
                            <h2 class="page-title" style="font-size:clamp(2rem, 4vw, 3.4rem); margin:14px 0 12px;">{{ $section['title'] }}</h2>
                        @endif
                        @if (!empty($section['body']))
                            <p class="muted" style="font-size:1.05rem; max-width:70ch;">{{ $section['body'] }}</p>
                        @endif
                        @if ((!empty($section['cta_label']) && !empty($section['cta_href'])) || (!empty($section['secondary_cta_label']) && !empty($section['secondary_cta_href'])))
                            <div style="display:flex; flex-wrap:wrap; gap:12px; margin-top:20px;">
                                @if (!empty($section['cta_label']) && !empty($section['cta_href']))
                                    <a
                                        href="{{ $section['cta_href'] }}"
                                        class="section-cta"
                                        @if (!empty($section['cta_is_external']))
                                            target="_blank" rel="noopener noreferrer"
                                        @endif
                                    >
                                        {{ $section['cta_label'] }}
                                    </a>
                                @endif
                                @if (!empty($section['secondary_cta_label']) && !empty($section['secondary_cta_href']))
                                    <a
                                        href="{{ $section['secondary_cta_href'] }}"
                                        class="section-cta-secondary"
                                        @if (!empty($section['secondary_cta_is_external']))
                                            target="_blank" rel="noopener noreferrer"
                                        @endif
                                    >
                                        {{ $section['secondary_cta_label'] }}
                                    </a>
                                @endif
                            </div>
                        @endif
                    </section>
                    @break

                @case(\App\Models\HostedPage::SECTION_CALLOUT)
                    <section class="section-shell callout-section">
                        @if (!empty($section['eyebrow']))
                            <span class="eyebrow">{{ $section['eyebrow'] }}</span>
                        @endif
                        @if (!empty($section['title']))
                            <h2 class="page-title" style="font-size:clamp(1.8rem, 3vw, 2.8rem); margin:14px 0 12px;">{{ $section['title'] }}</h2>
                        @endif
                        @if (!empty($section['body']))
                            <p class="muted" style="font-size:1rem; max-width:70ch;">{{ $section['body'] }}</p>
                        @endif
                        @if (!empty($section['cta_label']) && !empty($section['cta_href']))
                            <a
                                href="{{ $section['cta_href'] }}"
                                class="section-cta"
                                @if (!empty($section['cta_is_external']))
                                    target="_blank" rel="noopener noreferrer"
                                @endif
                            >
                                {{ $section['cta_label'] }}
                            </a>
                        @endif
                    </section>
                    @break

                @case(\App\Models\HostedPage::SECTION_TESTIMONIAL_GRID)
                    <section class="section-shell">
                        @if (!empty($section['title']))
                            <h2 class="page-title" style="font-size:clamp(1.6rem, 3vw, 2.4rem); margin-bottom:10px;">{{ $section['title'] }}</h2>
                        @endif
                        <div class="feature-grid">
                            @foreach ($section['items'] ?? [] as $item)
                                <article class="feature-card testimonial-card">
                                    @if (!empty($item['body']))
                                        <p style="font-size:1rem; line-height:1.7;">“{{ $item['body'] }}”</p>
                                    @endif
                                    @if (!empty($item['title']))
                                        <p class="eyebrow" style="margin-top:16px;">{{ $item['title'] }}</p>
                                    @endif
                                </article>
                            @endforeach
                        </div>
                    </section>
                    @break

                @case(\App\Models\HostedPage::SECTION_STAT_GRID)
                    <section class="section-shell">
                        @if (!empty($section['title']))
                            <h2 class="page-title" style="font-size:clamp(1.6rem, 3vw, 2.4rem); margin-bottom:10px;">{{ $section['title'] }}</h2>
                        @endif
                        <div class="feature-grid">
                            @foreach ($section['items'] ?? [] as $item)
                                <article class="feature-card stat-card">
                                    @if (!empty($item['title']))
                                        <h3 style="margin:0 0 10px; font-size:2rem; line-height:1;">{{ $item['title'] }}</h3>
                                    @endif
                                    @if (!empty($item['body']))
                                        <p class="muted">{{ $item['body'] }}</p>
                                    @endif
                                </article>
                            @endforeach
                        </div>
                    </section>
                    @break

                @case(\App\Models\HostedPage::SECTION_FEATURE_GRID)
                    <section class="section-shell">
                        @if (!empty($section['title']))
                            <h2 class="page-title" style="font-size:clamp(1.6rem, 3vw, 2.4rem); margin-bottom:10px;">{{ $section['title'] }}</h2>
                        @endif
                        <div class="feature-grid">
                            @foreach ($section['items'] ?? [] as $item)
                                <article class="feature-card">
                                    @if (!empty($item['title']))
                                        <h3 style="margin:0 0 10px; font-size:1.1rem;">{{ $item['title'] }}</h3>
                                    @endif
                                    @if (!empty($item['body']))
                                        <p class="muted">{{ $item['body'] }}</p>
                                    @endif
                                </article>
                            @endforeach
                        </div>
                    </section>
                    @break

                @case(\App\Models\HostedPage::SECTION_FAQ)
                    <section class="section-shell">
                        @if (!empty($section['title']))
                            <h2 class="page-title" style="font-size:clamp(1.6rem, 3vw, 2.4rem); margin-bottom:10px;">{{ $section['title'] }}</h2>
                        @endif
                        <div class="faq-list">
                            @foreach ($section['items'] ?? [] as $item)
                                <article class="faq-item">
                                    @if (!empty($item['question']))
                                        <h3 style="margin:0 0 10px; font-size:1.05rem;">{{ $item['question'] }}</h3>
                                    @endif
                                    @if (!empty($item['answer']))
                                        <p class="muted">{{ $item['answer'] }}</p>
                                    @endif
                                </article>
                            @endforeach
                        </div>
                    </section>
                    @break
            @endswitch
        @endforeach
    </div>
@endif
