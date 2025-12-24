import OpenAI from 'openai';
import { config } from './config.js';

let openai = null;

/**
 * Gets or creates the OpenAI client instance.
 * @returns {OpenAI} OpenAI client
 * @throws {Error} If API key is not configured
 */
function getOpenAI() {
    if (!openai) {
        if (!config.openai.apiKey) {
            throw new Error('OpenAI API key is not configured. Set OPENAI_API_KEY in .env');
        }
        openai = new OpenAI({ apiKey: config.openai.apiKey });
    }
    return openai;
}

/**
 * Generates a JSON response from the LLM.
 * @param {string} prompt - The user prompt
 * @param {string} [systemPrompt=''] - Optional system prompt
 * @param {Object} [options={}] - Configuration options
 * @returns {Promise<Object>} Parsed JSON response
 * @throws {Error} If API call fails or response is invalid
 */
export async function generateJSON(prompt, systemPrompt = '', options = {}) {
    const client = getOpenAI();

    try {
        const response = await client.chat.completions.create({
            model: options.model || 'gpt-4o-mini',
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
            throw new Error('Empty response from OpenAI');
        }

        try {
            return JSON.parse(content);
        } catch (parseError) {
            console.error('Failed to parse JSON response:', content);
            throw new Error(`Invalid JSON response from OpenAI: ${parseError.message}`);
        }
    } catch (error) {
        if (error.message.includes('Invalid JSON') || error.message.includes('Empty response')) {
            throw error;
        }
        console.error('OpenAI API error:', error.message);
        throw new Error(`OpenAI API call failed: ${error.message}`);
    }
}

/**
 * Generates a text response from the LLM.
 * @param {string} prompt - The user prompt
 * @param {string} [systemPrompt=''] - Optional system prompt
 * @param {Object} [options={}] - Configuration options
 * @returns {Promise<string>} Text response
 * @throws {Error} If API call fails or response is invalid
 */
export async function generateText(prompt, systemPrompt = '', options = {}) {
    const client = getOpenAI();

    try {
        const response = await client.chat.completions.create({
            model: options.model || 'gpt-4o-mini',
            messages: [
                ...(systemPrompt ? [{ role: 'system', content: systemPrompt }] : []),
                { role: 'user', content: prompt },
            ],
            temperature: options.temperature || 0.7,
            max_tokens: options.maxTokens || 4096,
        });

        const content = response.choices?.[0]?.message?.content;
        if (!content) {
            throw new Error('Empty response from OpenAI');
        }

        return content;
    } catch (error) {
        if (error.message.includes('Empty response')) {
            throw error;
        }
        console.error('OpenAI API error:', error.message);
        throw new Error(`OpenAI API call failed: ${error.message}`);
    }
}
