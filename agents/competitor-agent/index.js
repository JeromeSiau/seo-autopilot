import { program } from 'commander';
import { emitStarted, emitProgress, emitCompleted, emitError, closeRedis } from '../shared/event-emitter.js';
import { generateJSON } from '../shared/llm.js';
import { analyzeCompetitors } from './serp-analyzer.js';

const AGENT_TYPE = 'competitor';

program
    .requiredOption('--articleId <id>', 'Article ID')
    .requiredOption('--keyword <keyword>', 'Target keyword')
    .requiredOption('--urls <urls>', 'JSON array of competitor URLs')
    .parse();

const options = program.opts();

async function main() {
    const { articleId, keyword, urls: urlsJson } = options;

    let urls;
    try {
        urls = JSON.parse(urlsJson);
        if (!Array.isArray(urls) || urls.length === 0) {
            throw new Error('URLs must be a non-empty array');
        }
    } catch (parseError) {
        await emitError(articleId, AGENT_TYPE, `Invalid URLs JSON: ${parseError.message}`);
        console.log(JSON.stringify({ success: false, error: `Invalid URLs: ${parseError.message}` }));
        process.exit(1);
    }

    try {
        await emitStarted(articleId, AGENT_TYPE,
            `Analyse de ${urls.length} concurrents pour "${keyword}"`,
            `Je vais analyser la structure, le word count et les topics couverts par chaque concurrent.`
        );

        // Analyze each competitor
        const analyses = await analyzeCompetitors(urls, (current, total, url, data) => {
            const message = data
                ? `${new URL(url).hostname}: ${data.wordCount} mots, ${data.headings.h2} H2`
                : `Analyse de ${new URL(url).hostname} (${current}/${total})...`;

            emitProgress(articleId, AGENT_TYPE, message, {
                progressCurrent: current,
                progressTotal: total,
                metadata: data ? { url, ...data } : null
            });
        });

        const validAnalyses = analyses.filter(a => a.wordCount > 0);

        if (validAnalyses.length === 0) {
            await emitCompleted(articleId, AGENT_TYPE, 'Aucun concurrent analysable', {
                reasoning: 'Aucune page n\'a pu être analysée correctement.'
            });
            console.log(JSON.stringify({ success: true, competitors: [], avg_word_count: 0 }));
            return;
        }

        // Calculate statistics
        const wordCounts = validAnalyses.map(a => a.wordCount);
        const avgWordCount = Math.round(wordCounts.reduce((a, b) => a + b, 0) / wordCounts.length);
        const top3Avg = Math.round(wordCounts.slice(0, 3).reduce((a, b) => a + b, 0) / Math.min(3, wordCounts.length));

        await emitProgress(articleId, AGENT_TYPE,
            `Statistiques calculées: moyenne ${avgWordCount} mots`
        );

        // Extract common topics
        await emitProgress(articleId, AGENT_TYPE, 'Extraction des topics communs...');

        const topicAnalysis = await analyzeTopics(keyword, validAnalyses);

        // Generate recommendations
        const recommendedWordCount = Math.round(top3Avg * 1.15); // 15% more than top 3

        await emitCompleted(articleId, AGENT_TYPE,
            `Analyse terminée: moyenne ${avgWordCount} mots, recommandation ${recommendedWordCount} mots`,
            {
                reasoning: `Les 3 premiers résultats font en moyenne ${top3Avg} mots. Pour les dépasser, je recommande ${recommendedWordCount} mots (+15%). ${topicAnalysis.gaps?.length || 0} content gaps identifiés.`,
                metadata: {
                    competitors_analyzed: validAnalyses.length,
                    avg_word_count: avgWordCount,
                    recommended_word_count: recommendedWordCount,
                    content_gaps: topicAnalysis.gaps?.length || 0,
                }
            }
        );

        // Output result
        const result = {
            success: true,
            competitors: validAnalyses,
            avg_word_count: avgWordCount,
            top3_avg_word_count: top3Avg,
            recommended_word_count: recommendedWordCount,
            common_topics: topicAnalysis.common || [],
            content_gaps: topicAnalysis.gaps || [],
            recommended_headings: topicAnalysis.recommendedHeadings || [],
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

async function analyzeTopics(keyword, competitors) {
    const headingsText = competitors.map(c =>
        `[${c.url}]\n${c.allHeadings.map(h => `${h.level}: ${h.text}`).join('\n')}`
    ).join('\n\n');

    const result = await generateJSON(`
        Analyse ces titres H2/H3 des articles concurrents sur "${keyword}":

        ${headingsText}

        Retourne un JSON avec:
        1. common: Topics couverts par 50%+ des concurrents (liste de strings)
        2. gaps: Topics couverts par moins de 30% (opportunités de différenciation)
        3. recommendedHeadings: Structure H2/H3 recommandée pour un article complet
           Format: [{ "level": "h2", "text": "..." }, ...]
    `, '', { model: 'gpt-4o' });

    return {
        common: result.common || [],
        gaps: result.gaps || [],
        recommendedHeadings: result.recommendedHeadings || [],
    };
}

main();
