import { ActivityCauser } from "@/types/app/modules/core/identity/models/team/activity";

/**
 * The changed fields whose values are instants rather
 * than plain text.
 */
export const ACTIVITY_DATE_FIELDS = [
    "accepted_at",
    "ends_at",
    "expires_at",
    "occurred_at",
    "paused_at",
    "trial_ends_at",
];

/**
 * The translated label of a changed field, so nobody needs to know
 * the column names. A field with no label of its own is shown as
 * it is stored.
 */
export const ACTIVITY_FIELD_LABELS: Record<string, string> = {
    accepted_at: "Accepted",
    account_id: "Account",
    amount: "Amount",
    category: "Category",
    currency_id: "Currency",
    description: "Description",
    email: "Email",
    ends_at: "Ends",
    expires_at: "Expires",
    is_active: "Active",
    name: "Name",
    occurred_at: "Date",
    paused_at: "Paused",
    quote: "Rate",
    role: "Role",
    slug: "Handle",
    status: "Status",
    tags: "Tags",
    trial_ends_at: "Trial ends",
    type: "Type",
};

/**
 * Name where an entry came from: somebody in the team by their own
 * name, and anybody else by what they were to the team.
 */
export function causerName(causer: ActivityCauser, t: (key: string) => string): string {
    if (causer.kind === "member" && causer.name) {
        return causer.name;
    }

    return causer.kind === "support" ? t("Support") : causer.kind === "system" ? t("System") : t("A former member");
}
