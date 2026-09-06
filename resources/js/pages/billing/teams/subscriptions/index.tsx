import { Deferred, Form, Link, usePage, usePoll } from "@inertiajs/react";
import { useLaravelReactI18n } from "laravel-react-i18n";
import { CheckIcon, CreditCardIcon, DownloadIcon, ReceiptIcon } from "lucide-react";
import { useEffect, useState } from "react";

import { ConfirmAction } from "@/components/confirm-action";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Empty, EmptyDescription, EmptyHeader, EmptyMedia, EmptyTitle } from "@/components/ui/empty";
import { Item, ItemActions, ItemContent, ItemGroup, ItemTitle } from "@/components/ui/item";
import { Skeleton } from "@/components/ui/skeleton";
import { Spinner } from "@/components/ui/spinner";
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from "@/components/ui/table";
import { ToggleGroup, ToggleGroupItem } from "@/components/ui/toggle-group";
import { ApplicationLayout } from "@/layouts/application/layout";
import routes from "@/routes/billing/teams/subscriptions";
import transactionRoutes from "@/routes/billing/teams/transactions";
import { Transaction } from "@/types/app/modules/billing/models/transaction";
import { LengthAwarePaginator } from "@/types/illuminate/pagination/length-aware-paginator";

type BillingFeature = "accounts" | "members" | "transactions";

type Checkout = Record<string, unknown>;

type SubscriptionProp = {
    id: number;
    status: string;
    interval: "month" | "year" | null;
    on_grace_period: boolean;
    ends_at: string | null;
    past_due: boolean;
    next_payment: { amount: string; date: string } | null;
};

type PageProps = {
    plan: "free" | "pro";
    limits: Record<BillingFeature, number | null>;
    usage: Record<BillingFeature, number>;
    subscription: SubscriptionProp | null;
    checkouts: { month: Checkout; year: Checkout } | null;
    transactions: LengthAwarePaginator<Transaction>;
};

const FEATURES: Array<BillingFeature> = ["accounts", "members", "transactions"];

const Index = () => {
    return (
        <div className="flex flex-col gap-8">
            <Header />
            <ConfirmingSubscription />
            <PlanCard />
            <BillingHistory />
        </div>
    );
};

const Header = () => {
    const t = useLaravelReactI18n().t;

    return (
        <header className="flex flex-col gap-1">
            <h1 className="text-2xl font-medium">{t("Billing")}</h1>
            <p className="text-muted-foreground text-sm">{t("Manage your team's plan and payment details.")}</p>
        </header>
    );
};

/**
 * Shows a "confirming" state while the Paddle webhook that flips the
 * team to Pro has not landed yet, polling until the subscription
 * prop appears.
 */
const ConfirmingSubscription = () => {
    const t = useLaravelReactI18n().t;
    const { subscription } = usePage<PageProps>().props;
    const params = new URLSearchParams(window.location.search);
    const isConfirming = params.get("checkout") === "success" && !subscription;

    const { stop } = usePoll(3000, { only: ["subscription", "plan", "checkouts"] });

    useEffect(() => {
        if (!isConfirming) {
            stop();
        }
    }, [isConfirming, stop]);

    if (!isConfirming) {
        return null;
    }

    return (
        <div className="flex items-center gap-2 rounded-lg border border-dashed p-4 text-sm">
            <Spinner />
            <span>{t("Confirming your subscription…")}</span>
        </div>
    );
};

const PlanCard = () => {
    const t = useLaravelReactI18n().t;
    const { plan, user } = usePage<PageProps>().props;

    return (
        <div className="flex flex-col gap-4 rounded-lg border p-4">
            <div className="flex items-center justify-between gap-2">
                <div className="flex flex-col gap-1">
                    <span className="text-sm font-medium">{plan === "pro" ? t("Pro plan") : t("Free plan")}</span>
                    <span className="text-muted-foreground text-sm">
                        {plan === "pro"
                            ? t("Unlimited accounts, members, and transactions.")
                            : t("Upgrade to Pro for unlimited accounts, members, and transactions.")}
                    </span>
                </div>
                <StatusBadge />
            </div>

            <UsageRows />

            {plan === "pro" ? <ProControls /> : <FreeCheckout />}

            <p className="text-muted-foreground text-xs">
                {t("Billing is managed under :name's email.", { name: user?.name ?? "" })}
            </p>
        </div>
    );
};

