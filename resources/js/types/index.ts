import { LucideIcon } from 'lucide-react';

export interface Auth {
    user: User;
}

export interface BreadcrumbItem {
    title: string;
    href: string;
}

export interface NavGroup {
    title: string;
    items: NavItem[];
}

export interface NavItem {
    title: string;
    url: string;
    icon?: LucideIcon | null;
    isActive?: boolean;
}

export interface SharedData {
    name: string;
    quote: { message: string; author: string };
    auth: Auth;
    csrfToken: string;
    [key: string]: unknown;
}

export interface BudgetOverviewRow {
    category: string;
    budget_cents: number;
    fixed_commitments_cents: number | null;
    previous_budget_cents: number | null;
    spent_cents: number;
    remaining_cents: number;
    percent_used: number | null;
    currency: string;
    notes: string | null;
}

export interface BudgetOverviewProps {
    year_month: string;
    label: string;
    today_label: string;
    rows: BudgetOverviewRow[];
    spend_outside_budget_slots: Array<{ category: string; spent_cents: number; currency: string }>;
    totals: {
        budget_cents: number;
        fixed_commitments_cents: number;
        monthly_income_cents: number;
        health_percent: number | null;
        spent_cents: number;
        remaining_vs_budget_cents: number;
        spent_percent_of_planned: number | null;
        today_spent_cents: number;
    };
    canned_prompts: Array<{ label: string; text: string }>;
}

export interface ChatPageProps extends SharedData {
    needsFinancialOnboarding: boolean;
    chatWelcome: string;
    budgetOverview: BudgetOverviewProps;
}

export interface User {
    id: number;
    name: string;
    email: string;
    avatar?: string;
    email_verified_at: string | null;
    created_at: string;
    updated_at: string;
    ai_chat_provider?: string | null;
    ai_chat_model?: string | null;
    [key: string]: unknown; // This allows for additional properties...
}
