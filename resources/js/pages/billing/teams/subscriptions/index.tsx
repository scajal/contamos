import { Deferred, Link, usePage } from "@inertiajs/react";
import { ModalLink } from "@inertiaui/modal-react";
import { BorderBeam } from "border-beam";
import { format } from "date-fns";
import { useLaravelReactI18n } from "laravel-react-i18n";
import {
    ArrowUpRightFromSquareIcon,
    CheckCircleIcon,
    ChevronDownIcon,
    ChevronLeftIcon,
    ChevronRightIcon,
    CircleDashedIcon,
    MoreHorizontalIcon,
    PrinterIcon,
    ReceiptTextIcon,
    SparklesIcon,
    XIcon,
} from "lucide-react";
import { Fragment } from "react/jsx-runtime";

import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from "@/components/ui/dialog";
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuGroup,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from "@/components/ui/dropdown-menu";
import { Empty, EmptyHeader, EmptyMedia, EmptyTitle } from "@/components/ui/empty";
import {
    Item,
    ItemActions,
    ItemContent,
    ItemDescription,
    ItemGroup,
    ItemHeader,
    ItemMedia,
    ItemTitle,
} from "@/components/ui/item";
import { Modal } from "@/components/ui/modal";
import { Pagination as PaginationComponent, PaginationContent, PaginationItem } from "@/components/ui/pagination";
import { Skeleton } from "@/components/ui/skeleton";
import { ApplicationLayout } from "@/layouts/application/layout";
import subscriptions from "@/routes/billing/teams/subscriptions";
import { Transaction } from "@/types/app/modules/billing/models/transaction";
import { Team } from "@/types/app/modules/core/identity/models/team";
import { LengthAwarePaginator } from "@/types/illuminate/pagination/length-aware-paginator";

type PageProps = {
    plan: any;
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
                {/** Current plan */}
                {/** <Plan /> */}
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
            <h1 className="text-2xl font-medium">{t("Billing")}</h1>
            <p className="text-muted-foreground text-sm">{t("Manage your team's billing and subscription.")}</p>
        </header>
    );
};

const Plan = () => {
    /**
     * Translate the provided string
     * to the current locale.
     */
    const t = useLaravelReactI18n().t;

    /**
     * The page's props.
     */
    const { plan, team } = usePage<PageProps>().props;

    return (
        <Item className="bg-background" variant="outline">
            <ItemContent>
                <ItemHeader>
                    <ItemTitle>{t("Plan :name", { name: plan.name })}</ItemTitle>
                    {plan.subscription && <Badge>{plan.subscription.status}</Badge>}
                </ItemHeader>
                <ItemDescription>{t("The plan your team is currently subscribed to.")}</ItemDescription>
            </ItemContent>

            <Item variant="muted">
                <ItemContent>
                    {plan?.features?.map((feature) => (
                        <Item key={feature.value}>
                            <ItemMedia variant="icon">
                                <CheckCircleIcon className="text-primary" />
                            </ItemMedia>
                            <ItemContent>
                                <ItemHeader>
                                    <ItemTitle>{feature.name}</ItemTitle>
                                    <ItemDescription>{`${feature.usage} / ${feature.limit ?? t("Unlimited")}`}</ItemDescription>
                                </ItemHeader>
                            </ItemContent>
                        </Item>
                    ))}
                </ItemContent>
            </Item>
            <ItemActions className="w-full justify-end">
                {/** Upgrade to pro */}
                {plan.value === "free" && <UpgradeToPro />}

                {/** Pro actions */}
                {plan.value === "pro" && (
                    <DropdownMenu>
                        <DropdownMenuTrigger render={<Button variant="outline" />}>
                            <span>{t("Manage")}</span>
                            <ChevronDownIcon className="transition-transform in-data-popup-open:rotate-180" />
                        </DropdownMenuTrigger>
                        <DropdownMenuContent align="end" className="w-auto min-w-(--anchor-width)">
                            <DropdownMenuItem
                                render={
                                    <a
                                        href={subscriptions.redirectToCustomerPortal.url({ team: team.slug })}
                                        target="_blank"
                                    />
                                }
                            >
                                <ArrowUpRightFromSquareIcon />
                                <span>{t("Customer portal")}</span>
                            </DropdownMenuItem>
                            <DropdownMenuItem variant="destructive">
                                <XIcon />
                                <span>{t("Cancel")}</span>
                            </DropdownMenuItem>
                        </DropdownMenuContent>
                    </DropdownMenu>
                )}
            </ItemActions>
        </Item>
    );
};

