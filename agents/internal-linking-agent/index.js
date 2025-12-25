import { program } from 'commander';
import { readFileSync } from 'fs';
import { emitStarted, emitProgress, emitCompleted, emitError, closeRedis } from '../shared/event-emitter.js';
import { loadSitePages } from './site-scanner.js';
import { findLinkOpportunities, insertLinks } from './link-suggester.js';

const AGENT_TYPE = 'internal_linking';

program
    .requiredOption('--articleId <id>', 'Article ID')
    .requiredOption('--siteId <id>', 'Site ID')
    .requiredOption('--contentFile <path>', 'Path to article content file')
    .parse();

const options = program.opts();

async function main() {
    const { articleId, siteId, contentFile } = options;

    let content;
    try {
        content = readFileSync(contentFile, 'utf-8');
    } catch (error) {
        console.log(JSON.stringify({ success: false, error: `Failed to read content file: ${error.message}` }));
        process.exit(1);
    }

    try {
        await emitStarted(articleId, AGENT_TYPE,
            'Démarrage du linking interne',
            'Je vais analyser vos pages existantes et insérer des liens pertinents.'
        );

        // Step 1: Load site index
        await emitProgress(articleId, AGENT_TYPE, 'Chargement de l\'index du site...');

        const sitePages = await loadSitePages(siteId);

        await emitProgress(articleId, AGENT_TYPE,
            `Index chargé: ${sitePages.length} pages existantes`
        );

        if (sitePages.length === 0) {
            await emitCompleted(articleId, AGENT_TYPE,
                'Aucune page existante pour le linking',
                { reasoning: 'Le site n\'a pas encore de pages indexées.' }
            );
            console.log(JSON.stringify({
                success: true,
                links_added: [],
                content: content
            }));
            return;
        }

        // Step 2: Find link opportunities
        await emitProgress(articleId, AGENT_TYPE, 'Recherche d\'opportunités de liens...');

        const opportunities = await findLinkOpportunities(content, sitePages);

        await emitProgress(articleId, AGENT_TYPE,
            `${opportunities.length} opportunités de liens trouvées`,
            {
                reasoning: `J'ai identifié ${opportunities.length} termes pouvant lier vers vos pages existantes.`,
                metadata: { opportunities_count: opportunities.length }
            }
        );

        // Step 3: Select and insert links (respecting rules)
        await emitProgress(articleId, AGENT_TYPE, 'Sélection et insertion des liens...');

        const { linkedContent, linksAdded, linksSkipped } = await insertLinks(
            content,
            opportunities,
            {
                maxLinksPerSection: 2,
                minWordsBetweenLinks: 300,
                skipIntroWords: 150,
                preferOrphanPages: true,
            }
        );

        await emitCompleted(articleId, AGENT_TYPE,
            `${linksAdded.length} liens insérés (${linksSkipped.length} ignorés)`,
            {
                reasoning: linksSkipped.length > 0
                    ? `${linksSkipped.length} liens ignorés pour éviter la sur-optimisation (densité, intro, doublon).`
                    : 'Tous les liens pertinents ont été insérés.',
                metadata: {
                    links_added: linksAdded.length,
                    links_skipped: linksSkipped.length,
                }
            }
        );

        // Output result
        const result = {
            success: true,
            content: linkedContent,
            links_added: linksAdded,
            links_skipped: linksSkipped,
            site_health: {
                total_pages: sitePages.length,
                orphan_pages: sitePages.filter(p => (p.inbound_links || 0) === 0).length,
            }
        };

        console.log(JSON.stringify(result));

    } catch (error) {
        await emitError(articleId, AGENT_TYPE, `Erreur: ${error.message}`, error);
        console.log(JSON.stringify({ success: false, error: error.message, content }));
        process.exit(1);
    } finally {
        try {
            await closeRedis();
        } catch (redisError) {
            console.error('Failed to close Redis:', redisError.message);
        }
    }
}

main();
