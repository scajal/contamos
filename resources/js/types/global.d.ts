import "react";
import { User } from "./app/modules/core/identity/models/user";
import { Toast } from "./app/modules/support/objects/toast";

declare module "react" {
    interface InputHTMLAttributes<T> {
        passwordrules?: string;
    }
}

type BillingFeature = "accounts" | "members" | "transactions";

declare global {
    interface Window {
        Paddle?: {
            Checkout: {
                open: (options: Record<string, unknown>) => void;
            };
            Environment: {
                set: (environment: "sandbox" | "production") => void;
            };
        };
    }
}

declare module "@inertiajs/core" {
    export interface InertiaConfig {
        sharedPageProps: {
            auth: { canAccessAdministration: boolean };
            billing: {
                plan: "free" | "pro" | null;
                limits: Record<BillingFeature, number | null> | null;
                usage: Record<BillingFeature, number> | null;
                can_join_free_team: boolean;
            };
            user: User | null;
            [key: string]: unknown;
        };
        flashDataType: {
            toast?: Toast;
        };
    }
}