const UpgradeToPro = () => {
    /**
     * Translate the provided string
     * to the current locale.
     */
    const t = useLaravelReactI18n().t;

    /**
     * The page's props.
     */
    const { team } = usePage<PageProps>().props;

    return (
        <Fragment>
            <BorderBeam colorVariant="sunset">
                <Button
                    render={
                        <ModalLink href="#upgrade-to-pro">
                            <SparklesIcon />
                            <span>{t("Upgrade to Pro")}</span>
                        </ModalLink>
                    }
                    variant="outline"
                />
            </BorderBeam>
            <Modal name="upgrade-to-pro">
                {({ close, isOpen }) => (
                    <Dialog open={isOpen} onOpenChange={close}>
                        <DialogContent>
                            <DialogHeader>
                                <DialogTitle>{t("Upgrade to Pro")}</DialogTitle>
                                <DialogDescription>
                                    {t("Choose the billing cycle for your subscription.")}
                                </DialogDescription>
                            </DialogHeader>
                            <DialogFooter className="*:flex-1">
                                <Button
                                    render={
                                        <ModalLink
                                            href={subscriptions.subscribe.url({ team: team.slug, cycle: "monthly" })}
                                        >
                                            <span>{t("Monthly")}</span>
                                        </ModalLink>
                                    }
                                />
                                <Button
                                    render={
                                        <ModalLink
                                            href={subscriptions.subscribe.url({ team: team.slug, cycle: "yearly" })}
                                        >
                                            <span>{t("Yearly")}</span>
                                        </ModalLink>
                                    }
                                    variant="outline"
                                />
                            </DialogFooter>
                        </DialogContent>
                    </Dialog>
                )}
            </Modal>
        </Fragment>
    );
};

const Transactions = () => {
    /**
     * Translate the provided string
     * to the current locale.
     */
    const t = useLaravelReactI18n().t;

    /**
     * The page's props.
     */
    const { transactions } = usePage<PageProps>().props;

    return (
        <Fragment>
            <header className="flex flex-col gap-1">
                <h1 className="font-medium">{t("Transactions")}</h1>
                <p className="text-muted-foreground text-sm">{t("View your team's billing transactions.")}</p>
            </header>
            <Deferred data="transactions" fallback={<SkeletonRows />}>
                {!transactions?.data?.length ? (
                    <div className="rounded-sm border">
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
                        <div className="overflow-hidden rounded-sm border">
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
                                            <ItemTitle>{format(transaction.billed_at, "MMMM d, yyyy")}</ItemTitle>
                                            <ItemDescription>{`${transaction.currency} ${transaction.total}`}</ItemDescription>
                                        </ItemContent>
                                        <ItemActions className="flex-wrap justify-end">
                                            <Badge variant="ghost">{transaction.status}</Badge>
                                            <DropdownMenu>
                                                <DropdownMenuTrigger render={<Button size="icon" variant="ghost" />}>
                                                    <MoreHorizontalIcon />
                                                    <span className="sr-only">{t("Actions")}</span>
                                                </DropdownMenuTrigger>
                                                <DropdownMenuContent align="end">
                                                    <DropdownMenuGroup>
                                                        <DropdownMenuItem render={<a href="/" />}>
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
                        <Pagination />
                    </div>
                )}
            </Deferred>
        </Fragment>
    );
};

const SkeletonRows = () => {
    return (
        <div className="overflow-hidden rounded-sm border">
            {[0, 1, 2].map((row) => (
                <div className="flex items-center gap-3 border-b px-3 py-2.5 last:border-b-0" key={row}>
                    <Skeleton className="size-8 rounded-sm" />
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

const Pagination = () => {
    /**
     * Translate the provided string
     * to the current locale.
     */
    const t = useLaravelReactI18n().t;

    /**
     * The page's props.
     */
    const { transactions } = usePage<PageProps>().props;

    return (
        <PaginationComponent>
            <PaginationContent>
                <PaginationItem>
                    <Button
                        disabled={!transactions?.prev_page_url}
                        nativeButton={false}
                        render={<Link href={transactions?.prev_page_url ?? undefined} />}
                        variant="ghost"
                    >
                        <ChevronLeftIcon data-icon="inline-start" />
                        <span>{t("Previous")}</span>
                    </Button>
                </PaginationItem>
                <PaginationItem>
                    <Button
                        disabled={!transactions?.next_page_url}
                        nativeButton={false}
                        render={<Link href={transactions?.next_page_url ?? undefined} />}
                        variant="ghost"
                    >
                        <span>{t("Next")}</span>
                        <ChevronRightIcon data-icon="inline-end" />
                    </Button>
                </PaginationItem>
            </PaginationContent>
        </PaginationComponent>
    );
};

Index.layout = (page: React.ReactNode) => <ApplicationLayout>{page}</ApplicationLayout>;

export default Index;