const StatusBadge = () => {
    const t = useLaravelReactI18n().t;
    const { subscription } = usePage<PageProps>().props;

    if (!subscription) {
        return null;
    }

    if (subscription.past_due) {
        return <Badge variant="destructive">{t("Past due")}</Badge>;
    }

    if (subscription.on_grace_period) {
        return <Badge variant="secondary">{t("Cancels on :date", { date: subscription.ends_at ?? "" })}</Badge>;
    }

    return <Badge variant="ghost">{t("Active")}</Badge>;
};

const UsageRows = () => {
    const t = useLaravelReactI18n().t;
    const { limits, usage } = usePage<PageProps>().props;

    const labels: Record<BillingFeature, string> = {
        accounts: t("Accounts"),
        members: t("Members"),
        transactions: t("Transactions this month"),
    };

    return (
        <ItemGroup className="gap-0">
            {FEATURES.map((feature) => {
                const limit = limits[feature];

                return (
                    <Item
                        className="rounded-none border-0 border-b px-0 last:border-b-0"
                        key={feature}
                        size="sm"
                        variant="outline"
                    >
                        <ItemContent>
                            <ItemTitle className="font-normal">{labels[feature]}</ItemTitle>
                        </ItemContent>
                        <ItemActions>
                            <span className="text-muted-foreground text-sm">
                                {limit === null
                                    ? t(":count / Unlimited", { count: usage[feature] })
                                    : `${usage[feature]} / ${limit}`}
                            </span>
                        </ItemActions>
                    </Item>
                );
            })}
        </ItemGroup>
    );
};

const FreeCheckout = () => {
    const t = useLaravelReactI18n().t;
    const { checkouts } = usePage<PageProps>().props;
    const [interval, setInterval] = useState<"month" | "year">("month");

    if (!checkouts) {
        return null;
    }

    const checkout = checkouts[interval];

    return (
        <div className="flex flex-col gap-3 border-t pt-4 sm:flex-row sm:items-center sm:justify-between">
            <ToggleGroup
                onValueChange={(value) => {
                    const next = value[0] as "month" | "year" | undefined;

                    if (next) {
                        setInterval(next);
                    }
                }}
                value={[interval]}
            >
                <ToggleGroupItem value="month">{t("Monthly")}</ToggleGroupItem>
                <ToggleGroupItem value="year">{t("Yearly")}</ToggleGroupItem>
            </ToggleGroup>
            <Button
                onClick={() => {
                    window.Paddle?.Checkout.open({
                        ...checkout,
                        settings: {
                            ...(checkout.settings as Record<string, unknown>),
                            displayMode: "overlay",
                        },
                    });
                }}
            >
                {t("Subscribe to Pro")}
            </Button>
        </div>
    );
};

const ProControls = () => {
    const t = useLaravelReactI18n().t;
    const { subscription, user } = usePage<PageProps>().props;
    const team = { team: user?.current_team?.slug ?? "" };

    if (!subscription) {
        return null;
    }

    return (
        <div className="flex flex-col gap-3 border-t pt-4">
            {subscription.next_payment && !subscription.on_grace_period ? (
                <p className="text-muted-foreground text-sm">
                    {t("Next payment: :amount on :date", {
                        amount: subscription.next_payment.amount,
                        date: subscription.next_payment.date,
                    })}
                </p>
            ) : null}

            <div className="flex flex-wrap items-center gap-2">
                {subscription.interval ? (
                    <Form
                        {...routes.update.form(team)}
                        options={{ only: ["subscription", "errors"] }}
                        disableWhileProcessing
                    >
                        {({ processing }) => (
                            <>
                                <input
                                    name="interval"
                                    type="hidden"
                                    value={subscription.interval === "month" ? "year" : "month"}
                                />
                                <Button disabled={processing} type="submit" variant="outline">
                                    {processing && <Spinner data-icon="inline-start" />}
                                    {subscription.interval === "month" ? t("Switch to yearly") : t("Switch to monthly")}
                                </Button>
                            </>
                        )}
                    </Form>
                ) : null}

                <Button nativeButton={false} render={<a href={routes.paymentMethod.url(team)} />} variant="outline">
                    <CreditCardIcon data-icon="inline-start" />
                    {t("Update payment method")}
                </Button>

                {subscription.on_grace_period ? (
                    <Form {...routes.resume.form(team)} options={{ only: ["subscription"] }} disableWhileProcessing>
                        {({ processing }) => (
                            <Button disabled={processing} type="submit" variant="outline">
                                {processing && <Spinner data-icon="inline-start" />}
                                <CheckIcon data-icon="inline-start" />
                                {t("Resume subscription")}
                            </Button>
                        )}
                    </Form>
                ) : (
                    <ConfirmAction
                        action={
                            <Link
                                href={routes.destroy.url(team)}
                                method="delete"
                                only={["subscription"]}
                                preserveScroll
                            />
                        }
                        description={t("Your plan stays Pro until the end of the current billing period.")}
                        title={t("Cancel subscription?")}
                    >
                        <Button variant="outline">{t("Cancel subscription")}</Button>
                    </ConfirmAction>
                )}
            </div>
        </div>
    );
};

