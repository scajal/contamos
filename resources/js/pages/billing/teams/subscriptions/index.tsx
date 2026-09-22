import { Deferred, router, usePage, usePoll } from "@inertiajs/react";
import { ModalLink } from "@inertiaui/modal-react";
import { useLaravelReactI18n } from "laravel-react-i18n";
import {
    AlertTriangleIcon,
    CircleDashedIcon,
    MoreHorizontalIcon,
    PrinterIcon,
    ReceiptTextIcon,
    SparklesIcon,
} from "lucide-react";
import { useEffect, useRef } from "react";
import { Fragment } from "react/jsx-runtime";

import { PaginationControls } from "@/components/pagination-controls";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuGroup,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from "@/components/ui/dropdown-menu";
import { Empty, EmptyHeader, EmptyMedia, EmptyTitle } from "@/components/ui/empty";
import { Item, ItemActions, ItemContent, ItemDescription, ItemGroup, ItemMedia, ItemTitle } from "@/components/ui/item";
import { Skeleton } from "@/components/ui/skeleton";
import { Spinner } from "@/components/ui/spinner";
import { toast } from "@/components/ui/toast";
import { ApplicationLayout } from "@/layouts/application/layout";
import { isOverAddableLimit, transactionStatusLabel, transactionStatusVariant } from "@/lib/billing";
import { formatDate } from "@/lib/utils";
import subscriptions from "@/routes/billing/teams/subscriptions";
import { Transaction } from "@/types/app/modules/billing/models/transaction";
import { PlanSummary, Renewal } from "@/types/app/modules/billing/plan-summary";
import { Team } from "@/types/app/modules/core/identity/models/team";
import { LengthAwarePaginator } from "@/types/illuminate/pagination/length-aware-paginator";

type PageProps = {
    activating: boolean;
    plan: PlanSummary;
    renewal: Renewal | null;
    team: Team;
    transactions: LengthAwarePaginator<Transaction>;
};

const Index = () => {
    return (
        <div className="flex flex-col gap-8">
            {/** Header */}
            <Header />

            {/** Content */}
            <div className="flex flex-col gap-8">
                {/** Activation */}
                <Activation />
                {/** Current plan */}
                <CurrentPlan />
                {/** Transactions */}
                <Transactions />
            </div>
        </div>
    );
};

const Header = () => {
    /**
     * Translate the provided string
     * to the current locale.
     */
    const t = useLaravelReactI18n().t;

    return (
        <header className="flex flex-col gap-1">
            <h1 className="font-heading text-[1.75rem] leading-tight font-semibold tracking-tight">{t("Billing")}</h1>
            <p className="text-muted-foreground text-[15px] leading-relaxed">
                {t("Manage your team's billing and subscription.")}
            </p>
        </header>
    );
};

/**
 * Shown after coming back from checkout while the plan is still
 * Free. Polls the plan until Paddle's webhook flips it to Pro,
 * then says so once.
 */
const Activation = () => {
    /**
     * Translate the provided string
     * to the current locale.
     */
    const t = useLaravelReactI18n().t;

    /**
     * The page's props.
     */
    const { activating, plan, team } = usePage<PageProps>().props;

    /**
     * Whether we are still waiting for the webhook.
     */
    const waiting = activating && plan.name !== "pro";

    /**
     * Whether the page was ever waiting, so the welcome
     * only fires on the transition to Pro.
     */
    const wasWaiting = useRef(waiting);

    usePoll(3000, { only: ["plan", "renewal"] }, { autoStart: waiting });

    useEffect(() => {
        if (wasWaiting.current && !waiting && plan.name === "pro") {
            wasWaiting.current = false;
            toast.add({ description: t("You are on Pro now.") });
            router.replace({ url: subscriptions.index.url({ team: team.slug }) });
        }
    }, [waiting, plan.name, t, team.slug]);

    if (!waiting) {
        return null;
    }

    return (
        <div className="flex items-center gap-3 rounded-xl border px-4 py-3">
            <Spinner />
            <div className="flex flex-col">
                <p className="text-sm font-medium">{t("Activating your subscription…")}</p>
                <p className="text-muted-foreground text-[15px] leading-relaxed">
                    {t("This usually takes a few seconds.")}
                </p>
            </div>
        </div>
    );
};

