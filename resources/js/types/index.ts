export type UnitSummary = { id: string; code: string; name: string };

export type AuthUser = {
    id: string;
    name: string;
    email: string;
    unit: UnitSummary | null;
    roles: string[];
    permissions: string[];
};

export type NotificationItem = {
    id: string;
    data: { title?: string; message?: string; url?: string };
    created_at: string | null;
};

export type SharedProps = {
    app: { name: string };
    system: { policy_version: string; data_classification: string; maintenance_message: string | null; support_message: string };
    auth: { user: AuthUser | null };
    notifications: NotificationItem[];
    flash: { success?: string; error?: string };
    errors?: Record<string, string>;
    [key: string]: unknown;
};

export type PaginationLink = { url: string | null; label: string; active: boolean };
export type Paginated<T> = {
    data: T[];
    current_page: number;
    last_page: number;
    from: number | null;
    to: number | null;
    total: number;
    links: PaginationLink[];
};

export type ActivitySummary = {
    id: string;
    code: string;
    title: string;
    status: string;
    starts_at: string;
    ends_at: string;
    budget_ceiling: string;
    mandate?: { id: string; number: string; title: string };
    pic?: { id: string; name: string };
    lpj?: { id: string; status: string; current_version: number };
    participants_count?: number;
    transactions_count?: number;
    evidence_count?: number;
    can_update?: boolean;
    can_delete?: boolean;
};

export type LpjSummary = {
    id: string;
    status: string;
    current_version: number;
    total_amount: string;
    updated_at: string;
    activity: { id: string; code: string; title: string; starts_at?: string; ends_at?: string; unit?: UnitSummary };
    owner: { id: string; name: string };
    current_version_record?: { verifications?: Array<{ id: string; stage: string; status: string }> } | null;
};
