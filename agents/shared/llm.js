import OpenAI from 'openai';
import { config } from './config.js';

let openrouter = null;

const DEFAULT_MODEL = 'deepseek/deepseek-v3.2';

/**
 * Gets or creates the OpenRouter client instance.
 * Uses OpenAI SDK since OpenRouter is API-compatible.
 * @returns {OpenAI} OpenRouter client
 * @throws {Error} If API key is not configured
 */
function getOpenRouter() {
    if (!openrouter) {
        if (!config.openrouter.apiKey) {
            throw new Error('OpenRouter API key is not configured. Set OPENROUTER_API_KEY in .env');
        }
        openrouter = new OpenAI({
            baseURL: config.openrouter.baseUrl,
            apiKey: config.openrouter.apiKey,
            defaultHeaders: {
                'HTTP-Referer': config.openrouter.siteUrl,
                'X-Title': config.openrouter.siteName,
            },
        });
    }
    return openrouter;
}

/**
 * Generates a JSON response from the LLM.
 * @param {string} prompt - The user prompt
 * @param {string} [systemPrompt=''] - Optional system prompt
 * @param {Object} [options={}] - Configuration options
 * @param {string} [options.model] - Model to use (default: deepseek/deepseek-v3.2)
 * @param {number} [options.temperature] - Temperature (default: 0.7)
 * @param {number} [options.maxTokens] - Max tokens (default: 4096)
 * @returns {Promise<Object>} Parsed JSON response
 * @throws {Error} If API call fails or response is invalid
 */
export async function generateJSON(prompt, systemPrompt = '', options = {}) {
    const client = getOpenRouter();

    try {
        const response = await client.chat.completions.create({
            model: options.model || DEFAULT_MODEL,
            messages: [
                ...(systemPrompt ? [{ role: 'system', content: systemPrompt }] : []),
                { role: 'user', content: prompt },
            ],
            response_format: { type: 'json_object' },
            temperature: options.temperature || 0.7,
            max_tokens: options.maxTokens || 4096,
        });

        const content = response.choices?.[0]?.message?.content;
        if (!content) {
            throw new Error('Empty response from OpenRouter');
        }

        try {
            return JSON.parse(content);
        } catch (parseError) {
            console.error('Failed to parse JSON response:', content);
            throw new Error(`Invalid JSON response from OpenRouter: ${parseError.message}`);
        }
    } catch (error) {
        if (error.message.includes('Invalid JSON') || error.message.includes('Empty response')) {
            throw error;
        }
        console.error('OpenRouter API error:', error.message);
        throw new Error(`OpenRouter API call failed: ${error.message}`);
    }
}

/**
 * Generates a text response from the LLM.
 * @param {string} prompt - The user prompt
 * @param {string} [systemPrompt=''] - Optional system prompt
 * @param {Object} [options={}] - Configuration options
 * @param {string} [options.model] - Model to use (default: deepseek/deepseek-v3.2)
 * @param {number} [options.temperature] - Temperature (default: 0.7)
 * @param {number} [options.maxTokens] - Max tokens (default: 4096)
 * @returns {Promise<string>} Text response
 * @throws {Error} If API call fails or response is invalid
 */
export async function generateText(prompt, systemPrompt = '', options = {}) {
    const client = getOpenRouter();

    try {
        const response = await client.chat.completions.create({
            model: options.model || DEFAULT_MODEL,
            messages: [
                ...(systemPrompt ? [{ role: 'system', content: systemPrompt }] : []),
                { role: 'user', content: prompt },
            ],
            temperature: options.temperature || 0.7,
            max_tokens: options.maxTokens || 4096,
        });

        const content = response.choices?.[0]?.message?.content;
        if (!content) {
            throw new Error('Empty response from OpenRouter');
        }

        return content;
    } catch (error) {
        if (error.message.includes('Empty response')) {
            throw error;
        }
        console.error('OpenRouter API error:', error.message);
        throw new Error(`OpenRouter API call failed: ${error.message}`);
    }
}
