import { cn } from "cn";
import { useLaravelReactI18n } from "laravel-react-i18n";
import { Loader2Icon } from "lucide-react";

function Spinner({ className, ...props }: React.ComponentProps<"svg">) {
    const { t } = useLaravelReactI18n();

    return (
        <Loader2Icon
            data-slot="spinner"
            role="status"
            aria-label={t("Loading")}
            className={cn("size-4 animate-spin", className)}
            {...props}
        />
    );
}

export { Spinner };
