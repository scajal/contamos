import "react";
import { User } from "./app/modules/core/models/user";

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
    }
}
