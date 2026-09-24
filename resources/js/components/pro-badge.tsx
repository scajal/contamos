import { useLaravelReactI18n } from "laravel-react-i18n";

import { Badge } from "@/components/ui/badge";
import { cn } from "@/lib/utils";

type Props = {
    className?: string;
};

/**
 * Marks a page, a feature or an example as needing the Pro plan,
 * so a reader knows before trying it whether their team can.
 */
export const ProBadge = ({ className }: Props) => {
    /**
     * Translate the provided string
     * to the current locale.
     */
    const t = useLaravelReactI18n().t;

    return (
        <Badge className={cn("h-4.5 px-1.5 text-[0.6875rem]", className)} variant="outline">
            {t("Pro")}
        </Badge>
    );
};
