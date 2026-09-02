import { useLaravelReactI18n } from "laravel-react-i18n";
import type { ReactElement } from "react";

import {
    AlertDialog,
    AlertDialogAction,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
    AlertDialogTrigger,
} from "@/components/ui/alert-dialog";

type Props = {
    title: string;
    description?: string;
    confirmLabel?: string;
    cancelLabel?: string;
    action: ReactElement;
    children: ReactElement;
};

export function ConfirmAction({ title, description, confirmLabel, cancelLabel, action, children }: Props) {
    /**
     * Translate the provided string
     * to the current locale.
     */
    const t = useLaravelReactI18n().t;

    return (
        <AlertDialog>
            <AlertDialogTrigger render={children} />
            <AlertDialogContent size="sm">
                <AlertDialogHeader>
                    <AlertDialogTitle>{title}</AlertDialogTitle>
                    {description ? <AlertDialogDescription>{description}</AlertDialogDescription> : null}
                </AlertDialogHeader>
                <AlertDialogFooter>
                    <AlertDialogCancel>{cancelLabel ?? t("Cancel")}</AlertDialogCancel>
                    <AlertDialogAction nativeButton={false} render={action} variant="destructive">
                        {confirmLabel ?? t("Continue")}
                    </AlertDialogAction>
                </AlertDialogFooter>
            </AlertDialogContent>
        </AlertDialog>
    );
}
