import { config } from '../shared/config.js';

const VOYAGE_API_URL = 'https://api.voyageai.com/v1/embeddings';
const VOYAGE_MODEL = 'voyage-3';
const MAX_BATCH_SIZE = 128;

/**
 * Generates a single embedding using Voyage API.
 * @param {string} text - The text to embed
 * @param {string} inputType - The input type ('document' or 'query')
 * @returns {Promise<number[]>} The embedding vector
 * @throws {Error} If API call fails or configuration is missing
 */
export async function generateEmbedding(text, inputType = 'document') {
    if (!config.voyage.apiKey) {
        throw new Error('Voyage API key is not configured. Set VOYAGE_API_KEY in .env');
    }

    if (!text || typeof text !== 'string') {
        throw new Error('Text must be a non-empty string');
    }

    const response = await fetch(VOYAGE_API_URL, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'Authorization': `Bearer ${config.voyage.apiKey}`,
        },
        body: JSON.stringify({
            input: [text],
            model: VOYAGE_MODEL,
            input_type: inputType,
        }),
    });

    if (!response.ok) {
        const error = await response.text();
        throw new Error(`Voyage API error (${response.status}): ${error}`);
    }

    const data = await response.json();

    if (!data.data || !data.data[0] || !data.data[0].embedding) {
        throw new Error('Invalid response from Voyage API');
    }

    return data.data[0].embedding;
}

/**
 * Generates embeddings for multiple texts in batch.
 * Automatically splits into multiple requests if batch exceeds 128 items.
 * @param {string[]} texts - Array of texts to embed
 * @param {string} inputType - The input type ('document' or 'query')
 * @returns {Promise<number[][]>} Array of embedding vectors
 * @throws {Error} If API call fails or configuration is missing
 */
export async function generateEmbeddingsBatch(texts, inputType = 'document') {
    if (!config.voyage.apiKey) {
        throw new Error('Voyage API key is not configured. Set VOYAGE_API_KEY in .env');
    }

    if (!Array.isArray(texts) || texts.length === 0) {
        throw new Error('Texts must be a non-empty array');
    }

    // Validate all texts
    for (const text of texts) {
        if (!text || typeof text !== 'string') {
            throw new Error('All texts must be non-empty strings');
        }
    }

    const embeddings = [];

    // Process in batches of MAX_BATCH_SIZE
    for (let i = 0; i < texts.length; i += MAX_BATCH_SIZE) {
        const batch = texts.slice(i, i + MAX_BATCH_SIZE);

        const response = await fetch(VOYAGE_API_URL, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Authorization': `Bearer ${config.voyage.apiKey}`,
            },
            body: JSON.stringify({
                input: batch,
                model: VOYAGE_MODEL,
                input_type: inputType,
            }),
        });

        if (!response.ok) {
            const error = await response.text();
            throw new Error(`Voyage API error (${response.status}): ${error}`);
        }

        const data = await response.json();

        if (!data.data || !Array.isArray(data.data)) {
            throw new Error('Invalid response from Voyage API');
        }

        // Extract embeddings in order
        for (const item of data.data) {
            if (!item.embedding) {
                throw new Error('Missing embedding in API response');
            }
            embeddings.push(item.embedding);
        }
    }

    return embeddings;
}
