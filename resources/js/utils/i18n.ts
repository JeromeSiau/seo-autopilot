/**
 * Interpolate variables in a translation string
 * Usage: trans(t.sites.keywordsCount, { count: 5 })
 * Template: "{count} keywords" -> "5 keywords"
 */
export function trans(template: string, params: Record<string, string | number>): string {
    if (!template) return '';
    return template.replace(/{(\w+)}/g, (_, key) => String(params[key] ?? ''));
}

/**
 * Simple pluralization helper
 * Usage: plural(count, t.common.article, t.common.articles)
 */
export function plural(count: number, singular: string, pluralForm: string): string {
    return count === 1 ? singular : pluralForm;
}
