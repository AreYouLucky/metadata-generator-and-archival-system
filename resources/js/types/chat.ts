/** "Ask me" (Phase 7). Answers come only from the function tools. */
export type ChatMessageStatus = 'pending' | 'answered' | 'not_found' | 'failed';

/** A moment of a record the answer relies on, and where this account may watch it. */
export type ChatCitation = {
    record_id: string;
    title: string;
    start: number;
    end: number;
    url: string;
};

export type ChatMessage = {
    id: number;
    role: 'user' | 'assistant';
    content: string;
    /** Questions: pending → answered. Replies: answered (from the archive), not_found or failed. */
    status: ChatMessageStatus;
    citations: ChatCitation[];
    /** Function tools the answer used, e.g. `search_transcript`. */
    tools: string[];
    created_at: string | null;
};

export type ChatPage = {
    messages: ChatMessage[];
    has_more: boolean;
    pending: boolean;
};
