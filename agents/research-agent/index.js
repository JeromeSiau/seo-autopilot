import { program } from 'commander';
import { emitStarted, emitProgress, emitCompleted, emitError, closeRedis } from '../shared/event-emitter.js';
import { generateJSON } from '../shared/llm.js';
import { searchGoogle } from './google-search.js';
import { scrapeUrls } from './content-scraper.js';

const AGENT_TYPE = 'research';

program
    .requiredOption('--articleId <id>', 'Article ID')
    .requiredOption('--keyword <keyword>', 'Target keyword')
    .option('--siteId <id>', 'Site ID')
    .parse();

const options = program.opts();

async function main() {
    const { articleId, keyword } = options;

    try {
        await emitStarted(articleId, AGENT_TYPE, `Démarrage de la recherche pour "${keyword}"`,
            `Le keyword "${keyword}" sera analysé pour comprendre l'intention de recherche et collecter des sources.`);

        // Step 1: Generate search queries
        await emitProgress(articleId, AGENT_TYPE, 'Génération des requêtes de recherche...');

        const queries = await generateSearchQueries(keyword);

        await emitProgress(articleId, AGENT_TYPE,
            `${queries.length} requêtes de recherche préparées`,
            { reasoning: `Requêtes variées pour couvrir différents angles du sujet.` }
        );

        // Step 2: Search Google for each query
        let allUrls = [];
        for (let i = 0; i < queries.length; i++) {
            await emitProgress(articleId, AGENT_TYPE,
                `Recherche Google: "${queries[i]}"`,
                { progressCurrent: i + 1, progressTotal: queries.length }
            );

            const urls = await searchGoogle(queries[i]);
            allUrls = [...allUrls, ...urls];
        }

        // Deduplicate URLs
        const uniqueUrls = [...new Set(allUrls)];
        await emitProgress(articleId, AGENT_TYPE,
            `${allUrls.length} URLs collectées (dédupliquées: ${uniqueUrls.length})`
        );

        // Step 3: Scrape content from URLs
        await emitProgress(articleId, AGENT_TYPE, 'Extraction du contenu des pages...');

        const scrapedContent = await scrapeUrls(uniqueUrls, (current, total) => {
            emitProgress(articleId, AGENT_TYPE,
                `Extraction du contenu (${current}/${total})...`,
                { progressCurrent: current, progressTotal: total }
            );
        });

        const validContent = scrapedContent.filter(c => c.content && c.content.length > 200);

        if (validContent.length === 0) {
            await emitCompleted(articleId, AGENT_TYPE,
                'Aucune source exploitable trouvée',
                { reasoning: 'Les pages trouvées n\'avaient pas assez de contenu.' }
            );
            console.log(JSON.stringify({ success: true, sources: [], key_topics: [], entities: [], facts: [], suggested_angles: [], competitor_urls: uniqueUrls.slice(0, 10) }));
            return;
        }

        await emitProgress(articleId, AGENT_TYPE,
            `Extraction terminée, ${validContent.length} pages exploitables`
        );

        // Step 4: Analyze and synthesize
        await emitProgress(articleId, AGENT_TYPE, 'Analyse et synthèse des sources...');

        const analysis = await analyzeContent(keyword, validContent);

        await emitCompleted(articleId, AGENT_TYPE,
            `Recherche terminée: ${analysis.entities.length} entités identifiées, ${analysis.facts.length} faits collectés`,
            {
                reasoning: analysis.summary,
                metadata: {
                    sources_count: validContent.length,
                    entities_count: analysis.entities.length,
                    facts_count: analysis.facts.length,
                }
            }
        );

        // Output result as JSON (last line, for Laravel to parse)
        const result = {
            success: true,
            sources: validContent.map(c => ({ url: c.url, title: c.title, snippet: c.content.substring(0, 500) })),
            key_topics: analysis.topics,
            entities: analysis.entities,
            facts: analysis.facts,
            suggested_angles: analysis.angles,
            competitor_urls: uniqueUrls.slice(0, 10),
        };

        console.log(JSON.stringify(result));

    } catch (error) {
        await emitError(articleId, AGENT_TYPE, `Erreur: ${error.message}`, error);
        console.log(JSON.stringify({ success: false, error: error.message }));
        process.exit(1);
    } finally {
        await closeRedis();
    }
}

async function generateSearchQueries(keyword) {
    const result = await generateJSON(`
        Génère 5-6 requêtes de recherche Google variées pour le keyword "${keyword}".
        Les requêtes doivent couvrir:
        - La requête principale
        - Des variations avec "best", "top", "guide"
        - Des questions (how to, what is)
        - Des comparaisons si pertinent

        Retourne un JSON: { "queries": ["query1", "query2", ...] }
    `);

    if (!result.queries || !Array.isArray(result.queries)) {
        throw new Error('Invalid LLM response: missing queries array');
    }
    return result.queries;
}

async function analyzeContent(keyword, sources) {
    const sourcesText = sources.map(s =>
        `[${s.title}]\n${s.content.substring(0, 2000)}`
    ).join('\n\n---\n\n');

    const result = await generateJSON(`
        Analyse ces sources sur le sujet "${keyword}" et extrait:

        1. topics: Les sous-sujets principaux couverts (liste de strings)
        2. entities: Les entités importantes (outils, marques, personnes) mentionnées
        3. facts: Les faits/statistiques citables avec leur source
        4. angles: 2-3 angles d'article suggérés pour se différencier
        5. summary: Un résumé de 2-3 phrases de ce que les sources couvrent

        Sources:
        ${sourcesText}

        Retourne un JSON avec ces 5 clés.
    `, '', { model: 'gpt-4o', maxTokens: 4096 });

    // Validate and provide defaults for missing fields
    return {
        topics: result.topics || [],
        entities: result.entities || [],
        facts: result.facts || [],
        angles: result.angles || [],
        summary: result.summary || '',
    };
}

main();
