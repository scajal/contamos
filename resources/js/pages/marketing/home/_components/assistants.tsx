import { useLaravelReactI18n } from "laravel-react-i18n";
import {
    ArrowRightIcon,
    CalendarCheckIcon,
    CheckIcon,
    FileTextIcon,
    HistoryIcon,
    MessageCircleQuestionIcon,
    PenLineIcon,
    PlugIcon,
    SparklesIcon,
    UnplugIcon,
    UserCheckIcon,
} from "lucide-react";
import type { LucideIcon } from "lucide-react";
import { AnimatePresence, m, useReducedMotion } from "motion/react";
import { useState } from "react";
import type { ReactNode } from "react";

import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { cn } from "@/lib/utils";
import { login } from "@/routes";

interface AssistantsProps {
    dashboardUrl: string | null;
}

/**
 * One turn of a mock conversation. The visitor's turns can carry a
 * file they handed over; the assistant's say what it did in the
 * ledger before it answered.
 */
interface Turn {
    from: "you" | "assistant";
    text: string;
    action?: string;
    file?: string;
}

/**
 * One thing the connection is for, with the conversation that shows it.
 */
interface Scenario {
    key: string;
    icon: LucideIcon;
    title: string;
    body: string;
    turns: Array<Turn>;
}

/**
 * Why handing the ledger to an assistant is safe, each point something
 * the connection really does.
 */
const TRUST: Array<{ icon: LucideIcon; title: string; body: string }> = [
    {
        icon: PlugIcon,
        title: "Connected in a minute.",
        body: "Paste one address into Claude or ChatGPT and sign in. No API keys, nothing to install.",
    },
    {
        icon: UserCheckIcon,
        title: "It acts as you.",
        body: "It sees the teams you belong to and can do what your role allows there, nothing more.",
    },
    {
        icon: HistoryIcon,
        title: "Every change on the record.",
        body: "What it writes shows up in the activity log, marked as the assistant's work.",
    },
    {
        icon: UnplugIcon,
        title: "Disconnect it any time.",
        body: "Cut its access from your account settings and it stops at once.",
    },
];

/**
 * The flagship act, right after the opening: the same ledger, reached
 * from the AI assistant the visitor already talks to. Four scenarios
 * show what the connection is for, each as a mock conversation built
 * from plain markup rather than a screenshot of any one assistant, so
 * it reads the same in both themes and both languages. The first one
 * is rendered on the server as it is; switching animates the next in.
 */