const BillingHistory = () => {
    const t = useLaravelReactI18n().t;

    return (
        <div className="flex flex-col gap-4">
            <h2 className="text-lg font-medium">{t("Billing history")}</h2>
            <Deferred data="transactions" fallback={<TransactionsSkeleton />}>
                <TransactionsTable />
            </Deferred>
        </div>
    );
};

const TransactionsTable = () => {
    const t = useLaravelReactI18n().t;
    const { transactions, user } = usePage<PageProps>().props;

    if (!transactions?.data?.length) {
        return (
            <div className="rounded-sm border">
                <Empty>
                    <EmptyHeader>
                        <EmptyMedia variant="icon">
                            <ReceiptIcon />
                        </EmptyMedia>
                        <EmptyTitle>{t("No billing history yet")}</EmptyTitle>
                        <EmptyDescription>{t("Invoices appear here once you subscribe to Pro.")}</EmptyDescription>
                    </EmptyHeader>
                </Empty>
            </div>
        );
    }

    return (
        <div className="overflow-x-auto rounded-sm border">
            <Table>
                <TableHeader>
                    <TableRow>
                        <TableHead>{t("Date")}</TableHead>
                        <TableHead>{t("Total")}</TableHead>
                        <TableHead>{t("Tax")}</TableHead>
                        <TableHead>{t("Status")}</TableHead>
                        <TableHead className="text-right">{t("Invoice")}</TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    {transactions.data.map((transaction) => (
                        <TableRow key={transaction.id}>
                            <TableCell>{transaction.billed_at}</TableCell>
                            <TableCell>{transaction.total}</TableCell>
                            <TableCell>{transaction.tax}</TableCell>
                            <TableCell>
                                <Badge variant={transaction.status === "completed" ? "ghost" : "secondary"}>
                                    {transaction.status}
                                </Badge>
                            </TableCell>
                            <TableCell className="text-right">
                                <Button
                                    nativeButton={false}
                                    render={
                                        <a
                                            href={transactionRoutes.invoice.url({
                                                team: user?.current_team?.slug ?? "",
                                                billingTransaction: transaction.id,
                                            })}
                                        />
                                    }
                                    size="icon"
                                    variant="ghost"
                                >
                                    <DownloadIcon />
                                    <span className="sr-only">{t("Download invoice")}</span>
                                </Button>
                            </TableCell>
                        </TableRow>
                    ))}
                </TableBody>
            </Table>
        </div>
    );
};

const TransactionsSkeleton = () => {
    return (
        <div className="overflow-hidden rounded-sm border">
            {[0, 1, 2].map((row) => (
                <div className="flex items-center gap-3 border-b px-3 py-2.5 last:border-b-0" key={row}>
                    <Skeleton className="h-4 w-24" />
                    <Skeleton className="h-4 w-16" />
                    <Skeleton className="h-4 flex-1" />
                    <Skeleton className="h-5 w-16" />
                </div>
            ))}
        </div>
    );
};

Index.layout = (page: React.ReactNode) => <ApplicationLayout>{page}</ApplicationLayout>;

export default Index;
