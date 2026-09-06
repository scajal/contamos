import { Deferred, usePage } from "@inertiajs/react";
import { useLaravelReactI18n } from "laravel-react-i18n";
import { CircleDashedIcon } from "lucide-react";

import { Empty, EmptyDescription, EmptyHeader, EmptyMedia, EmptyTitle } from "@/components/ui/empty";
import { Skeleton } from "@/components/ui/skeleton";
import { ApplicationLayout } from "@/layouts/application/layout";
import { cn, formatAmount } from "@/lib/utils";
import { AccountBalance } from "@/types/app/modules/core/finance/models/team/account-balance";
import { Team } from "@/types/app/modules/core/identity/models/team";

type PageProps = {
    balances: Array<AccountBalance>;
    team: Team;
};

const Dashboard = () => {
    return (
        <div className="flex flex-col gap-8">
            {/** Header */}
            <Header />

            {/** Balances */}
            <Deferred data="balances" fallback={<SkeletonCards />}>
                <Balances />
            </Deferred>
        </div>
    );
};

const Header = () => {
    /**
     * The page's props.
     */
    const { team } = usePage<PageProps>().props;

    return <h1 className="text-2xl font-semibold">{team.name}</h1>;
};

const Balances = () => {
    /**
     * Translate the provided string
     * to the current locale.
     */
    const t = useLaravelReactI18n().t;

    /**
     * Get the balances from the page props.
     */
    const balances = usePage<PageProps>().props.balances;

    // Render the empty state when there are no balances.
    if (balances.length < 1) {
        return (
            <div className="rounded-sm border">
                <Empty>
                    <EmptyHeader>
                        <EmptyMedia variant="icon">
                            <CircleDashedIcon />
                        </EmptyMedia>
                        <EmptyTitle>{t("No :name yet", { name: t("balances") })}</EmptyTitle>
                        <EmptyDescription>
                            {t("Add a new :name to get started.", { name: t("transaction") })}
                        </EmptyDescription>
                    </EmptyHeader>
                </Empty>
            </div>
        );
    }

    return (
        <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            {balances.map((balance) => (
                <BalanceCard key={balance.id} balance={balance} />
            ))}
        </div>
    );
};

const BalanceCard = ({ balance }: { balance: AccountBalance }) => {
    /**
     * Get the current locale.
     */
    const currentLocale = useLaravelReactI18n().currentLocale;

    /**
     * The balance as a string, since some drivers
     * return aggregated decimals as numbers.
     */
    const amount = String(balance.balance);

    /**
     * Whether the balance is negative.
     */
    const isNegative = amount.startsWith("-");

    return (
        <div className="flex flex-col gap-1 rounded-sm border p-4">
            <span className="text-muted-foreground text-sm">{balance.code}</span>
            <span className={cn("text-2xl font-semibold tabular-nums", isNegative && "text-destructive")}>
                {`${isNegative ? "- " : ""}${formatAmount(amount, currentLocale(), balance)}`}
            </span>
        </div>
    );
};

const SkeletonCards = () => {
    return (
        <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            {Array.from({ length: 4 }).map((_, index) => (
                <div key={index} className="flex flex-col gap-2 rounded-sm border p-4">
                    <Skeleton className="h-4 w-12" />
                    <Skeleton className="h-8 w-32" />
                </div>
            ))}
        </div>
    );
};

Dashboard.layout = (page: React.ReactNode) => <ApplicationLayout>{page}</ApplicationLayout>;

export default Dashboard;
