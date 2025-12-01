export enum Role {
    USER = 'user',
    ASSISTANT = 'assistant',
}

export enum StreamEventType {
    TEXT = 'text',
    ERROR = 'error',
}

export interface StreamEvent {
    type: StreamEventType;
    content: string;
}

export interface MessageParts {
    text?: string;
}

export interface Message {
    id?: string;
    chat_id?: string;
    role: Role;
    parts: MessageParts;
    created_at?: string;
    updated_at?: string;
}

export interface Chat {
    id: string;
    user_id: number;
    title: string;
    model: string;
    created_at: string;
    updated_at: string;
    messages?: Message[];
}

export interface Model {
    id: string;
    name: string;
    description: string;
    provider: string;
}
