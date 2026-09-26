import "react";
import { Permission } from "./app/modules/core/identity/enums/team/permission";
import { User } from "./app/modules/core/identity/models/user";
import { AnalyticsEvent } from "./app/modules/support/objects/analytics-event";
import { Toast } from "./app/modules/support/objects/toast";
import { Onboarding } from "./app/modules/support/onboarding";

declare global {
    interface Window {
        gaMeasurementId?: string;
        gtag?: (...args: unknown[]) => void;
        Paddle?: {
            Checkout: {
                open: (options: Record<string, unknown>) => void;
            };
            Environment: {
                set: (environment: "sandbox" | "production") => void;
            };
            Initialize: (options: {
                token: string;
                eventCallback?: (event: { name: string; data?: Record<string, unknown> }) => void;
            }) => void;
        };
    }
}

declare module "react" {
    interface InputHTMLAttributes<T> {
        passwordrules?: string;
    }
}

declare module "@inertiajs/core" {
    export interface InertiaConfig {
        sharedPageProps: {
            auth: { canAccessAdministration: boolean };
            onboarding: Onboarding | null;
            permissions: Array<Permission>;
            user: User | null;
            locale: "en" | "es";
            appearance: "light" | "dark" | "system";
            [key: string]: unknown;
        };
        flashDataType: {
            analytics?: Array<AnalyticsEvent>;
            toast?: Toast;
        };
    }
}
