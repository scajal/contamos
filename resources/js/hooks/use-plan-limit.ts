import { usePage } from "@inertiajs/react";

type BillingFeature = "accounts" | "members" | "transactions";

/**
 * Whether the current team has reached its plan's limit for the
 * given feature. Unlimited (null limit) features never trip this.
 */
export const usePlanLimit = (feature: BillingFeature): boolean => {
    const { billing } = usePage().props;
    const limit = billing.limits?.[feature] ?? null;

    if (limit === null) {
        return false;
    }

    return (billing.usage?.[feature] ?? 0) >= limit;
};
