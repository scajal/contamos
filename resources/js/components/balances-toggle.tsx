import { useLaravelReactI18n } from "laravel-react-i18n";
import { EyeIcon, EyeOffIcon } from "lucide-react";

import { Button } from "@/components/ui/button";
import { Tooltip, TooltipContent, TooltipTrigger } from "@/components/ui/tooltip";
import { useBalances } from "@/hooks/use-balances";

/**
 * Masks every amount in the app, for the moments the screen is not only
 * yours. The choice is remembered in this browser, so a device somebody
 * else looks at keeps its balances covered between visits.
 */
export const BalancesToggle = () => {
    /**
     * Translate the provided string to the
     * current locale.
     */
    const t = useLaravelReactI18n().t;

    /**
     * Whether amounts are masked, and the way to flip that.
     */
    const { hidden, toggle } = useBalances();

    /**
     * What the button does next, which is also what it is called.
     */
    const label = hidden ? t("Show balances") : t("Hide balances");

    return (
        <Tooltip>
            <TooltipTrigger
                render={
                    <Button aria-pressed={hidden} onClick={toggle} size="icon-sm" variant="ghost">
                        {hidden ? <EyeOffIcon /> : <EyeIcon />}
                        <span className="sr-only">{label}</span>
                    </Button>
                }
            />
            <TooltipContent>{label}</TooltipContent>
        </Tooltip>
    );
};
