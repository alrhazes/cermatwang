import { type ClassValue, clsx } from 'clsx';
import { twMerge } from 'tailwind-merge';

export function cn(...inputs: ClassValue[]) {
    return twMerge(clsx(inputs));
}

/**
 * Stable unique id for client-only keys. Uses randomUUID when available (secure contexts);
 * falls back for HTTP / older browsers where crypto.randomUUID is missing.
 */
export function createClientId(): string {
    if (typeof crypto !== 'undefined' && typeof crypto.randomUUID === 'function') {
        return crypto.randomUUID();
    }
    if (typeof crypto !== 'undefined' && typeof crypto.getRandomValues === 'function') {
        const bytes = new Uint8Array(16);
        crypto.getRandomValues(bytes);
        bytes[6] = (bytes[6] & 0x0f) | 0x40;
        bytes[8] = (bytes[8] & 0x3f) | 0x80;
        const hex = [...bytes].map((b) => b.toString(16).padStart(2, '0')).join('');
        return `${hex.slice(0, 8)}-${hex.slice(8, 12)}-${hex.slice(12, 16)}-${hex.slice(16, 20)}-${hex.slice(20)}`;
    }
    return `id-${Date.now()}-${Math.random().toString(36).slice(2, 11)}`;
}

/**
 * Strip pseudo-tool markup some models print into message text (mirrors ChatAssistantContentSanitizer on the server).
 */
export function stripAssistantInlineToolMarkup(content: string): string {
    return content
        .replace(/<function=[a-zA-Z0-9_]+>\s*[\s\S]*?<\/function>/gi, '')
        .replace(/\n{3,}/g, '\n\n')
        .trim();
}
