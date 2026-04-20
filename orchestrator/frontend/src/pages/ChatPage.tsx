import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { useCallback, useEffect, useState } from 'react';
import { apiFetch, parseJsonOrThrow } from '../api/client';
import { useAuth } from '../context/AuthContext';
import type { ChatMessage, ChatSession } from '../types';

interface MessageStatusPayload {
  id: string;
  status: ChatMessage['status'];
  content: string | null;
  error: string | null;
}

async function pollMessageStatus(messageId: string): Promise<MessageStatusPayload> {
  const res = await apiFetch(`/api/v1/chat/messages/${messageId}/status`);
  return parseJsonOrThrow(res);
}

export function ChatPage(): React.JSX.Element {
  const { user, logout } = useAuth();
  const queryClient = useQueryClient();
  const [activeSessionId, setActiveSessionId] = useState<string | null>(null);
  const [draft, setDraft] = useState('');
  const [sending, setSending] = useState(false);

  const sessionsQuery = useQuery({
    queryKey: ['chat-sessions'],
    queryFn: async () => {
      const res = await apiFetch('/api/v1/chat/sessions');
      const body = await parseJsonOrThrow<{ items: ChatSession[] }>(res);
      return body.items;
    },
  });

  const messagesQuery = useQuery({
    queryKey: ['chat-messages', activeSessionId],
    enabled: Boolean(activeSessionId),
    queryFn: async () => {
      const res = await apiFetch(`/api/v1/chat/sessions/${activeSessionId}/messages`);
      const body = await parseJsonOrThrow<{ items: ChatMessage[] }>(res);
      return body.items;
    },
  });

  const createSession = useMutation({
    mutationFn: async () => {
      const res = await apiFetch('/api/v1/chat/sessions', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ title: 'New Chat' }),
      });
      return parseJsonOrThrow<ChatSession>(res);
    },
    onSuccess: (session) => {
      void queryClient.invalidateQueries({ queryKey: ['chat-sessions'] });
      setActiveSessionId(session.id);
    },
  });

  const sendMessage = useCallback(async () => {
    if (!activeSessionId || !draft.trim()) {
      return;
    }
    setSending(true);
    try {
      const res = await apiFetch(`/api/v1/chat/sessions/${activeSessionId}/messages`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ content: draft.trim() }),
      });
      const body = await parseJsonOrThrow<{
        assistant_message_id: string;
      }>(res);
      setDraft('');
      void queryClient.invalidateQueries({ queryKey: ['chat-messages', activeSessionId] });

      const assistantId = body.assistant_message_id;
      const deadline = Date.now() + 60_000;
      let status = 'processing';
      while (Date.now() < deadline && status === 'processing') {
        const st = await pollMessageStatus(assistantId);
        status = st.status;
        if (status === 'processing') {
          await new Promise((r) => setTimeout(r, 400));
        }
      }
      void queryClient.invalidateQueries({ queryKey: ['chat-messages', activeSessionId] });
    } finally {
      setSending(false);
    }
  }, [activeSessionId, draft, queryClient]);

  useEffect(() => {
    if (!activeSessionId && sessionsQuery.data?.length) {
      setActiveSessionId(sessionsQuery.data[0].id);
    }
  }, [activeSessionId, sessionsQuery.data]);

  return (
    <div className="chat-layout">
      <aside className="sidebar">
        <div className="sidebar-header">
          <strong>{user?.name}</strong>
          <button type="button" className="link" onClick={() => void logout()}>
            Log out
          </button>
        </div>
        <button
          type="button"
          className="new-session"
          onClick={() => void createSession.mutateAsync()}
          disabled={createSession.isPending}
        >
          New chat
        </button>
        <ul className="session-list">
          {sessionsQuery.isLoading ? (
            <li>Loading sessions…</li>
          ) : sessionsQuery.data?.length ? (
            sessionsQuery.data.map((s) => (
              <li key={s.id}>
                <button
                  type="button"
                  className={s.id === activeSessionId ? 'session active' : 'session'}
                  onClick={() => setActiveSessionId(s.id)}
                >
                  {s.title}
                </button>
              </li>
            ))
          ) : (
            <li>No chats yet</li>
          )}
        </ul>
      </aside>
      <main className="chat-main">
        {!activeSessionId ? (
          <p className="empty">Create a chat to get started.</p>
        ) : (
          <>
            <div className="messages">
              {messagesQuery.isLoading ? (
                <p>Loading messages…</p>
              ) : (
                messagesQuery.data?.map((m) => (
                  <div key={m.id} className={`msg msg-${m.role}`}>
                    <div className="msg-meta">
                      <span>{m.role}</span>
                      {m.status !== 'done' ? <span className="status">{m.status}</span> : null}
                    </div>
                    <div className="msg-body">{m.content || (m.status === 'processing' ? '…' : '')}</div>
                  </div>
                ))
              )}
            </div>
            <form
              className="composer"
              onSubmit={(e) => {
                e.preventDefault();
                void sendMessage();
              }}
            >
              <input
                value={draft}
                onChange={(e) => setDraft(e.target.value)}
                placeholder="Message"
                disabled={sending}
              />
              <button type="submit" disabled={sending || !draft.trim()}>
                Send
              </button>
            </form>
          </>
        )}
      </main>
    </div>
  );
}
