import OpenAI from 'openai';
import { config } from './config.js';

let openai = null;

function getOpenAI() {
    if (!openai) {
        openai = new OpenAI({ apiKey: config.openai.apiKey });
    }
    return openai;
}

export async function generateJSON(prompt, systemPrompt = '', options = {}) {
    const client = getOpenAI();

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

    const content = response.choices[0].message.content;
    return JSON.parse(content);
}

export async function generateText(prompt, systemPrompt = '', options = {}) {
    const client = getOpenAI();

    const response = await client.chat.completions.create({
        model: options.model || 'gpt-4o-mini',
        messages: [
            ...(systemPrompt ? [{ role: 'system', content: systemPrompt }] : []),
            { role: 'user', content: prompt },
        ],
        temperature: options.temperature || 0.7,
        max_tokens: options.maxTokens || 4096,
    });

    return response.choices[0].message.content;
}
