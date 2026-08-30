import "react";
import { User } from "./app/modules/core/identity/models/user";
import { Toast } from "./app/modules/support/objects/toast";

declare module "react" {
    interface InputHTMLAttributes<T> {
        passwordrules?: string;
    }
}

declare module "@inertiajs/core" {
    export interface InertiaConfig {
        sharedPageProps: {
            user: User | null;
            [key: string]: unknown;
        };
        flashDataType: {
            toast?: Toast;
        };
    }
}
