import { m, useReducedMotion } from "motion/react";

interface Lobe {
    color: string;
    /** Where the lobe sits and how big it is, as Tailwind utilities. */
    className: string;
    /** The loop it drifts along, in pixels. */
    drift: { x: Array<number>; y: Array<number> };
    /** Seconds for one full loop; each differs so they never sync up. */
    duration: number;
}

/**
 * The three lobes the ribbon is built from. Each is a soft radial blob
 * that drifts on its own slow loop; overlapped and heavily blurred they
 * read as one continuous band of light rather than three circles.
 */
const LOBES: Array<Lobe> = [
    {
        color: "oklch(0.72 0.19 25)",
        className: "left-[6%] h-[420px] w-[46%]",
        drift: { x: [0, 40, 0], y: [0, -26, 0] },
        duration: 19,
    },
    {
        color: "oklch(0.74 0.16 265)",
        className: "left-[28%] h-[480px] w-[52%]",
        drift: { x: [0, -34, 0], y: [0, 30, 0] },
        duration: 23,
    },
    {
        color: "oklch(0.78 0.15 190)",
        className: "right-[4%] h-[400px] w-[44%]",
        drift: { x: [0, 26, 0], y: [0, 22, 0] },
        duration: 27,
    },
];

/**
 * An abstract band of colour behind the plans: layered radial lobes,
 * blurred far past recognition and skewed into a diagonal, with a mask
 * fading them out at every edge so the band never meets a hard border.
 *
 * It is the page's one piece of free colour, so it is kept at low
 * opacity — enough to warm the section, not enough to fight the cards
 * or the price for attention. Holds still for reduced-motion visitors.
 */
export const GradientRibbon = () => {
    /**
     * Whether the visitor prefers reduced motion.
     */
    const reducedMotion = useReducedMotion();

    return (
        <div
            aria-hidden="true"
            className="pointer-events-none absolute inset-x-0 top-[18%] h-[560px] overflow-hidden [mask-image:radial-gradient(70%_60%_at_50%_50%,black,transparent)]"
        >
            <div className="absolute inset-0 origin-center [transform:skewY(-7deg)]">
                {LOBES.map((lobe, index) => (
                    <m.div
                        key={index}
                        className={`absolute top-1/2 -translate-y-1/2 rounded-full opacity-45 blur-[110px] dark:opacity-35 ${lobe.className}`}
                        style={{ background: lobe.color }}
                        animate={reducedMotion ? undefined : lobe.drift}
                        transition={{ duration: lobe.duration, repeat: Infinity, ease: "easeInOut" }}
                    />
                ))}
            </div>
        </div>
    );
};
