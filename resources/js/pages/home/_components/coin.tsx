import { m } from "motion/react";
import type { MotionStyle } from "motion/react";

import { cn } from "@/lib/utils";

interface CoinProps {
    src: string;
    size: number;
    className?: string;
    /** Motion values (e.g. scroll-linked scale/blur) merged into the wrapper's style. */
    style?: MotionStyle;
}

/**
 * A decorative, Blender-rendered 3D coin (CC0, 3dicons.co). Static by
 * default — any motion (scroll-linked scale, blur, …) is driven by
 * the `style` prop the caller passes in. Purely decorative — always
 * aria-hidden.
 */
export const Coin = ({ src, size, className, style }: CoinProps) => (
    <m.div
        aria-hidden="true"
        className={cn("pointer-events-none absolute select-none", className)}
        style={{ width: size, height: size, ...style }}
    >
        <img src={src} alt="" width={size} height={size} className="size-full object-contain" />
    </m.div>
);
