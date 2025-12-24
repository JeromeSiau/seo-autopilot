import { generateJSON } from '../shared/llm.js';

// HTML escape function to prevent XSS
function escapeHtml(text) {
    const htmlEntities = {
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#39;',
    };
    return String(text).replace(/[&<>"']/g, char => htmlEntities[char]);
}

// Validate URL is safe (http/https only)
function isValidUrl(url) {
    if (typeof url !== 'string' || !url.trim()) return false;
    if (url.startsWith('//')) return false;

    try {
        const parsed = new URL(url);
        return parsed.protocol === 'http:' || parsed.protocol === 'https:';
    } catch {
        return false;
    }
}

export async function findLinkOpportunities(content, sitePages) {
    // Create a summary of available pages for the LLM
    const pagesContext = sitePages.map(p =>
        `- "${p.title}" (${p.url}) - ${p.description?.substring(0, 100) || 'No description'}`
    ).join('\n');

    let result;
    try {
        result = await generateJSON(`
            Analyse ce contenu et trouve les opportunités de liens internes vers les pages existantes.

            Contenu de l'article:
            ${content.substring(0, 8000)}

            Pages disponibles pour le linking:
            ${pagesContext}

            Trouve les termes/phrases dans l'article qui pourraient naturellement lier vers ces pages.

            Règles:
            - L'anchor text doit être naturel (pas de bourrage de keywords)
            - Le lien doit apporter de la valeur au lecteur
            - Privilégie les pages avec peu de liens entrants (orphelines)

            Retourne un JSON: {
                "opportunities": [
                    {
                        "anchor_text": "texte exact à lier dans l'article",
                        "target_url": "URL de la page cible",
                        "target_title": "Titre de la page cible",
                        "relevance_score": 1-10,
                        "reason": "Pourquoi ce lien est pertinent"
                    }
                ]
            }
        `, '', { model: 'gpt-4o' });
    } catch (error) {
        console.error('LLM error in findLinkOpportunities:', error.message);
        return [];
    }

    // Validate response structure
    if (!result.opportunities || !Array.isArray(result.opportunities)) {
        console.warn('LLM returned invalid opportunities structure');
        return [];
    }

    return result.opportunities
        .filter(o => o && o.anchor_text && o.target_url && typeof o.relevance_score === 'number')
        .filter(o => isValidUrl(o.target_url)) // Validate URL is safe
        .filter(o => o.relevance_score >= 6)
        .sort((a, b) => b.relevance_score - a.relevance_score);
}

export async function insertLinks(content, opportunities, options = {}) {
    const {
        maxLinksPerSection = 2,
        minWordsBetweenLinks = 300,
        skipIntroWords = 150,
    } = options;

    let linkedContent = content;
    const linksAdded = [];
    const linksSkipped = [];
    const linkPositions = [];

    for (const opp of opportunities) {
        if (/<[^>]*>/.test(opp.anchor_text)) {
            linksSkipped.push({ ...opp, reason: 'Anchor contains HTML' });
            continue;
        }

        const anchorIndex = linkedContent.indexOf(opp.anchor_text);

        if (anchorIndex === -1) {
            linksSkipped.push({ ...opp, reason: 'Anchor text not found' });
            continue;
        }

        const wordsBeforeAnchor = linkedContent.substring(0, anchorIndex).split(/\s+/).length;
        if (wordsBeforeAnchor < skipIntroWords) {
            linksSkipped.push({ ...opp, reason: 'In introduction' });
            continue;
        }

        const tooCloseToOtherLink = linkPositions.some(pos => {
            const wordsBetween = linkedContent.substring(
                Math.min(anchorIndex, pos),
                Math.max(anchorIndex, pos)
            ).split(/\s+/).length;
            return wordsBetween < minWordsBetweenLinks;
        });

        if (tooCloseToOtherLink) {
            linksSkipped.push({ ...opp, reason: 'Too close to another link' });
            continue;
        }

        if (linkedContent.includes(`href="${escapeHtml(opp.target_url)}"`)) {
            linksSkipped.push({ ...opp, reason: 'Page already linked' });
            continue;
        }

        // Escape HTML to prevent XSS
        const safeUrl = escapeHtml(opp.target_url);
        const safeAnchor = escapeHtml(opp.anchor_text);
        const linkHtml = `<a href="${safeUrl}">${safeAnchor}</a>`;

        // Search for unescaped anchor text in content, replace with properly escaped link HTML
        linkedContent = linkedContent.replace(opp.anchor_text, linkHtml);

        linkPositions.push(anchorIndex);
        linksAdded.push({
            anchor_text: opp.anchor_text,
            target_url: opp.target_url,
            target_title: opp.target_title,
            position: anchorIndex,
        });
    }

    return { linkedContent, linksAdded, linksSkipped };
}