export const Assistants = ({ dashboardUrl }: AssistantsProps) => {
    /**
     * Translate the provided string to the current locale.
     */
    const { currentLocale, t } = useLaravelReactI18n();

    /**
     * Whether the visitor prefers reduced motion.
     */
    const reducedMotion = useReducedMotion();

    /**
     * The scenario on show.
     */
    const [selected, setSelected] = useState(0);

    /**
     * Whether the visitor switched scenarios yet. The first one is
     * painted as it is; only a switch animates its turns in.
     */
    const [switched, setSwitched] = useState(false);

    /**
     * Write an amount the way the visitor's locale writes money.
     */
    const money = (amount: number): string =>
        new Intl.NumberFormat(currentLocale(), { currency: "EUR", style: "currency" }).format(amount);

    const scenarios: Array<Scenario> = [
        {
            key: "ask",
            icon: MessageCircleQuestionIcon,
            title: t("Ask anything"),
            body: t("Get the figure, not a spreadsheet."),
            turns: [
                { from: "you", text: t("How much did we spend on food in August?") },
                {
                    from: "assistant",
                    action: t("Read the ledger"),
                    text: t("You spent :amount on Food in August, across 18 movements.", { amount: money(412.3) }),
                },
                { from: "you", text: t("And compared to July?") },
                {
                    from: "assistant",
                    action: t("Read the ledger"),
                    text: t("That is :difference more than in July, which came to :amount.", {
                        amount: money(368.1),
                        difference: money(44.2),
                    }),
                },
            ],
        },
        {
            key: "import",
            icon: FileTextIcon,
            title: t("Import a statement"),
            body: t("Hand it a PDF and it writes the movements down."),
            turns: [
                {
                    from: "you",
                    file: t("statement-august.pdf"),
                    text: t("Import this statement into the Santander account."),
                },
                {
                    from: "assistant",
                    action: t("Checked for duplicates"),
                    text: t("I found 43 movements. 2 look like ones you already have. Shall I import the other 41?"),
                },
                { from: "you", text: t("Yes, go ahead.") },
                {
                    from: "assistant",
                    action: t("Wrote to the ledger"),
                    text: t("Done: 41 movements imported into Santander."),
                },
            ],
        },
        {
            key: "log",
            icon: PenLineIcon,
            title: t("Write it down by chat"),
            body: t("Say what happened, in your own words."),
            turns: [
                {
                    from: "you",
                    text: t("Paid :amount for dinner with Ana on the Visa, tag it Trip.", { amount: money(38.5) }),
                },
                {
                    from: "assistant",
                    action: t("Wrote to the ledger"),
                    text: t("Recorded an expense of :amount under Food on the Visa, tagged Trip.", {
                        amount: money(38.5),
                    }),
                },
            ],
        },
        {
            key: "review",
            icon: CalendarCheckIcon,
            title: t("Review the month"),
            body: t("Where the money went, and how the budgets are doing."),
            turns: [
                { from: "you", text: t("How is September going?") },
                {
                    from: "assistant",
                    action: t("Read the monthly report"),
                    text: t(
                        "So far :income came in and :expenses went out. Groceries is at 92% of its budget with a week to go; Transport is well under.",
                        { expenses: money(1864.75), income: money(3200) },
                    ),
                },
            ],
        },
    ];

    const scenario = scenarios[selected] ?? scenarios[0];

    return (
        <section id="assistants" className="bg-background relative scroll-mt-28 border-t">
            <div className="mx-auto max-w-6xl px-4 py-24 sm:px-6 sm:py-32">
                <m.div
                    initial={reducedMotion ? false : { opacity: 0, y: 24 }}
                    whileInView={{ opacity: 1, y: 0 }}
                    viewport={{ once: true, margin: "-80px" }}
                    transition={{ duration: 0.6, ease: "easeOut" }}
                    className="mx-auto flex max-w-3xl flex-col items-center gap-5 text-center"
                >
                    <Badge variant="outline">
                        <SparklesIcon aria-hidden="true" />
                        {t("AI assistants")}
                    </Badge>
                    <h2 className="font-heading text-[clamp(2.25rem,5vw,3.75rem)] leading-[1.02] font-semibold tracking-tight text-balance">
                        {t("Talk to your finances.")}
                    </h2>
                    <p className="text-muted-foreground max-w-[56ch] text-lg text-balance">
                        {t(
                            "Connect Claude, ChatGPT or another assistant with remote MCP connectors to your ledger. It answers from your real figures, and it keeps the ledger up to date for you.",
                        )}
                    </p>
                </m.div>

                <m.div
                    initial={reducedMotion ? false : { opacity: 0, y: 24 }}
                    whileInView={{ opacity: 1, y: 0 }}
                    viewport={{ once: true, margin: "-60px" }}
                    transition={{ duration: 0.6, ease: "easeOut", delay: 0.1 }}
                    className="mt-14 grid gap-6 sm:mt-16 lg:grid-cols-[minmax(0,5fr)_minmax(0,7fr)] lg:gap-10"
                >
                    {/** The scenarios, as a list of toggles that pick the conversation. */}
                    <div
                        className="grid grid-cols-2 gap-3 lg:grid-cols-1"
                        aria-label={t("What you can ask it")}
                        role="group"
                    >
                        {scenarios.map((item, index) => (
                            <button
                                aria-pressed={index === selected}
                                className={cn(
                                    "group flex flex-col items-start gap-2 rounded-2xl border p-4 text-left transition-colors sm:p-5 lg:flex-row lg:items-center lg:gap-4",
                                    index === selected
                                        ? "bg-card border-foreground/20 ring-foreground/10 shadow-sm ring-1"
                                        : "hover:bg-muted/60 border-transparent",
                                )}
                                key={item.key}
                                onClick={() => {
                                    setSelected(index);
                                    setSwitched(true);
                                }}
                                type="button"
                            >
                                <span
                                    aria-hidden="true"
                                    className={cn(
                                        "grid size-10 shrink-0 place-items-center rounded-xl transition-colors",
                                        index === selected
                                            ? "bg-primary text-primary-foreground"
                                            : "bg-muted text-foreground",
                                    )}
                                >
                                    <item.icon className="size-5" strokeWidth={1.75} />
                                </span>
                                <span className="flex flex-col gap-0.5">
                                    <span className="font-heading text-sm font-semibold tracking-tight sm:text-base">
                                        {item.title}
                                    </span>
                                    <span className="text-muted-foreground text-xs leading-relaxed sm:text-sm">
                                        {item.body}
                                    </span>
                                </span>
                            </button>
                        ))}
                    </div>

                    {/** The conversation of the scenario on show. */}
                    <div
                        aria-label={t("An example conversation with an AI assistant connected to Contamos.")}
                        aria-live="polite"
                        className="bg-card flex flex-col rounded-2xl border shadow-sm"
                        role="group"
                    >
                        <div className="flex items-center justify-between gap-3 border-b px-5 py-3.5">
                            <span className="font-heading text-sm font-semibold tracking-tight">
                                {t("Your AI assistant")}
                            </span>
                            <span className="text-muted-foreground flex items-center gap-1.5 text-xs">
                                <PlugIcon aria-hidden="true" className="size-3.5" />
                                {t("Connected to Contamos")}
                            </span>
                        </div>

                        {/** Tall enough for the longest conversation, so switching never resizes the card. */}
                        <div className="flex min-h-[380px] flex-col gap-4 p-5 sm:p-6">
                            <AnimatePresence initial={false} mode="wait">
                                <m.div
                                    animate="shown"
                                    className="flex flex-col gap-4"
                                    exit={reducedMotion ? undefined : { opacity: 0, transition: { duration: 0.15 } }}
                                    initial={switched && !reducedMotion ? "hidden" : false}
                                    key={scenario.key}
                                    variants={{ hidden: {}, shown: { transition: { staggerChildren: 0.35 } } }}
                                >
                                    {scenario.turns.map((turn, index) => (
                                        <m.div
                                            key={index}
                                            variants={{ hidden: { opacity: 0, y: 8 }, shown: { opacity: 1, y: 0 } }}
                                        >
                                            <Message action={turn.action} from={turn.from}>
                                                {turn.file ? (
                                                    <span className="flex flex-col gap-2">
                                                        <span className="bg-primary-foreground/10 flex w-fit items-center gap-1.5 rounded-lg px-2 py-1 text-xs">
                                                            <FileTextIcon aria-hidden="true" className="size-3.5" />
                                                            {turn.file}
                                                        </span>
                                                        {turn.text}
                                                    </span>
                                                ) : (
                                                    turn.text
                                                )}
                                            </Message>
                                        </m.div>
                                    ))}
                                </m.div>
                            </AnimatePresence>
                        </div>
                    </div>
                </m.div>

                {/** Why it is safe to hand the ledger over. */}
                <div className="mt-6 grid gap-4 sm:grid-cols-2 lg:mt-10 lg:grid-cols-4">
                    {TRUST.map((point, index) => (
                        <m.div
                            className="flex flex-col gap-3 rounded-2xl border p-5"
                            initial={reducedMotion ? false : { opacity: 0, y: 24 }}
                            key={point.title}
                            transition={{ duration: 0.55, ease: "easeOut", delay: index * 0.07 }}
                            viewport={{ once: true, margin: "-60px" }}
                            whileInView={{ opacity: 1, y: 0 }}
                        >
                            <point.icon
                                aria-hidden="true"
                                className="text-muted-foreground size-5"
                                strokeWidth={1.75}
                            />
                            <div className="flex flex-col gap-1">
                                <h3 className="font-heading text-sm font-semibold tracking-tight">{t(point.title)}</h3>
                                <p className="text-muted-foreground text-sm leading-relaxed">{t(point.body)}</p>
                            </div>
                        </m.div>
                    ))}
                </div>

                <div className="mt-12 flex flex-col items-center gap-3">
                    <div className="flex flex-wrap items-center justify-center gap-3">
                        <Button nativeButton={false} render={<a href={dashboardUrl ?? login.url()} />} size="lg">
                            {dashboardUrl ? t("Go to dashboard") : t("Start free")} <ArrowRightIcon />
                        </Button>
                        <Button nativeButton={false} render={<a href="#pricing" />} size="lg" variant="ghost">
                            {t("See pricing")}
                        </Button>
                    </div>
                    <p className="text-muted-foreground text-sm">
                        {t("Included in every plan, Free too. Each plan's limits apply.")}
                    </p>
                </div>
            </div>
        </section>
    );
};

interface MessageProps {
    children: ReactNode;
    from: "you" | "assistant";
    /** What the assistant did in the ledger before it answered. */
    action?: string;
}

/**
 * One turn of the mock conversation: the visitor's on the right in the
 * brand's own colour, the assistant's on the left, with a quiet note
 * of what it did in the ledger before it answered.
 */
const Message = ({ action, children, from }: MessageProps) => {
    return (
        <div className={cn("flex flex-col gap-1.5", from === "you" ? "items-end" : "items-start")}>
            {action && (
                <span className="text-muted-foreground flex items-center gap-1 text-xs">
                    <CheckIcon aria-hidden="true" className="text-success size-3.5" />
                    {action}
                </span>
            )}
            <p
                className={cn(
                    "max-w-[85%] rounded-2xl px-4 py-2.5 text-sm leading-relaxed",
                    from === "you" ? "bg-primary text-primary-foreground rounded-br-md" : "bg-muted rounded-bl-md",
                )}
            >
                {children}
            </p>
        </div>
    );
};