const CurrentPlan = () => {
    /**
     * Translate the provided string
     * to the current locale.
     */
    const { currentLocale, t } = useLaravelReactI18n();

    /**
     * The page's props.
     */
    const { plan, renewal, team } = usePage<PageProps>().props;

    /**
     * Format a date for the current locale.
     */
    const date = (value: string) => formatDate(value, currentLocale());

    /**
     * What the team holds above the Free plan limits, left
     * over from an earlier Pro period. History is left out: its
     * older months are hidden, not blocked, and the transactions
     * list says so itself.
     */
    const overLimits = plan.name === "free" ? plan.limits.filter(isOverAddableLimit) : [];

    /**
     * Whether the team is in a state it may not get out of on its
     * own: a payment that failed, a subscription that is ending or
     * paused, or limits it is already over.
     */
    const stuck = ["grace", "past_due", "paused"].includes(plan.status) || overLimits.length > 0;

    /**
     * The line under the plan name, by lifecycle state.
     */
    const detail = (() => {
        switch (plan.status) {
            case "free":
                return t("You are on the Free plan.");
            case "granted":
                return plan.granted_until
                    ? t("Your team was granted this plan until :date.", { date: date(plan.granted_until) })
                    : t("Your team was granted this plan.");
            case "grace":
                return plan.ends_at ? t("Your subscription ends on :date.", { date: date(plan.ends_at) }) : null;
            case "past_due":
                return t("Your last payment failed. Update your payment method to keep Pro.");
            case "paused":
                return t("Your subscription is paused.");
            case "trialing":
                return plan.trial_ends_at ? t("Your trial ends on :date.", { date: date(plan.trial_ends_at) }) : null;
            default:
                return renewal
                    ? t("Next payment of :amount on :date.", { amount: renewal.amount, date: date(renewal.date) })
                    : null;
        }
    })();

    return (
        <Fragment>
            <header className="flex flex-col gap-1">
                <h2 className="font-heading font-medium">{t("Current plan")}</h2>
                <p className="text-muted-foreground text-[15px] leading-relaxed">
                    {t("What your team is on and when it renews.")}
                </p>
            </header>
            <div className="flex flex-col gap-4 rounded-xl border p-4">
                <div className="flex flex-wrap items-center justify-between gap-4">
                    <div className="flex flex-col gap-1">
                        <div className="flex items-center gap-2">
                            <span className="font-heading text-lg font-medium">
                                {plan.name === "pro" ? t("Pro") : t("Free")}
                            </span>
                            {plan.cycle && (
                                <Badge variant="secondary">
                                    {plan.cycle === "yearly" ? t("Billed yearly") : t("Billed monthly")}
                                </Badge>
                            )}
                            {plan.status === "granted" && <Badge variant="secondary">{t("Granted")}</Badge>}
                            {plan.status === "grace" && <Badge variant="outline">{t("Cancelled")}</Badge>}
                            {plan.status === "past_due" && <Badge variant="destructive">{t("Payment failed")}</Badge>}
                            {plan.status === "paused" && <Badge variant="outline">{t("Paused")}</Badge>}
                        </div>
                        {plan.status === "active" ? (
                            <Deferred data="renewal" fallback={<Skeleton className="h-4 w-56" />}>
                                {detail ? (
                                    <p className="text-muted-foreground text-[15px] leading-relaxed">{detail}</p>
                                ) : null}
                            </Deferred>
                        ) : detail ? (
                            <p className="text-muted-foreground text-[15px] leading-relaxed">{detail}</p>
                        ) : null}
                    </div>
                    <PlanActions />
                </div>
                {overLimits.length > 0 && (
                    <div className="flex gap-3 rounded-xl border border-amber-500/40 bg-amber-500/5 p-3">
                        <AlertTriangleIcon className="mt-0.5 size-4 shrink-0 text-amber-600 dark:text-amber-400" />
                        <div className="flex flex-col gap-2 text-sm">
                            <p>
                                {t(
                                    "Your team is over the Free plan limits. Nothing was removed, but you cannot add more until you upgrade.",
                                )}
                            </p>
                            <ul className="text-muted-foreground flex flex-col gap-0.5">
                                {overLimits.map((row) => (
                                    <li key={row.feature}>
                                        {row.label}: {row.usage} / {row.free}
                                    </li>
                                ))}
                            </ul>
                        </div>
                    </div>
                )}
                {/* A payment that failed, a subscription that stopped or a
                 * portal that will not help is where a person needs one, so
                 * the address sits inside the card that states the trouble. */}
                {stuck && (
                    <p className="text-muted-foreground text-sm leading-relaxed">
                        {t("Something we can help with?")}{" "}
                        <a className="text-foreground underline underline-offset-4" href={"mailto:hello@contamos.xyz"}>
                            {t("Write to :email", { email: "hello@contamos.xyz" })}
                        </a>
                    </p>
                )}
            </div>
            {/* The plan someone is paying for keeps changing, so the page says the app is still being built rather than letting the table read as final. */}
            <p className="text-muted-foreground text-[15px] leading-relaxed">
                {t(
                    "Contamos is in active development. New features land regularly; what your plan includes today is what you see above.",
                )}
            </p>
        </Fragment>
    );

    /**
     * The action that fits the lifecycle state. Upgrading opens
     * the modal; everything else goes through Paddle's portal. A
     * plan granted by hand has nothing to manage, and only offers
     * the upgrade while it is Free.
     */
    function PlanActions() {
        const portal = subscriptions.redirectToCustomerPortal.url({ team: team.slug });

        if (plan.status === "granted" && plan.name === "pro") {
            return null;
        }

        switch (plan.status) {
            case "granted":
            case "free":
                return (
                    <Button
                        nativeButton={false}
                        render={
                            <ModalLink href={subscriptions.upgrade.url({ team: team.slug })}>
                                <SparklesIcon data-icon="inline-start" />
                                {t("Upgrade to Pro")}
                            </ModalLink>
                        }
                    />
                );
            case "grace":
            case "paused":
                return (
                    <Button nativeButton={false} render={<a href={portal} />}>
                        {t("Resume subscription")}
                    </Button>
                );
            case "past_due":
                return (
                    <Button nativeButton={false} render={<a href={portal} />}>
                        {t("Update payment method")}
                    </Button>
                );
            default:
                return (
                    <Button nativeButton={false} render={<a href={portal} />} variant="outline">
                        {t("Manage billing")}
                    </Button>
                );
        }
    }
};

