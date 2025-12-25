import dotenv from 'dotenv';
import path from 'path';
import { fileURLToPath } from 'url';

// Load .env from project root (not from agents folder)
const __dirname = path.dirname(fileURLToPath(import.meta.url));
dotenv.config({ path: path.join(__dirname, '..', '..', '.env') });

export const config = {
    redis: {
        host: process.env.REDIS_HOST || 'localhost',
        port: parseInt(process.env.REDIS_PORT || '6379'),
        password: process.env.REDIS_PASSWORD || undefined,
    },
    openai: {
        apiKey: process.env.OPENAI_API_KEY,
    },
    voyage: {
        apiKey: process.env.VOYAGE_API_KEY,
    },
    database: {
        host: process.env.DB_HOST || 'localhost',
        port: parseInt(process.env.DB_PORT || '3306'),
        database: process.env.DB_DATABASE || 'seo_autopilot',
        user: process.env.DB_USERNAME || 'root',
        password: process.env.DB_PASSWORD || '',
    },
};
