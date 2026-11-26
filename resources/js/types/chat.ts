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

/** A record an answer is about, and where this account may watch it. */
export type ChatLink = {
    record_id: string;
    title: string;
    url: string;
};

export type ChatMessage = {
    id: number;
    conversation_id: number | null;
    /** The record whose page the question was asked on, if any. */
    record_id: string | null;
    role: 'user' | 'assistant';
    content: string;
    /** Questions: pending → answered. Replies: answered (from the archive), not_found or failed. */
    status: ChatMessageStatus;
    citations: ChatCitation[];
    /** The record a time off the citations opens, and where this account may watch it. */
    link: ChatLink | null;
    /** Function tools the answer used, e.g. `search_transcript`. */
    tools: string[];
    /** Questions the answer suggests asking next. */
    follow_ups: string[];
    created_at: string | null;
};

/** One saved conversation in the history list. */
export type ChatConversation = {
    id: number;
    title: string;
    updated_at: string | null;
};

export type ChatConversationsPage = {
    conversations: ChatConversation[];
    next_page: number | null;
};

export type ChatPage = {
    conversation: ChatConversation;
    messages: ChatMessage[];
    has_more: boolean;
    pending: boolean;
};
