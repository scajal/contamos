import { m, useReducedMotion } from "motion/react";
import type { ReactNode } from "react";

type RevealTag = "div" | "li";

interface RevealProps {
    children: ReactNode;
    delay?: number;
    className?: string;
    as?: RevealTag;
}

/**
 * Fades and slides its children up as they scroll into view, once.
 * A no-op (children render at their final state immediately) for
 * reduced-motion visitors.
 */
export const Reveal = ({ children, delay = 0, className, as = "div" }: RevealProps) => {
    /**
     * Whether the visitor prefers reduced motion.
     */
    const reducedMotion = useReducedMotion();

    const Component = as === "li" ? m.li : m.div;

    return (
        <Component
            initial={reducedMotion ? false : { opacity: 0, y: 20 }}
            whileInView={{ opacity: 1, y: 0 }}
            viewport={{ once: true, margin: "-80px" }}
            transition={{ duration: 0.5, ease: "easeOut", delay }}
            className={className}
        >
            {children}
        </Component>
    );
};
