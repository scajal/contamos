import { useLaravelReactI18n } from "laravel-react-i18n";
import { m, useMotionTemplate, useMotionValue, useReducedMotion, useSpring, useTransform } from "motion/react";
import { useState } from "react";
import type { MouseEvent as ReactMouseEvent } from "react";

import { cn } from "@/lib/utils";

import { RollingAmount } from "./rolling-amount";

const BALANCES = [
    { code: "EUR", symbol: "€", amount: 1250.4, decimals: 2 },
    { code: "USD", symbol: "$", amount: 4800, decimals: 2 },
    { code: "BTC", symbol: "₿", amount: 0.0031, decimals: 8 },
] as const;

/**
 * The first proof after the hero's promise: money stays in the currency
 * it was earned or spent in. Deliberately has no background of its own,
 * so the stage's floor grid runs underneath it.
 */
export const Balances = () => {
    /**
     * Translate the provided string to the current locale.
     */
    const { t, currentLocale } = useLaravelReactI18n();

    /**
     * Whether the visitor prefers reduced motion.
     */
    const reducedMotion = useReducedMotion();

    /**
     * The pointer's position over the panel, normalized to 0-1, driving
     * a spotlight that follows the cursor across it.
     */
    const pointerX = useMotionValue(0.5);
    const pointerY = useMotionValue(0.5);
    const springs = { stiffness: 200, damping: 24 };
    const spotlightX = useSpring(
        useTransform(pointerX, (value) => `${value * 100}%`),
        springs,
    );
    const spotlightY = useSpring(
        useTransform(pointerY, (value) => `${value * 100}%`),
        springs,
    );
    const spotlight = useMotionTemplate`radial-gradient(240px circle at ${spotlightX} ${spotlightY}, color-mix(in oklch, var(--primary) 22%, transparent), transparent 70%)`;

    /**
     * Whether the pointer is over the panel, gating both the spotlight
     * and the halo behind it.
     */
    const [hovering, setHovering] = useState(false);

    /**
     * Track the pointer's position over the panel.
     */
    const handlePointerMove = (event: ReactMouseEvent<HTMLDivElement>) => {
        const rect = event.currentTarget.getBoundingClientRect();

        pointerX.set((event.clientX - rect.left) / rect.width);
        pointerY.set((event.clientY - rect.top) / rect.height);
    };

    /**
     * Send the spotlight back to the middle when the pointer leaves.
     */
    const handlePointerLeave = () => {
        setHovering(false);
        pointerX.set(0.5);
        pointerY.set(0.5);
    };

    return (
        <section id="product" className="relative">
            <m.div
                initial={reducedMotion ? false : { opacity: 0, y: 24 }}
                whileInView={{ opacity: 1, y: 0 }}
                viewport={{ once: true, margin: "-80px" }}
                transition={{ duration: 0.6, ease: "easeOut" }}
                className="mx-auto grid max-w-5xl items-center gap-10 px-4 pt-48 pb-28 sm:px-6 sm:pt-64 sm:pb-36 md:grid-cols-2 md:gap-16"
            >
                <div className="flex flex-col gap-4">
                    <h2 className="font-heading text-3xl font-semibold tracking-tight text-balance md:text-4xl">
                        {t("Balances per currency, not a blended guess.")}
                    </h2>
                    <p className="text-muted-foreground max-w-[44ch]">
                        {t(
                            "Every account keeps its own currency — see your euros, dollars and bitcoin exactly as they are, not blended into one misleading total.",
                        )}
                    </p>
                </div>

                {/* A panel that sits on the stage like the 3D props do: soft edge, blurred glass, its own light. */}
                <div
                    className="relative"
                    onMouseMove={reducedMotion ? undefined : handlePointerMove}
                    onMouseEnter={() => setHovering(true)}
                    onMouseLeave={handlePointerLeave}
                >
                    <div
                        aria-hidden="true"
                        className={cn(
                            "absolute -inset-6 rounded-[2.5rem] blur-3xl transition-colors duration-500",
                            hovering ? "bg-primary/45" : "bg-primary/20",
                        )}
                    />
                    <div
                        className={cn(
                            "bg-card/70 relative overflow-hidden rounded-3xl border p-1.5 shadow-2xl backdrop-blur-md transition-colors duration-300",
                            hovering && "border-primary/40",
                        )}
                    >
                        {!reducedMotion && (
                            <m.div
                                aria-hidden="true"
                                className="pointer-events-none absolute inset-0 transition-opacity duration-300"
                                style={{ background: spotlight, opacity: hovering ? 1 : 0 }}
                            />
                        )}

                        <div className="bg-background/40 relative rounded-[1.15rem]">
                            {BALANCES.map((balance, index) => (
                                <div
                                    key={balance.code}
                                    className={cn(
                                        "flex items-center justify-between gap-6 px-5 py-4",
                                        index > 0 && "border-t",
                                    )}
                                >
                                    <div className="flex items-center gap-3">
                                        <span
                                            aria-hidden="true"
                                            className="bg-muted/60 text-muted-foreground grid size-9 place-items-center rounded-full text-sm"
                                        >
                                            {balance.symbol}
                                        </span>
                                        <span className="text-muted-foreground text-sm font-medium tracking-wide">
                                            {balance.code}
                                        </span>
                                    </div>
                                    <RollingAmount
                                        value={balance.amount}
                                        decimals={balance.decimals}
                                        locale={currentLocale()}
                                        className="font-heading text-xl font-semibold whitespace-nowrap"
                                    />
                                </div>
                            ))}
                        </div>
                    </div>
                </div>
            </m.div>
        </section>
    );
};
