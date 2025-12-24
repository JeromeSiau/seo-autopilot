import { generateJSON } from '../shared/llm.js';
import { searchGoogle } from '../research-agent/google-search.js';
import { scrapeUrls } from '../research-agent/content-scraper.js';

export async function verifyClaims(claims) {
    const results = [];

    for (const claim of claims) {
        const verification = await verifySingleClaim(claim);
        results.push({
            original_text: claim.text,
            ...verification
        });
    }

    return results;
}

async function verifySingleClaim(claim) {
    try {
        // Search for verification
        const searchQuery = `${claim.text} source fact check`;
        const urls = await searchGoogle(searchQuery, 5);

        if (urls.length === 0) {
            return {
                status: 'unverifiable',
                reason: 'Aucune source trouvée pour vérifier cette affirmation.'
            };
        }

        // Scrape top results
        const sources = await scrapeUrls(urls.slice(0, 3));
        const validSources = sources.filter(s => s.content && s.content.length > 200);

        if (validSources.length === 0) {
            return {
                status: 'unverifiable',
                reason: 'Impossible d\'extraire le contenu des sources.'
            };
        }

        // Use LLM to verify
        const sourcesText = validSources.map(s =>
            `[${s.title}] (${s.url})\n${s.content.substring(0, 2000)}`
        ).join('\n\n---\n\n');

        const result = await generateJSON(`
            Vérifie cette affirmation avec les sources fournies:

            Affirmation: "${claim.text}"
            Type: ${claim.type}

            Sources:
            ${sourcesText}

            Analyse les sources et détermine si l'affirmation est:
            - "verified": Confirmée par au moins une source fiable
            - "partially_true": Partiellement vraie (nuance nécessaire)
            - "incorrect": Fausse ou erronée (fournis la correction)
            - "unverifiable": Impossible à vérifier avec ces sources

            Retourne un JSON:
            {
                "status": "verified|partially_true|incorrect|unverifiable",
                "reason": "Explication courte",
                "source_url": "URL de la meilleure source (si trouvée)",
                "source_title": "Titre de la source",
                "corrected_text": "Texte corrigé (si incorrect, sinon null)"
            }
        `, '', { model: 'gpt-4o' });

        return result;

    } catch (error) {
        return {
            status: 'unverifiable',
            reason: `Erreur lors de la vérification: ${error.message}`
        };
    }
}
