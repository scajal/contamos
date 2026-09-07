import { useLaravelReactI18n } from "laravel-react-i18n";

import { Badge } from "@/components/ui/badge";
import { TableBody, TableCell, Table as TableComponent, TableRow } from "@/components/ui/table";
import { cn } from "@/lib/utils";

import { Reveal } from "./reveal";
import { TiltCard } from "./tilt-card";

const BALANCES = [
    { code: "EUR", amount: "€ 1,250.40" },
    { code: "USD", amount: "$ 4,800.00" },
    { code: "BTC", amount: "₿ 0.00310000" },
] as const;

const LEDGER_ROWS = [
    { label: "Apartment rent", amount: "- € 950.00", kind: "expense" },
    { label: "Monthly salary", amount: "+ $ 3,200.00", kind: "income" },
    { label: "Supermarket", amount: "- $ 85.40", kind: "expense" },
] as const;

const TAGS = ["Groceries", "Rent", "Savings", "Salary", "Utilities", "Transport"] as const;

const PILLARS = [
    {
        title: "Balances per currency, not a blended guess.",
        body: "Every account keeps its own currency — see your euros, dollars and bitcoin exactly as they are, not blended into one misleading total.",
        tab: "bg-amber-400",
        fragment: (
            <div className="flex min-w-0 divide-x overflow-x-auto rounded-sm border">
                {BALANCES.map((balance) => (
                    <div key={balance.code} className="flex min-w-28 flex-1 flex-col gap-1 p-4">
                        <span className="text-muted-foreground text-sm">{balance.code}</span>
                        <span className="text-lg font-medium tabular-nums">{balance.amount}</span>
                    </div>
                ))}
            </div>
        ),
    },
    {
        title: "One ledger for the whole household.",
        body: "Every transaction from every account lands in the same timeline, so nothing gets lost between apps or spreadsheets.",
        tab: "bg-teal-400",
        fragment: (
            <div className="min-w-0 overflow-x-auto rounded-sm border">
                <TableComponent>
                    <TableBody>
                        {LEDGER_ROWS.map((row) => (
                            <TableRow key={row.label}>
                                <TableCell>{row.label}</TableCell>
                                <TableCell className="text-right">
                                    <span
                                        className={cn(
                                            "font-medium tabular-nums",
                                            row.kind === "expense" ? "text-destructive" : "text-success",
                                        )}
                                    >
                                        {row.amount}
                                    </span>
                                </TableCell>
                            </TableRow>
                        ))}
                    </TableBody>
                </TableComponent>
            </div>
        ),
    },
    {
        title: "Tags that answer your questions.",
        body: "Tag every expense once and filter your history in seconds — no more scrolling to remember where the money went.",
        tab: "bg-rose-400",
        fragment: (
            <div className="flex flex-wrap gap-2 p-1">
                {TAGS.map((tag, index) => (
                    <Badge key={tag} variant={index === 0 ? "default" : "secondary"}>
                        {tag}
                    </Badge>
                ))}
            </div>
        ),
    },
] as const;

/**
 * The three pillars, as an editorial zigzag list: text and fragment
 * swap sides on alternating rows, each fragment reusing the app's own
 * balance-strip and table components (not a bespoke look-alike), and
 * each card tilts gently toward the cursor for a light 3D presence.
 */
export const Pillars = () => {
    /**
     * Translate the provided string to the current locale.
     */
    const t = useLaravelReactI18n().t;

    return (
        <section className="mx-auto max-w-6xl px-4 py-20 sm:px-6 sm:py-28">
            <h2 className="font-heading max-w-[30ch] text-3xl font-semibold tracking-tight md:text-5xl">
                {t("Built for the way families really handle money")}
            </h2>

            <div className="mt-12 flex flex-col divide-y">
                {PILLARS.map((pillar, index) => (
                    <Reveal key={pillar.title} className="grid gap-6 py-10 md:grid-cols-2 md:items-center">
                        <div className={cn("flex flex-col gap-3", index % 2 === 1 && "md:order-2")}>
                            <span aria-hidden="true" className={cn("h-1.5 w-8 rounded-full", pillar.tab)} />
                            <p className="max-w-[36ch] text-xl font-medium text-balance md:text-2xl">
                                {t(pillar.title)}
                            </p>
                            <p className="text-muted-foreground max-w-[42ch] text-sm">{t(pillar.body)}</p>
                        </div>
                        <TiltCard className={index % 2 === 1 ? "md:order-1" : undefined}>{pillar.fragment}</TiltCard>
                    </Reveal>
                ))}
            </div>
        </section>
    );
};
