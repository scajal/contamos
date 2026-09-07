import { m, useMotionValue, useReducedMotion, useSpring, useTransform } from "motion/react";
import type { MouseEvent as ReactMouseEvent, ReactNode } from "react";

import { cn } from "@/lib/utils";

/**
 * A card that tilts gently toward the cursor, giving it a light 3D
 * presence. Disabled for reduced-motion visitors.
 */
export const TiltCard = ({ children, className }: { children: ReactNode; className?: string }) => {
    /**
     * Whether the visitor prefers reduced motion.
     */
    const reducedMotion = useReducedMotion();

    /**
     * The pointer's position over the card, normalized to 0-1.
     */
    const pointerX = useMotionValue(0.5);
    const pointerY = useMotionValue(0.5);
    const springs = { stiffness: 220, damping: 26 };
    const rotateX = useSpring(useTransform(pointerY, [0, 1], [4, -4]), springs);
    const rotateY = useSpring(useTransform(pointerX, [0, 1], [-4, 4]), springs);

    /**
     * Track the pointer's position over the card.
     */
    const handleMove = (event: ReactMouseEvent<HTMLDivElement>) => {
        const rect = event.currentTarget.getBoundingClientRect();

        pointerX.set((event.clientX - rect.left) / rect.width);
        pointerY.set((event.clientY - rect.top) / rect.height);
    };

    /**
     * Reset the tilt to its neutral position.
     */
    const handleLeave = () => {
        pointerX.set(0.5);
        pointerY.set(0.5);
    };

    return (
        <m.div
            className={cn("bg-background rounded-2xl border p-6 shadow-md", className)}
            style={reducedMotion ? undefined : { rotateX, rotateY, transformPerspective: 800 }}
            onMouseMove={reducedMotion ? undefined : handleMove}
            onMouseLeave={reducedMotion ? undefined : handleLeave}
        >
            {children}
        </m.div>
    );
};
