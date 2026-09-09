import { cn } from "@/lib/utils";

/**
 * The billing surfaces' one piece of free colour: the same three
 * hues as the pricing ribbon on the homepage, blurred into a wash
 * that sits behind the title and fades out before the numbers so
 * they stay on plain ground.
 */
export const Splash = ({ className }: { className?: string }) => {
    return (
        <div
            aria-hidden="true"
            className={cn(
                "pointer-events-none absolute inset-x-0 top-0 h-64 overflow-hidden [mask-image:linear-gradient(to_bottom,black_30%,transparent)]",
                className,
            )}
        >
            <div className="absolute -top-24 -left-16 size-72 rounded-full bg-[oklch(0.72_0.19_25)] opacity-45 blur-[80px] dark:opacity-30" />
            <div className="absolute -top-28 left-1/3 size-80 rounded-full bg-[oklch(0.74_0.16_265)] opacity-45 blur-[90px] dark:opacity-30" />
            <div className="absolute -top-20 -right-16 size-72 rounded-full bg-[oklch(0.78_0.15_190)] opacity-45 blur-[80px] dark:opacity-30" />
        </div>
    );
};
