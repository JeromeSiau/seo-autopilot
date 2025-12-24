import { generateJSON } from '../shared/llm.js';

export async function extractClaims(content) {
    const result = await generateJSON(`
        Analyse ce texte et extrait toutes les affirmations factuelles vérifiables.

        Types d'affirmations à extraire:
        - Statistiques et pourcentages ("73% des utilisateurs...")
        - Dates et événements ("lancé en 2020", "fondé par X")
        - Comparaisons chiffrées ("2x plus rapide", "50% moins cher")
        - Faits techniques vérifiables ("utilise l'algorithme X")
        - Citations de sources ("selon une étude de...")

        NE PAS extraire:
        - Opinions subjectives
        - Conseils généraux
        - Affirmations vagues non vérifiables

        Texte:
        ${content.substring(0, 15000)}

        Retourne un JSON: { "claims": [{ "text": "...", "type": "statistic|date|comparison|technical|citation", "context": "phrase complète contenant l'affirmation" }] }
    `, '', { model: 'gpt-4o' });

    return result.claims || [];
}
