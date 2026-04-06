import HeadingSmall from '@/components/heading-small';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import AppLayout from '@/layouts/app-layout';
import SettingsLayout from '@/layouts/settings/layout';
import { type BreadcrumbItem } from '@/types';
import { Transition } from '@headlessui/react';
import { Head, useForm } from '@inertiajs/react';
import { FormEventHandler } from 'react';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Assistant settings',
        href: '/settings/assistant',
    },
];

interface AssistantSettingsProps {
    aiChatProvider: string | null;
    aiChatModel: string | null;
}

export default function Assistant({ aiChatProvider, aiChatModel }: AssistantSettingsProps) {
    const { data, setData, patch, errors, processing, recentlySuccessful } = useForm({
        ai_chat_provider: aiChatProvider === 'groq' ? 'groq' : 'openai',
        ai_chat_model: aiChatModel ?? '',
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        patch(route('assistant.update'));
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Assistant settings" />

            <SettingsLayout>
                <div className="space-y-6">
                    <HeadingSmall
                        title="Chat assistant"
                        description="Choose which provider powers the chat and optionally override the model name (leave blank to use the server default)."
                    />

                    <form onSubmit={submit} className="space-y-6">
                        <div className="grid gap-2">
                            <Label htmlFor="ai_chat_provider">Provider</Label>
                            <Select
                                value={data.ai_chat_provider}
                                onValueChange={(v) => setData('ai_chat_provider', v)}
                            >
                                <SelectTrigger id="ai_chat_provider" className="w-full max-w-md">
                                    <SelectValue placeholder="Provider" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="openai">OpenAI</SelectItem>
                                    <SelectItem value="groq">Groq</SelectItem>
                                </SelectContent>
                            </Select>
                            <InputError className="mt-2" message={errors.ai_chat_provider} />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="ai_chat_model">Model (optional)</Label>
                            <Input
                                id="ai_chat_model"
                                className="max-w-md"
                                value={data.ai_chat_model}
                                onChange={(e) => setData('ai_chat_model', e.target.value)}
                                placeholder="e.g. gpt-4o-mini or llama-3.3-70b-versatile"
                                autoComplete="off"
                            />
                            <p className="text-muted-foreground text-sm">
                                Groq uses OpenAI-compatible model ids — check Groq docs for current names.
                            </p>
                            <InputError className="mt-2" message={errors.ai_chat_model} />
                        </div>

                        <div className="flex items-center gap-4">
                            <Button disabled={processing}>Save</Button>

                            <Transition
                                show={recentlySuccessful}
                                enter="transition ease-in-out"
                                enterFrom="opacity-0"
                                leave="transition ease-in-out"
                                leaveTo="opacity-0"
                            >
                                <p className="text-sm text-neutral-600">Saved</p>
                            </Transition>
                        </div>
                    </form>
                </div>
            </SettingsLayout>
        </AppLayout>
    );
}
