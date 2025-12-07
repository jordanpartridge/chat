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

export enum ArtifactType {
    CODE = 'code',
    MARKDOWN = 'markdown',
    HTML = 'html',
    SVG = 'svg',
    MERMAID = 'mermaid',
    REACT = 'react',
    VUE = 'vue',
}

export interface Artifact {
    id: string;
    message_id: string;
    identifier: string;
    type: ArtifactType;
    title: string;
    language?: string;
    content: string;
    version: number;
    created_at: string;
    updated_at: string;
}

export interface Message {
    id?: string;
    chat_id?: string;
    role: Role;
    parts: MessageParts;
    artifacts?: Artifact[];
    created_at?: string;
    updated_at?: string;
}

export interface Chat {
    id: string;
    user_id: number;
    title: string;
    ai_model_id: number;
    ai_model?: Model;
    created_at: string;
    updated_at: string;
    messages?: Message[];
}

export interface Model {
    id: number;
    name: string;
    model_id: string;
    description: string | null;
    provider?: string;
    supports_tools?: boolean;
    supports_vision?: boolean;
    enabled?: boolean;
}
