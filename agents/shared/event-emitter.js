import Redis from 'ioredis';
import { config } from './config.js';

let redis = null;

function getRedis() {
    if (!redis) {
        redis = new Redis(config.redis);

        redis.on('error', (err) => {
            console.error('Redis connection error:', err.message);
        });

        redis.on('connect', () => {
            console.log('Redis connected');
        });
    }
    return redis;
}

export async function emitEvent(articleId, agentType, eventType, message, options = {}) {
    const event = {
        article_id: articleId,
        agent_type: agentType,
        event_type: eventType,
        message: message,
        reasoning: options.reasoning || null,
        metadata: options.metadata || null,
        progress_current: options.progressCurrent || null,
        progress_total: options.progressTotal || null,
        timestamp: Date.now(),
    };

    try {
        const redisClient = getRedis();

        // Publish for real-time listeners
        await redisClient.publish(`agent-events:${articleId}`, JSON.stringify(event));

        // Also store in a queue for Laravel to process
        await redisClient.rpush('agent-events-queue', JSON.stringify(event));

        console.log(`[${agentType}] ${eventType}: ${message}`);
    } catch (error) {
        console.error(`Failed to emit event: ${error.message}`);
        // Don't throw - event emission failure shouldn't crash the agent
    }

    return event;
}

export async function emitStarted(articleId, agentType, message, reasoning = null) {
    return emitEvent(articleId, agentType, 'started', message, { reasoning });
}

export async function emitProgress(articleId, agentType, message, options = {}) {
    return emitEvent(articleId, agentType, 'progress', message, options);
}

export async function emitCompleted(articleId, agentType, message, options = {}) {
    return emitEvent(articleId, agentType, 'completed', message, options);
}

export async function emitError(articleId, agentType, message, error = null) {
    return emitEvent(articleId, agentType, 'error', message, {
        metadata: { error: error?.message || error }
    });
}

export async function closeRedis() {
    if (redis) {
        await redis.quit();
        redis = null;
    }
}
