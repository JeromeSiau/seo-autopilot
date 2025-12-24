import { generateJSON } from '../shared/llm.js';

export async function findLinkOpportunities(content, sitePages) {
    // Create a summary of available pages for the LLM
    const pagesContext = sitePages.map(p =>
        `- "${p.title}" (${p.url}) - ${p.description?.substring(0, 100) || 'No description'}`
    ).join('\n');

    const result = await generateJSON(`
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

    const opportunities = result.opportunities || [];

    // Validate and filter opportunities
    return opportunities
        .filter(o => o && o.anchor_text && o.target_url && typeof o.relevance_score === 'number')
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

    // Track positions of inserted links
    const linkPositions = [];

    for (const opp of opportunities) {
        // Find the anchor text in content
        const anchorIndex = linkedContent.indexOf(opp.anchor_text);

        if (anchorIndex === -1) {
            linksSkipped.push({ ...opp, reason: 'Anchor text not found' });
            continue;
        }

        // Check: not in intro
        const wordsBeforeAnchor = linkedContent.substring(0, anchorIndex).split(/\s+/).length;
        if (wordsBeforeAnchor < skipIntroWords) {
            linksSkipped.push({ ...opp, reason: 'In introduction' });
            continue;
        }

        // Check: minimum distance from other links
        const tooCloseToOtherLink = linkPositions.some(pos => {
            const distance = Math.abs(anchorIndex - pos);
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

        // Check: not already linked
        if (linkedContent.includes(`href="${opp.target_url}"`)) {
            linksSkipped.push({ ...opp, reason: 'Page already linked' });
            continue;
        }

        // Insert the link
        const linkHtml = `<a href="${opp.target_url}">${opp.anchor_text}</a>`;
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