const Transactions = () => {
    /**
     * Translate the provided string
     * to the current locale.
     */
    const { currentLocale, t } = useLaravelReactI18n();

    /**
     * The page's props.
     */
    const { team, transactions } = usePage<PageProps>().props;

    return (
        <Fragment>
            <header className="flex flex-col gap-1">
                <h2 className="font-heading font-medium">{t("Transactions")}</h2>
                <p className="text-muted-foreground text-[15px] leading-relaxed">
                    {t("View your team's billing transactions.")}
                </p>
            </header>
            <Deferred data="transactions" fallback={<SkeletonRows />}>
                {!transactions?.data?.length ? (
                    <div className="rounded-xl border">
                        <Empty>
                            <EmptyHeader>
                                <EmptyMedia variant="icon">
                                    <CircleDashedIcon />
                                </EmptyMedia>
                                <EmptyTitle>{t("No :name yet", { name: t("transactions") })}</EmptyTitle>
                            </EmptyHeader>
                        </Empty>
                    </div>
                ) : (
                    <div className="flex flex-col gap-4">
                        <div className="overflow-hidden rounded-xl border">
                            <ItemGroup className="gap-0">
                                {(transactions?.data ?? []).map((transaction) => (
                                    <Item
                                        className="rounded-none border-0 border-b last:border-b-0"
                                        key={transaction.id}
                                        size="sm"
                                        variant="outline"
                                    >
                                        <ItemMedia variant="icon">
                                            <ReceiptTextIcon />
                                        </ItemMedia>
                                        <ItemContent>
                                            <ItemTitle>{formatDate(transaction.billed_at, currentLocale())}</ItemTitle>
                                            <ItemDescription>{`${transaction.currency} ${transaction.total}`}</ItemDescription>
                                        </ItemContent>
                                        <ItemActions className="flex-wrap justify-end">
                                            <Badge variant={transactionStatusVariant(transaction.status)}>
                                                {t(transactionStatusLabel(transaction.status))}
                                            </Badge>
                                            <DropdownMenu>
                                                <DropdownMenuTrigger render={<Button size="icon" variant="ghost" />}>
                                                    <MoreHorizontalIcon />
                                                    <span className="sr-only">{t("Actions")}</span>
                                                </DropdownMenuTrigger>
                                                <DropdownMenuContent align="end">
                                                    <DropdownMenuGroup>
                                                        <DropdownMenuItem
                                                            disabled={!transaction.invoice_number}
                                                            render={
                                                                <a
                                                                    href={subscriptions.invoice.url({
                                                                        team: team.slug,
                                                                        billingTransaction: transaction.id,
                                                                    })}
                                                                    rel="noreferrer"
                                                                    target="_blank"
                                                                />
                                                            }
                                                        >
                                                            <PrinterIcon />
                                                            <span>{t("Download invoice")}</span>
                                                        </DropdownMenuItem>
                                                    </DropdownMenuGroup>
                                                </DropdownMenuContent>
                                            </DropdownMenu>
                                        </ItemActions>
                                    </Item>
                                ))}
                            </ItemGroup>
                        </div>
                        <PaginationControls
                            nextUrl={transactions?.next_page_url}
                            previousUrl={transactions?.prev_page_url}
                        />
                    </div>
                )}
            </Deferred>
        </Fragment>
    );
};

const SkeletonRows = () => {
    return (
        <div className="overflow-hidden rounded-xl border">
            {[0, 1, 2].map((row) => (
                <div className="flex items-center gap-3 border-b px-3 py-2.5 last:border-b-0" key={row}>
                    <Skeleton className="size-8 rounded-xl" />
                    <div className="flex flex-1 flex-col gap-2">
                        <Skeleton className="h-4 w-40" />
                        <Skeleton className="h-3 w-12" />
                    </div>
                    <Skeleton className="h-5 w-16" />
                </div>
            ))}
        </div>
    );
};

Index.layout = (page: React.ReactNode) => <ApplicationLayout>{page}</ApplicationLayout>;

export default Index;
