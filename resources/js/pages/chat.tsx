import { Alert, AlertDescription } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import { cn, createClientId, stripAssistantInlineToolMarkup } from '@/lib/utils';
import { type BreadcrumbItem, type ChatPageProps } from '@/types';
import { Head, router, usePage } from '@inertiajs/react';
import { Loader2, MapPin, SendHorizontal } from 'lucide-react';
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

interface PendingToolCall {
    id: string;
    tool_calls: unknown[];
}

/** Matches server fallback when the model returns tool calls with no message text. */
const PENDING_PLACEHOLDER_ASSISTANT_TEXT =
    'I can save a few details to your profile. Please confirm before I apply them.';

function formatMoneyFromCents(cents: number, currency: string): string {
    const safe = Number.isFinite(cents) ? cents : 0;
    return `${currency} ${(safe / 100).toFixed(2)}`;
}

function summarizePendingToolCalls(toolCalls: unknown[]): string[] {
    const lines: string[] = [];

    for (const call of toolCalls) {
        if (typeof call !== 'object' || call === null) {
            continue;
        }

        const fn = (call as any).function;
        const fnName = fn?.name;
        const rawArgs = fn?.arguments;

        if (typeof fnName !== 'string' || typeof rawArgs !== 'string') {
            continue;
        }

        let args: any = null;
        try {
            args = JSON.parse(rawArgs);
        } catch {
            args = null;
        }

        if (!args || typeof args !== 'object') {
            continue;
        }

        if (fnName === 'upsert_commitment') {
            const name = typeof args.name === 'string' ? args.name : 'Commitment';
            const amountCents = typeof args.amount_cents === 'number' ? args.amount_cents : null;
            const category = typeof args.category === 'string' ? args.category : null;
            const dueDay = typeof args.due_day === 'number' ? args.due_day : null;
            const currency = typeof args.currency === 'string' ? args.currency : 'MYR';

            const amount =
                typeof amountCents === 'number' && Number.isFinite(amountCents) ? `${currency} ${(amountCents / 100).toFixed(2)}` : null;

            const extras = [category ? `Category: ${category}` : null, dueDay ? `Due: ${dueDay}` : null].filter(Boolean).join(' · ');

            lines.push(`• ${name}${amount ? ` — ${amount}` : ''}${extras ? ` (${extras})` : ''}`);
        } else if (fnName === 'upsert_monthly_budget_allocation') {
            const ym = typeof args.year_month === 'string' ? args.year_month : '';
            const category = typeof args.category === 'string' ? args.category : 'Category';
            const amountCents = typeof args.amount_cents === 'number' ? args.amount_cents : null;
            const currency = typeof args.currency === 'string' ? args.currency : 'MYR';
            const amount =
                typeof amountCents === 'number' && Number.isFinite(amountCents)
                    ? formatMoneyFromCents(amountCents, currency)
                    : null;
            lines.push(`• Budget slot: ${category} @ ${ym}${amount ? ` — ${amount}` : ''}`);
        } else if (fnName === 'delete_monthly_budget_allocation') {
            const ym = typeof args.year_month === 'string' ? args.year_month : '';
            const category = typeof args.category === 'string' ? args.category : 'Category';
            lines.push(`• Remove budget slot: ${category} @ ${ym}`);
        } else if (fnName === 'log_expense') {
            const category = typeof args.category === 'string' ? args.category : 'Category';
            const amountCents = typeof args.amount_cents === 'number' ? args.amount_cents : null;
            const currency = typeof args.currency === 'string' ? args.currency : 'MYR';
            const place = typeof args.place_label === 'string' ? args.place_label : null;
            const amount =
                typeof amountCents === 'number' && Number.isFinite(amountCents)
                    ? formatMoneyFromCents(amountCents, currency)
                    : null;
            const extra = place ? ` @ ${place}` : '';
            lines.push(`• Log expense: ${category}${amount ? ` — ${amount}` : ''}${extra}`);
        } else if (fnName === 'delete_expense') {
            const id = typeof args.id === 'number' ? args.id : '?';
            const ym = typeof args.year_month === 'string' ? args.year_month : null;
            lines.push(`• Delete expense #${id}${ym ? ` (${ym})` : ''}`);
        }
    }

    return lines;
}

