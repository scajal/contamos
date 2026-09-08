import { cn } from "@/lib/utils";

type Props = {
    className?: string;
};

/**
 * The Contamos isotype: a ledger of three entries with the balance
 * marked in lime. The plate takes the foreground colour and the lines
 * take the background, so the mark inverts itself on dark surfaces
 * without a second asset.
 */
export function BrandMark({ className }: Props) {
    return (
        <svg
            viewBox="0 0 64 64"
            xmlns="http://www.w3.org/2000/svg"
            aria-hidden="true"
            className={cn("size-6 shrink-0", className)}
        >
            <rect width="64" height="64" rx="16" className="fill-foreground" />
            <path d="M17 20h30M17 32h22M17 44h14" strokeWidth="6" strokeLinecap="round" className="stroke-background" />
            <circle cx="45" cy="44" r="5" fill="#b2e016" />
        </svg>
    );
}
