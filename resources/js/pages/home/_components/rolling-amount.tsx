import NumberFlow from "@number-flow/react";
import { useInView } from "motion/react";
import { useRef } from "react";

import { cn } from "@/lib/utils";

interface RollingAmountProps {
    value: number;
    decimals: number;
    locale: string;
    className?: string;
}

/**
 * An amount that spins up from zero on its way into view, one reel per
 * digit, the way an odometer lands. NumberFlow handles the reels and
 * respects the visitor's reduced-motion preference on its own.
 */
export const RollingAmount = ({ value, decimals, locale, className }: RollingAmountProps) => {
    const ref = useRef<HTMLSpanElement>(null);
    const inView = useInView(ref, { once: true, margin: "-80px" });

    return (
        <span ref={ref} className={cn("inline-flex leading-none tabular-nums", className)}>
            <NumberFlow
                value={inView ? value : 0}
                locales={locale}
                format={{ minimumFractionDigits: decimals, maximumFractionDigits: decimals }}
                spinTiming={{ duration: 1400, easing: "cubic-bezier(0.16, 1, 0.3, 1)" }}
                transformTiming={{ duration: 900, easing: "cubic-bezier(0.16, 1, 0.3, 1)" }}
                willChange
            />
        </span>
    );
};
