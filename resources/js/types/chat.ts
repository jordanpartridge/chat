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
    id: number;
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
    id: number;
    user_id: number;
    title: string;
    ai_model_id: number;
    ai_model?: AiModel;
    agent_id?: number | null;
    agent?: Agent | null;
    created_at: string;
    updated_at: string;
    messages?: Message[];
}

export interface AiModel {
    id: number;
    name: string;
    description: string;
    provider: string;
    model_id: string;    supports_tools: boolean;
    supports_vision: boolean;
    context_window: number;
    speed_tier: string;
    cost_tier: string;}

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
    default_model?: AiModel;
}

export interface ToolOption {
    id: number;
    name: string;
    description: string;
}

export interface CapabilityOption {
    id: number;
    name: string;
    description: string;
}
