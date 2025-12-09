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
    model: string;
    agent_id?: number | null;
    agent?: Agent | null;
    created_at: string;
    updated_at: string;
    messages?: Message[];
}

export interface Model {
    id: string;
    name: string;
    description: string;
    provider: string;
    supportsTools?: boolean;
}

export interface Agent {
    id: number;
    name: string;
    description: string;
    user_id: number | null;
    default_model_id: number | null;
    system_prompt: string | null;
    avatar: string | null;
    tools: string[] | null;
    capabilities: string[] | null;
    is_active: boolean;
    created_at: string;
    updated_at: string;
    default_model?: Model;
}

export interface ToolOption {
    id: string;
    name: string;
    description: string;
}

export interface CapabilityOption {
    id: string;
    name: string;
    description: string;
}
