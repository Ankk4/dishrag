export interface User {
  id: string;
  email: string;
  name: string;
  created_at: string;
}

export interface AuthTokens {
  access_token: string;
  expires_in: number;
  token_type: 'Bearer';
}

export interface ChatSession {
  id: string;
  title: string;
  created_at: string;
  updated_at: string;
}

export interface ChatMessage {
  id: string;
  role: 'user' | 'assistant' | 'system' | 'context';
  content: string;
  status: 'processing' | 'done' | 'failed';
  created_at: string;
  metadata?: Record<string, unknown>;
}

export interface ApiErrorBody {
  error: {
    code: string;
    message: string;
    details?: Array<{ field?: string; message: string }>;
    request_id: string;
  };
}
