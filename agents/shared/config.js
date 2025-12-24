import 'dotenv/config';

export const config = {
    redis: {
        host: process.env.REDIS_HOST || 'localhost',
        port: parseInt(process.env.REDIS_PORT || '6379'),
        password: process.env.REDIS_PASSWORD || undefined,
    },
    openai: {
        apiKey: process.env.OPENAI_API_KEY,
    },
    database: {
        host: process.env.DB_HOST || 'localhost',
        port: parseInt(process.env.DB_PORT || '3306'),
        database: process.env.DB_DATABASE || 'seo_autopilot',
        user: process.env.DB_USERNAME || 'root',
        password: process.env.DB_PASSWORD || '',
    },
};
