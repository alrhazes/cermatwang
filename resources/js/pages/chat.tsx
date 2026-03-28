import { Alert, AlertDescription } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import { cn, createClientId } from '@/lib/utils';
import { type BreadcrumbItem, type SharedData } from '@/types';
import { Head, usePage } from '@inertiajs/react';
import { Loader2, SendHorizontal } from 'lucide-react';
import { useEffect, useId, useRef, useState } from 'react';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Chat',
        href: '/chat',
    },
];

type ChatRole = 'assistant' | 'user';

interface ChatMessage {
    id: string;
    role: ChatRole;
    content: string;
}

const initialMessages: ChatMessage[] = [
    {
        id: 'welcome',
        role: 'assistant',
        content:
            "Hi — I'm your budget assistant. Tell me about your income, fixed bills, debts, or goals in your own words, and I'll help you think through a plan.",
    },
];

export default function Chat() {
    const { csrfToken } = usePage<SharedData>().props;
    const formId = useId();
    const listRef = useRef<HTMLDivElement>(null);
    const [messages, setMessages] = useState<ChatMessage[]>(initialMessages);
    const [draft, setDraft] = useState('');
    const [isSending, setIsSending] = useState(false);
    const [sendError, setSendError] = useState<string | null>(null);

    useEffect(() => {
        const el = listRef.current;
        if (el) {
            el.scrollTop = el.scrollHeight;
        }
    }, [messages]);

    function handleSubmit(e: React.FormEvent) {
        e.preventDefault();
        void submitMessage();
    }

    async function submitMessage() {
        const trimmed = draft.trim();
        if (!trimmed || isSending) {
            return;
        }

        const userMessage: ChatMessage = {
            id: createClientId(),
            role: 'user',
            content: trimmed,
        };

        const nextMessages = [...messages, userMessage];
        setMessages(nextMessages);
        setDraft('');
        setSendError(null);
        setIsSending(true);

        const payload = {
            messages: nextMessages.map(({ role, content }) => ({ role, content })),
        };

        try {
            const response = await fetch(route('chat.messages'), {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                },
                body: JSON.stringify(payload),
            });

            const data: unknown = await response.json().catch(() => null);

            if (!response.ok) {
                if (response.status === 419) {
                    setSendError('Your session expired. Refresh the page and try again.');
                    return;
                }
                const message =
                    typeof data === 'object' &&
                    data !== null &&
                    'message' in data &&
                    typeof (data as { message: unknown }).message === 'string'
                        ? (data as { message: string }).message
                        : 'Something went wrong. Please try again.';
                setSendError(message);
                return;
            }

            if (
                typeof data !== 'object' ||
                data === null ||
                !('content' in data) ||
                typeof (data as { content: unknown }).content !== 'string'
            ) {
                setSendError('Unexpected response from the assistant. Please try again.');
                return;
            }

            const content = (data as { content: string }).content.trim();
            if (!content) {
                setSendError('The assistant returned an empty reply. Try rephrasing.');
                return;
            }

            setMessages((prev) => [
                ...prev,
                {
                    id: createClientId(),
                    role: 'assistant',
                    content,
                },
            ]);
        } catch {
            setSendError('Network error. Check your connection and try again.');
        } finally {
            setIsSending(false);
        }
    }

    function handleComposerKeyDown(e: React.KeyboardEvent<HTMLTextAreaElement>) {
        if (e.key !== 'Enter' && e.key !== 'NumpadEnter') {
            return;
        }
        if (e.shiftKey) {
            return;
        }
        if (e.nativeEvent.isComposing) {
            return;
        }
        e.preventDefault();
        void submitMessage();
    }

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Chat" />
            <div className="flex min-h-0 flex-1 flex-col">
                <div
                    ref={listRef}
                    className="min-h-0 flex-1 space-y-3 overflow-y-auto overscroll-y-contain px-3 pt-1 pb-2 md:px-4"
                    role="log"
                    aria-live="polite"
                    aria-relevant="additions"
                >
                    {messages.map((m) => (
                        <div
                            key={m.id}
                            className={cn('flex w-full', m.role === 'user' ? 'justify-end' : 'justify-start')}
                        >
                            <div
                                className={cn(
                                    'max-w-[min(100%,28rem)] rounded-2xl px-3 py-2.5 text-[15px] leading-snug md:text-sm',
                                    m.role === 'assistant'
                                        ? 'bg-muted text-foreground'
                                        : 'bg-primary text-primary-foreground',
                                )}
                            >
                                {m.content}
                            </div>
                        </div>
                    ))}
                </div>

                {sendError ? (
                    <div className="shrink-0 px-3 pt-2 md:px-4">
                        <Alert variant="destructive">
                            <AlertDescription>{sendError}</AlertDescription>
                        </Alert>
                    </div>
                ) : null}

                <form
                    id={formId}
                    onSubmit={handleSubmit}
                    className="shrink-0 border-t border-border bg-background p-3 md:p-4"
                    style={{ paddingBottom: 'max(0.75rem, env(safe-area-inset-bottom))' }}
                >
                    <div className="mx-auto flex max-w-3xl items-end gap-2">
                        <label htmlFor={`${formId}-message`} className="sr-only">
                            Message
                        </label>
                        <textarea
                            id={`${formId}-message`}
                            name="message"
                            rows={1}
                            value={draft}
                            onChange={(e) => setDraft(e.target.value)}
                            onKeyDown={handleComposerKeyDown}
                            placeholder="Message your assistant…"
                            disabled={isSending}
                            className={cn(
                                'max-h-32 min-h-11 flex-1 resize-none rounded-md border border-input bg-background px-3 py-2.5 text-base',
                                'placeholder:text-muted-foreground focus-visible:outline-hidden focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2',
                                'disabled:cursor-not-allowed disabled:opacity-50',
                                'md:min-h-10 md:py-2 md:text-sm',
                            )}
                            autoComplete="off"
                            autoCorrect="on"
                        />
                        <Button
                            type="submit"
                            size="icon"
                            className="size-11 shrink-0 md:size-10"
                            disabled={!draft.trim() || isSending}
                            aria-label="Send message"
                        >
                            {isSending ? <Loader2 className="size-5 animate-spin" /> : <SendHorizontal className="size-5" />}
                        </Button>
                    </div>
                    <p className="text-muted-foreground mx-auto mt-2 max-w-3xl text-center text-xs">
                        Enter to send · Shift+Enter for a new line
                    </p>
                </form>
            </div>
        </AppLayout>
    );
}
