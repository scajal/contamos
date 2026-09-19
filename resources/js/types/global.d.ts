import "react";
import { Permission } from "./app/modules/core/identity/enums/team/permission";
import { User } from "./app/modules/core/identity/models/user";
import { Toast } from "./app/modules/support/objects/toast";

declare global {
    interface Window {
        Paddle?: {
            Checkout: {
                open: (options: Record<string, unknown>) => void;
            };
            Environment: {
                set: (environment: "sandbox" | "production") => void;
            };
            Initialize: (options: { token: string }) => void;
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
            permissions: Array<Permission>;
            user: User | null;
            locale: "en" | "es";
            appearance: "light" | "dark";
            [key: string]: unknown;
        };
        flashDataType: {
            toast?: Toast;
        };
    }
}
