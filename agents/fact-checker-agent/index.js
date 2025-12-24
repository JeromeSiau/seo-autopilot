import { program } from 'commander';
import { readFileSync } from 'fs';
import { emitStarted, emitProgress, emitCompleted, emitError, closeRedis } from '../shared/event-emitter.js';
import { extractClaims } from './claim-extractor.js';
import { verifyClaims } from './verifier.js';

const AGENT_TYPE = 'fact_checker';

program
    .requiredOption('--articleId <id>', 'Article ID')
    .requiredOption('--contentFile <path>', 'Path to article content file')
    .parse();

const options = program.opts();

async function main() {
    const { articleId, contentFile } = options;

    let content;
    try {
        content = readFileSync(contentFile, 'utf-8');
    } catch (error) {
        console.log(JSON.stringify({ success: false, error: `Failed to read content file: ${error.message}` }));
        process.exit(1);
    }

    try {
        await emitStarted(articleId, AGENT_TYPE,
            'Démarrage de la vérification des faits',
            'Je vais identifier les affirmations factuelles et les vérifier avec des sources fiables.'
        );

        // Step 1: Extract claims
        await emitProgress(articleId, AGENT_TYPE, 'Extraction des affirmations vérifiables...');

        const claims = await extractClaims(content);

        if (claims.length === 0) {
            await emitCompleted(articleId, AGENT_TYPE,
                'Aucune affirmation factuelle à vérifier',
                { reasoning: 'Le contenu ne contient pas d\'affirmations vérifiables.' }
            );
            console.log(JSON.stringify({ success: true, total_claims: 0, claims: [] }));
            return;
        }

        await emitProgress(articleId, AGENT_TYPE,
            `${claims.length} affirmations à vérifier identifiées`,
            { metadata: { claims_count: claims.length } }
        );

        // Step 2: Verify each claim
        const verifiedClaims = [];

        for (let i = 0; i < claims.length; i++) {
            const claim = claims[i];

            await emitProgress(articleId, AGENT_TYPE,
                `Vérification (${i + 1}/${claims.length}): "${claim.text.substring(0, 50)}..."`,
                { progressCurrent: i + 1, progressTotal: claims.length }
            );

            const verification = await verifyClaims([claim]);
            const verified = verification[0];
            verifiedClaims.push(verified);

            // Emit result for this claim
            const statusEmoji = {
                verified: '✅',
                partially_true: '⚠️',
                incorrect: '❌',
                unverifiable: '❓'
            }[verified.status] || '❓';

            await emitProgress(articleId, AGENT_TYPE,
                `${statusEmoji} "${claim.text.substring(0, 40)}..." → ${verified.status}`,
                {
                    metadata: {
                        claim: claim.text.substring(0, 100),
                        status: verified.status,
                        source: verified.source_url
                    }
                }
            );
        }

        // Calculate stats
        const stats = {
            verified: verifiedClaims.filter(c => c.status === 'verified').length,
            partially_true: verifiedClaims.filter(c => c.status === 'partially_true').length,
            incorrect: verifiedClaims.filter(c => c.status === 'incorrect').length,
            unverifiable: verifiedClaims.filter(c => c.status === 'unverifiable').length,
        };

        await emitCompleted(articleId, AGENT_TYPE,
            `Vérification terminée: ${stats.verified} ✅, ${stats.partially_true} ⚠️, ${stats.incorrect} ❌, ${stats.unverifiable} ❓`,
            {
                reasoning: stats.incorrect > 0
                    ? `${stats.incorrect} erreur(s) factuelle(s) trouvée(s). Des corrections sont proposées.`
                    : 'Toutes les affirmations vérifiables sont correctes.',
                metadata: stats
            }
        );

        // Output result
        const result = {
            success: true,
            total_claims: claims.length,
            ...stats,
            claims: verifiedClaims,
            citations_to_add: verifiedClaims
                .filter(c => c.status === 'verified' && c.source_url)
                .map(c => ({
                    text: c.original_text,
                    source_url: c.source_url,
                    source_title: c.source_title
                })),
            corrections: verifiedClaims
                .filter(c => c.status === 'incorrect' && c.corrected_text)
                .map(c => ({
                    original: c.original_text,
                    corrected: c.corrected_text,
                    source_url: c.source_url
                }))
        };

        console.log(JSON.stringify(result));

    } catch (error) {
        await emitError(articleId, AGENT_TYPE, `Erreur: ${error.message}`, error);
        console.log(JSON.stringify({ success: false, error: error.message }));
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
