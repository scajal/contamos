import { usePage } from "@inertiajs/react";
import { useLaravelReactI18n } from "laravel-react-i18n";
import { ArrowRightLeftIcon, BanknoteIcon, SparklesIcon, UsersIcon } from "lucide-react";
import { Fragment } from "react/jsx-runtime";

import { Button } from "@/components/ui/button";
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from "@/components/ui/dropdown-menu";
import { Item, ItemActions, ItemContent, ItemDescription, ItemGroup, ItemMedia, ItemTitle } from "@/components/ui/item";
import { ApplicationLayout } from "@/layouts/application/layout";

type Checkout = Record<string, unknown>;

type PageProps = {
    checkouts: {
        monthly: Checkout;
        yearly: Checkout;
    };
    limits: {
        accounts: number;
        members: number;
        transactionsPerMonth: number;
    };
    plan: "free" | "pro";
    usage: {
        accounts: number;
        members: number;
        transactionsPerMonth: number;
    };
};

const Show = () => {
    return (
        <div className="flex flex-col gap-8">
            {/** Header */}
            <Header />

            {/** Content */}
            <div className="flex flex-col gap-4">
                {/** Current plan */}
                <CurrentPlan />
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
            <h1 className="text-2xl font-medium">{t("Billing")}</h1>
            <p className="text-muted-foreground text-sm">{t("Manage your team's plan and payment details.")}</p>
        </header>
    );
};

const CurrentPlan = () => {
    /**
     * Translate the provided string
     * to the current locale.
     */
    const t = useLaravelReactI18n().t;

    /**
     * Get the current plan from the page props.
     */
    const { limits, plan, usage } = usePage<PageProps>().props;

    return (
        <Item variant="outline">
            <ItemContent>
                <ItemTitle>{t(":name plan", { name: plan === "pro" ? t("Pro") : t("Free") })}</ItemTitle>
                <ItemDescription>
                    {plan === "pro"
                        ? t("Unlimited accounts, members and transactions.")
                        : t("Upgrade to Pro to unlock unlimited accounts, members and transactions.")}
                </ItemDescription>
                {/** Usage */}
                <div className="mt-1.5 flex flex-col">
                    <div className="overflow-hidden rounded-sm border">
                        <ItemGroup className="!gap-0">
                            {/** Accounts */}
                            <Item
                                className="rounded-none border-0 border-b last:border-b-0"
                                size="sm"
                                variant="outline"
                            >
                                <ItemMedia variant="icon">
                                    <BanknoteIcon />
                                </ItemMedia>
                                <ItemContent>
                                    <ItemTitle>{t("Accounts")}</ItemTitle>
                                    <ItemDescription>
                                        {`${usage.accounts} / ${limits.accounts ?? t("Unlimited")}`}
                                    </ItemDescription>
                                </ItemContent>
                            </Item>
                            {/** Members */}
                            <Item
                                className="rounded-none border-0 border-b last:border-b-0"
                                size="sm"
                                variant="outline"
                            >
                                <ItemMedia variant="icon">
                                    <UsersIcon />
                                </ItemMedia>
                                <ItemContent>
                                    <ItemTitle>{t("Members")}</ItemTitle>
                                    <ItemDescription>
                                        {`${usage.members} / ${limits.members ?? t("Unlimited")}`}
                                    </ItemDescription>
                                </ItemContent>
                            </Item>
                            {/** Transactions per month */}
                            <Item
                                className="rounded-none border-0 border-b last:border-b-0"
                                size="sm"
                                variant="outline"
                            >
                                <ItemMedia variant="icon">
                                    <ArrowRightLeftIcon />
                                </ItemMedia>
                                <ItemContent>
                                    <ItemTitle>{t("Transactions per month")}</ItemTitle>
                                    <ItemDescription>
                                        {`${usage.transactionsPerMonth} / ${limits.transactionsPerMonth ?? t("Unlimited")}`}
                                    </ItemDescription>
                                </ItemContent>
                            </Item>
                        </ItemGroup>
                    </div>
                </div>
                {/** Actions */}
                <ItemActions className="mt-1.5">{plan === "free" ? <FreePlanActions /> : null}</ItemActions>
            </ItemContent>
        </Item>
    );
};

const FreePlanActions = () => {
    /**
     * Translate the provided string
     * to the current locale.
     */
    const t = useLaravelReactI18n().t;

    /**
     * Get the checkouts from the page props.
     */
    const { checkouts } = usePage<PageProps>().props;

    return (
        <Fragment>
            <DropdownMenu>
                <DropdownMenuTrigger render={<Button />}>
                    <SparklesIcon />
                    <span>{t("Upgrade to Pro")}</span>
                </DropdownMenuTrigger>
                <DropdownMenuContent>
                    {Object.entries(checkouts).map(([key, checkout]) => (
                        <DropdownMenuItem
                            key={key}
                            onClick={() =>
                                window.Paddle?.Checkout.open({
                                    ...checkout,
                                    settings: {
                                        ...(checkout.settings as Record<string, unknown>),
                                        displayMode: "overlay",
                                    },
                                })
                            }
                        >
                            <span>{key === "monthly" ? t("Monthly") : t("Yearly")}</span>
                        </DropdownMenuItem>
                    ))}
                </DropdownMenuContent>
            </DropdownMenu>
        </Fragment>
    );
};

Show.layout = (page: React.ReactNode) => <ApplicationLayout>{page}</ApplicationLayout>;

export default Show;