export default function Chat() {
    const { csrfToken, needsFinancialOnboarding, chatWelcome, budgetOverview } = usePage<ChatPageProps>().props;
    const formId = useId();
    const listRef = useRef<HTMLDivElement>(null);
    const [messages, setMessages] = useState<ChatMessage[]>(() => [
        {
            id: 'welcome',
            role: 'assistant',
            content: chatWelcome,
        },
    ]);
    const [draft, setDraft] = useState('');
    const [isSending, setIsSending] = useState(false);
    const [sendError, setSendError] = useState<string | null>(null);
    const [isCompletingOnboarding, setIsCompletingOnboarding] = useState(false);
    const [pending, setPending] = useState<PendingToolCall | null>(null);
    const [isApplyingPending, setIsApplyingPending] = useState(false);
    const [attachLocation, setAttachLocation] = useState(false);

    useEffect(() => {
        const el = listRef.current;
        if (el) {
            el.scrollTop = el.scrollHeight;
        }
    }, [messages, pending]);

    function handleSubmit(e: React.FormEvent) {
        e.preventDefault();
        void submitMessage();
    }

    async function submitMessage() {
        const trimmed = draft.trim();
        if (!trimmed || isSending || pending) {
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

        let clientContext: { location: { latitude: number; longitude: number; accuracy?: number } } | undefined;

        if (attachLocation && typeof navigator !== 'undefined' && navigator.geolocation) {
            try {
                const pos = await new Promise<GeolocationPosition>((resolve, reject) => {
                    navigator.geolocation.getCurrentPosition(resolve, reject, {
                        enableHighAccuracy: true,
                        timeout: 12000,
                        maximumAge: 0,
                    });
                });
                clientContext = {
                    location: {
                        latitude: pos.coords.latitude,
                        longitude: pos.coords.longitude,
                        ...(typeof pos.coords.accuracy === 'number' ? { accuracy: pos.coords.accuracy } : {}),
                    },
                };
            } catch {
                // Send without location if permission denied or unavailable.
            }
        }

        const payload: Record<string, unknown> = {
            messages: nextMessages.map(({ role, content }) => ({ role, content })),
        };
        if (clientContext) {
            payload.client_context = clientContext;
        }

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

            if ('pending' in (data as Record<string, unknown>) && typeof (data as any).pending === 'object' && (data as any).pending) {
                const p = (data as any).pending as { id?: unknown; tool_calls?: unknown };
                if (typeof p.id === 'string' && Array.isArray(p.tool_calls)) {
                    setPending({ id: p.id, tool_calls: p.tool_calls });
                }
            }
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

    function completeOnboarding() {
        setIsCompletingOnboarding(true);
        router.post(
            route('chat.onboarding.complete'),
            {},
            {
                preserveScroll: true,
                onFinish: () => setIsCompletingOnboarding(false),
            },
        );
    }

    async function confirmPending() {
        if (!pending || isApplyingPending) {
            return;
        }
        setIsApplyingPending(true);
        setSendError(null);
        try {
            const response = await fetch(route('chat.pending_tools.confirm', pending.id), {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                },
                body: JSON.stringify({}),
            });
            const data: unknown = await response.json().catch(() => null);
            if (!response.ok) {
                const message =
                    typeof data === 'object' &&
                    data !== null &&
                    'message' in data &&
                    typeof (data as { message: unknown }).message === 'string'
                        ? (data as { message: string }).message
                        : 'Could not apply changes. Please try again.';
                setSendError(message);
                return;
            }
            if (typeof data === 'object' && data !== null && 'content' in data && typeof (data as any).content === 'string') {
                const content = ((data as any).content as string).trim();
                setMessages((prev) => {
                    const placeholderIdx = prev.findLastIndex(
                        (m) => m.role === 'assistant' && m.content.trim() === PENDING_PLACEHOLDER_ASSISTANT_TEXT,
                    );

                    if (placeholderIdx !== -1) {
                        const next = [...prev];
                        if (content) {
                            next[placeholderIdx] = {
                                ...next[placeholderIdx],
                                content,
                            };
                            return next;
                        }
                        next.splice(placeholderIdx, 1);
                        return next;
                    }

                    if (content) {
                        return [...prev, { id: createClientId(), role: 'assistant', content }];
                    }

                    return prev;
                });
            }
            setPending(null);
        } catch {
            setSendError('Network error. Check your connection and try again.');
        } finally {
            setIsApplyingPending(false);
        }
    }

    async function cancelPending() {
        if (!pending || isApplyingPending) {
            return;
        }
        setIsApplyingPending(true);
        setSendError(null);
        try {
            await fetch(route('chat.pending_tools.cancel', pending.id), {
                method: 'DELETE',
                credentials: 'same-origin',
                headers: {
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                },
            });
        } finally {
            setPending(null);
            setIsApplyingPending(false);
        }
    }

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Chat" />
            <div className="flex h-[calc(100dvh-4rem)] min-h-0 w-full max-w-full flex-col overflow-hidden">
                {needsFinancialOnboarding ? (
                    <div className="border-sidebar-border/50 shrink-0 border-b px-3 py-3 md:px-4">
                        <div className="bg-muted/60 mx-auto max-w-3xl rounded-xl border border-border/80 px-3 py-3 md:px-4">
                            <p className="text-foreground text-sm leading-snug">
                                <span className="font-medium">First-time setup:</span> chat casually about your income, bills, loans, and cards.
                                When you’re happy with what you’ve shared, continue below.
                            </p>
                            <Button
                                type="button"
                                variant="secondary"
                                size="sm"
                                className="mt-3 w-full sm:w-auto"
                                disabled={isCompletingOnboarding}
                                onClick={() => completeOnboarding()}
                            >
                                {isCompletingOnboarding ? (
                                    <>
                                        <Loader2 className="size-4 animate-spin" />
                                        Saving…
                                    </>
                                ) : (
                                    'Done — start budgeting'
                                )}
                            </Button>
                        </div>
                    </div>
                ) : null}

                {!needsFinancialOnboarding ? (
                    <div className="border-sidebar-border/50 shrink-0 border-b px-3 py-3 md:px-4">
                        <div className="bg-muted/60 mx-auto max-w-3xl rounded-xl border border-border/80 px-3 py-3 md:px-4">
                            <div className="flex flex-wrap items-baseline justify-between gap-2">
                                <h2 className="text-foreground text-sm font-semibold">Monthly budget</h2>
                                <p className="text-muted-foreground text-xs">
                                    {budgetOverview.label} · {budgetOverview.year_month}
                                </p>
                            </div>
                            <p className="text-muted-foreground mt-1 text-xs">
                                <span className="font-medium text-foreground">Today ({budgetOverview.today_label}):</span>{' '}
                                {formatMoneyFromCents(
                                    budgetOverview.totals.today_spent_cents,
                                    budgetOverview.rows[0]?.currency ?? 'MYR',
                                )}{' '}
                                <span className="font-normal">saved expenses only</span>
                            </p>
                            <div className="text-muted-foreground mt-2 grid gap-2 text-xs leading-snug sm:grid-cols-2">
                                <div>
                                    <span className="font-medium text-foreground">Planned total</span>{' '}
                                    {formatMoneyFromCents(
                                        budgetOverview.totals.budget_cents,
                                        budgetOverview.rows[0]?.currency ?? 'MYR',
                                    )}
                                </div>
                                <div>
                                    <span className="font-medium text-foreground">Logged spend (month)</span>{' '}
                                    {formatMoneyFromCents(
                                        budgetOverview.totals.spent_cents,
                                        budgetOverview.rows[0]?.currency ?? 'MYR',
                                    )}
                                </div>
                                <div>
                                    <span className="font-medium text-foreground">Fixed commitments (all)</span>{' '}
                                    {formatMoneyFromCents(
                                        budgetOverview.totals.fixed_commitments_cents,
                                        budgetOverview.rows[0]?.currency ?? 'MYR',
                                    )}
                                </div>
                                <div>
                                    <span className="font-medium text-foreground">Left vs planned slots</span>{' '}
                                    {formatMoneyFromCents(
                                        budgetOverview.totals.remaining_vs_budget_cents,
                                        budgetOverview.rows[0]?.currency ?? 'MYR',
                                    )}
                                </div>
                                <div>
                                    <span className="font-medium text-foreground">Monthly income (ref.)</span>{' '}
                                    {formatMoneyFromCents(
                                        budgetOverview.totals.monthly_income_cents,
                                        budgetOverview.rows[0]?.currency ?? 'MYR',
                                    )}
                                </div>
                                <div>
                                    <span className="font-medium text-foreground">Planned vs income</span>{' '}
                                    {budgetOverview.totals.health_percent === null
                                        ? '—'
                                        : `${budgetOverview.totals.health_percent.toFixed(1)}%`}
                                </div>
                                <div>
                                    <span className="font-medium text-foreground">Spend vs planned</span>{' '}
                                    {budgetOverview.totals.spent_percent_of_planned === null
                                        ? '—'
                                        : `${budgetOverview.totals.spent_percent_of_planned.toFixed(1)}%`}
                                </div>
                            </div>
                            {budgetOverview.rows.length > 0 ? (
                                <div className="mt-3 overflow-x-auto">
                                    <table className="w-full min-w-[40rem] text-left text-xs">
                                        <thead>
                                            <tr className="text-muted-foreground border-b border-border/80">
                                                <th className="py-1.5 pr-2 font-medium">Category</th>
                                                <th className="py-1.5 pr-2 font-medium">Planned</th>
                                                <th className="py-1.5 pr-2 font-medium">Spent</th>
                                                <th className="py-1.5 pr-2 font-medium">Left</th>
                                                <th className="py-1.5 pr-2 font-medium">%</th>
                                                <th className="py-1.5 pr-2 font-medium">Fixed</th>
                                                <th className="py-1.5 pr-2 font-medium">Last mo.</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            {budgetOverview.rows.map((row) => (
                                                <tr key={row.category} className="border-b border-border/40 last:border-0">
                                                    <td className="text-foreground py-1.5 pr-2 font-medium">{row.category}</td>
                                                    <td className="py-1.5 pr-2">{formatMoneyFromCents(row.budget_cents, row.currency)}</td>
                                                    <td className="py-1.5 pr-2">{formatMoneyFromCents(row.spent_cents, row.currency)}</td>
                                                    <td className="py-1.5 pr-2">{formatMoneyFromCents(row.remaining_cents, row.currency)}</td>
                                                    <td className="py-1.5 pr-2">
                                                        {row.percent_used === null ? '—' : `${row.percent_used.toFixed(0)}%`}
                                                    </td>
                                                    <td className="py-1.5 pr-2">
                                                        {row.fixed_commitments_cents !== null
                                                            ? formatMoneyFromCents(row.fixed_commitments_cents, row.currency)
                                                            : '—'}
                                                    </td>
                                                    <td className="py-1.5 pr-2">
                                                        {row.previous_budget_cents !== null
                                                            ? formatMoneyFromCents(row.previous_budget_cents, row.currency)
                                                            : '—'}
                                                    </td>
                                                </tr>
                                            ))}
                                        </tbody>
                                    </table>
                                </div>
                            ) : (
                                <p className="text-muted-foreground mt-2 text-xs">No category slots for this month yet.</p>
                            )}
                            {budgetOverview.spend_outside_budget_slots.length > 0 ? (
                                <p className="text-muted-foreground mt-2 text-xs">
                                    <span className="text-foreground font-medium">Spend outside slots: </span>
                                    {budgetOverview.spend_outside_budget_slots
                                        .map(
                                            (s) =>
                                                `${s.category} ${formatMoneyFromCents(s.spent_cents, s.currency)}`,
                                        )
                                        .join(' · ')}
                                </p>
                            ) : null}
                            <div className="mt-3 flex flex-wrap gap-2">
                                {budgetOverview.canned_prompts.map((p) => (
                                    <Button
                                        key={p.label}
                                        type="button"
                                        variant="secondary"
                                        size="sm"
                                        className="text-xs"
                                        disabled={!!pending || isSending}
                                        onClick={() => setDraft(p.text)}
                                    >
                                        {p.label}
                                    </Button>
                                ))}
                            </div>
                        </div>
                    </div>
                ) : null}

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
                                    'max-w-[min(100%,28rem)] rounded-2xl px-3 py-2.5 text-[15px] leading-snug whitespace-pre-wrap md:text-sm',
                                    m.role === 'assistant'
                                        ? 'bg-muted text-foreground'
                                        : 'bg-primary text-primary-foreground',
                                )}
                            >
                                {m.role === 'assistant' ? stripAssistantInlineToolMarkup(m.content) : m.content}
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

                {pending ? (
                    <div className="shrink-0 px-3 pt-2 md:px-4">
                        <div className="mx-auto max-w-3xl">
                            <div className="bg-muted/70 supports-[backdrop-filter]:bg-muted/50 sticky bottom-0 rounded-xl border border-border/80 px-3 py-3 backdrop-blur md:px-4">
                                <p className="text-sm leading-snug">
                                    I’m about to save changes to your financial profile. Confirm to apply.
                                </p>
                                {(() => {
                                    const summary = summarizePendingToolCalls(pending.tool_calls);
                                    if (summary.length === 0) {
                                        return null;
                                    }

                                    return (
                                        <div className="text-muted-foreground mt-2 space-y-1 text-xs leading-snug">
                                            {summary.map((line) => (
                                                <div key={line}>{line}</div>
                                            ))}
                                        </div>
                                    );
                                })()}
                                <div className="mt-3 flex gap-2">
                                    <Button type="button" onClick={() => void confirmPending()} disabled={isApplyingPending}>
                                        {isApplyingPending ? (
                                            <>
                                                <Loader2 className="size-4 animate-spin" />
                                                Applying…
                                            </>
                                        ) : (
                                            'Confirm'
                                        )}
                                    </Button>
                                    <Button
                                        type="button"
                                        variant="secondary"
                                        onClick={() => void cancelPending()}
                                        disabled={isApplyingPending}
                                    >
                                        Cancel
                                    </Button>
                                </div>
                            </div>
                        </div>
                    </div>
                ) : null}

                <form
                    id={formId}
                    onSubmit={handleSubmit}
                    className="shrink-0 border-t border-border bg-background p-3 md:p-4"
                    style={{ paddingBottom: 'max(0.75rem, env(safe-area-inset-bottom))' }}
                >
                    <div className="mx-auto mb-2 flex max-w-3xl items-center gap-2">
                        <Button
                            type="button"
                            variant={attachLocation ? 'secondary' : 'ghost'}
                            size="sm"
                            className="text-xs"
                            disabled={isSending || !!pending}
                            onClick={() => setAttachLocation((v) => !v)}
                        >
                            <MapPin className="size-3.5" />
                            {attachLocation ? 'Location on' : 'Location off'}
                        </Button>
                        <span className="text-muted-foreground text-xs">Attach GPS to your next message (optional).</span>
                    </div>
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
                            disabled={!draft.trim() || isSending || !!pending}
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
