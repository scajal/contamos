import { Link, usePage } from "@inertiajs/react";
import { ModalLink } from "@inertiaui/modal-react";
import { useLaravelReactI18n } from "laravel-react-i18n";
import {
    ArrowDownIcon,
    ArrowLeftRightIcon,
    ArrowUpIcon,
    BanknoteIcon,
    CheckIcon,
    ChevronRightIcon,
    ChevronsUpDownIcon,
    CircleDashedIcon,
    CreditCardIcon,
    CalendarRangeIcon,
    HistoryIcon,
    LayoutDashboardIcon,
    LifeBuoyIcon,
    LogOutIcon,
    PlusIcon,
    RepeatIcon,
    SettingsIcon,
    ShieldCheckIcon,
    TagIcon,
    UserIcon,
    UsersIcon,
    WalletIcon,
} from "lucide-react";
import { useState } from "react";
import { Fragment } from "react/jsx-runtime";

import { AppearanceMenu } from "@/components/appearance-menu";
import { BalancesToggle } from "@/components/balances-toggle";
import { Avatar, AvatarFallback, AvatarImage } from "@/components/ui/avatar";
import { Breadcrumb, BreadcrumbItem, BreadcrumbList, BreadcrumbPage } from "@/components/ui/breadcrumb";
import { Button } from "@/components/ui/button";
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from "@/components/ui/dialog";
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuGroup,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from "@/components/ui/dropdown-menu";
import { Item, ItemActions, ItemContent, ItemDescription, ItemGroup, ItemMedia, ItemTitle } from "@/components/ui/item";
import { Separator } from "@/components/ui/separator";
import {
    Sidebar as SidebarComponent,
    SidebarContent,
    SidebarFooter,
    SidebarGroup,
    SidebarGroupLabel,
    SidebarHeader,
    SidebarInset,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
    SidebarProvider,
    SidebarRail,
    SidebarTrigger,
} from "@/components/ui/sidebar";
import { useIsMobile } from "@/hooks/use-mobile";
import { usePermissions } from "@/hooks/use-permissions";
import { PERMISSIONS } from "@/lib/permissions";
import { initials } from "@/lib/utils";
import { logout } from "@/routes";
import subscriptions from "@/routes/billing/teams/subscriptions";
import administration from "@/routes/filament/administration";
import accounts from "@/routes/finance/teams/accounts";
import budgets from "@/routes/finance/teams/budgets";
import dashboard from "@/routes/finance/teams/dashboard";
import monthly from "@/routes/finance/teams/monthly";
import recurringRules from "@/routes/finance/teams/recurring-rules";
import tags from "@/routes/finance/teams/tags";
import transactions from "@/routes/finance/teams/transactions";
import transfers from "@/routes/finance/teams/transfers";
import teams from "@/routes/identity/teams";
import activity from "@/routes/identity/teams/activity";
import invitations from "@/routes/identity/teams/invitations";
import members from "@/routes/identity/teams/members";
import settings from "@/routes/identity/teams/settings";
import account from "@/routes/identity/users/account";

const PAGE_TITLES: Array<{ prefix: string; title: string }> = [
    { prefix: "finance/teams/accounts", title: "Accounts" },
    { prefix: "finance/teams/transactions", title: "Transactions" },
    { prefix: "finance/teams/dashboard", title: "Dashboard" },
    { prefix: "finance/teams/budgets", title: "Budgets" },
    { prefix: "finance/teams/monthly", title: "Monthly" },
    { prefix: "finance/teams/tags", title: "Tags" },
    { prefix: "finance/teams/recurring-rules", title: "Recurring" },
    { prefix: "identity/teams/activity", title: "Activity" },
    { prefix: "identity/teams/invitations", title: "Invitations" },
    { prefix: "identity/teams/members", title: "Members" },
    { prefix: "identity/teams/index", title: "Configuration" },
    { prefix: "identity/users/account", title: "Account" },
];

export const ApplicationLayout = ({
    breadcrumb,
    children,
}: {
    breadcrumb?: React.ReactNode;
    children: React.ReactNode;
}) => {
    /**
     * Translate the provided string to the
     * current locale.
     */
    const t = useLaravelReactI18n().t;

    /**
     * The current Inertia page.
     */
    const page = usePage();

    /**
     * The page's props.
     */
    const { user } = page.props;

    /**
     * The current page title for the header breadcrumb.
     */
    const pageTitle = PAGE_TITLES.find(({ prefix }) => page.component.startsWith(prefix))?.title;

    /**
     * The breadcrumb trail: an explicit override, or the current page.
     */
    const items =
        breadcrumb ??
        (pageTitle ? (
            <BreadcrumbItem>
                <BreadcrumbPage>{t(pageTitle)}</BreadcrumbPage>
            </BreadcrumbItem>
        ) : null);

    return (
        <SidebarProvider>
            {/** Sidebar */}
            <Sidebar />
            {/** Wrapper */}
            <SidebarInset className="min-w-0">
                {/** Header */}
                <header className="flex h-16 shrink-0 items-center gap-2 border-b transition-[width,height] ease-linear group-has-data-[collapsible=icon]/sidebar-wrapper:h-12">
                    <div className="flex h-full w-full items-center justify-between px-4">
                        <div className="flex items-center gap-2">
                            <SidebarTrigger className="-ml-1" />
                            {items ? (
                                <Fragment>
                                    <Separator
                                        className="mr-2 data-vertical:h-4 data-vertical:self-auto"
                                        orientation="vertical"
                                    />
                                    <Breadcrumb>
                                        <BreadcrumbList>{items}</BreadcrumbList>
                                    </Breadcrumb>
                                </Fragment>
                            ) : null}
                        </div>
                        {user?.current_team && (
                            <div className="flex items-center gap-1.5">
                                <BalancesToggle />
                                <OnboardingMenu />
                                <CreateTransactionActions team={user.current_team.slug} />
                            </div>
                        )}
                    </div>
                </header>
                {/** Content */}
                <main className="flex min-w-0 flex-1 px-5 py-6 md:px-12 md:py-12">
                    <div className="mx-auto flex w-full max-w-5xl min-w-0 flex-col">{children}</div>
                </main>
            </SidebarInset>
        </SidebarProvider>
    );
};

const CreateTransactionActions = ({ team }: { team: string }) => {
    /**
     * Translate the provided string to the
     * current locale.
     */
    const t = useLaravelReactI18n().t;

    return (
        <Fragment>
            {/** Every way of recording money, where the header has room for them. */}
            <div className="hidden gap-1.5 sm:flex">
                <Button
                    nativeButton={false}
                    render={
                        <ModalLink href={transfers.create.url({ team })}>
                            <ArrowLeftRightIcon data-icon="inline-start" />
                            {t("Transfer")}
                        </ModalLink>
                    }
                    size="sm"
                    variant="ghost"
                />
                <Button
                    nativeButton={false}
                    render={
                        <ModalLink href={transactions.create.url({ team, type: "income" })}>
                            <ArrowDownIcon data-icon="inline-start" />
                            {t("enums.income")}
                        </ModalLink>
                    }
                    size="sm"
                    variant="outline"
                />
                <Button
                    nativeButton={false}
                    render={
                        <ModalLink href={transactions.create.url({ team, type: "expense" })}>
                            <ArrowUpIcon data-icon="inline-start" />
                            {t("enums.expense")}
                        </ModalLink>
                    }
                    size="sm"
                />
            </div>

            {/** On a phone there is no room for three buttons, so the plus
             * opens the same three ways of recording money as a menu. */}
            <div className="flex items-center sm:hidden">
                <DropdownMenu>
                    <DropdownMenuTrigger render={<Button size="icon-sm" />}>
                        <PlusIcon />
                        <span className="sr-only">{t("New :name", { name: t("transaction") })}</span>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent align="end">
                        <DropdownMenuGroup>
                            <DropdownMenuItem
                                render={
                                    <ModalLink href={transactions.create.url({ team, type: "expense" })}>
                                        <ArrowUpIcon />
                                        <span>{t("New :name", { name: t("expense") })}</span>
                                    </ModalLink>
                                }
                            />
                            <DropdownMenuItem
                                render={
                                    <ModalLink href={transactions.create.url({ team, type: "income" })}>
                                        <ArrowDownIcon />
                                        <span>{t("New :name", { name: t("income") })}</span>
                                    </ModalLink>
                                }
                            />
                            <DropdownMenuItem
                                render={
                                    <ModalLink href={transfers.create.url({ team })}>
                                        <ArrowLeftRightIcon />
                                        <span>{t("Transfer")}</span>
                                    </ModalLink>
                                }
                            />
                        </DropdownMenuGroup>
                    </DropdownMenuContent>
                </DropdownMenu>
            </div>
        </Fragment>
    );
};

/**
 * The storage key holding the dismissal of a team's setup
 * checklist, kept per team so dismissing one team's checklist
 * leaves the others alone.
 */
const dismissalKey = (team: string) => `onboarding-dismissed:${team}`;

/**
 * Read whether the checklist was dismissed for the team in this
 * browser. Private windows and blocked site data throw on access,
 * where nothing was ever dismissed.
 */
const readDismissal = (team: string): boolean => {
    try {
        return window.localStorage.getItem(dismissalKey(team)) === "1";
    } catch {
        return false;
    }
};

/**
 * Remember that the checklist was dismissed for the team. A
 * browser that refuses to store it hides the checklist for this
 * visit alone.
 */
const writeDismissal = (team: string): void => {
    try {
        window.localStorage.setItem(dismissalKey(team), "1");
    } catch {
        return;
    }
};

const OnboardingMenu = () => {
    /**
     * Translate the provided string to the
     * current locale.
     */
    const t = useLaravelReactI18n().t;

    /**
     * The page's props.
     */
    const { onboarding, user } = usePage().props;

    /**
     * The team the user is acting on.
     */
    const team = user?.current_team;

    /**
     * Whether the checklist is open.
     */
    const [open, setOpen] = useState(false);

    /**
     * Whether the checklist was dismissed, remembered against the
     * team it was read for so switching teams reads again.
     */
    const [dismissal, setDismissal] = useState(() => ({
        dismissed: readDismissal(team?.id ?? ""),
        team: team?.id ?? "",
    }));

    // Switching teams keeps the layout mounted, so the dismissal
    // is re-read during the render that brings the new team in.
    if (dismissal.team !== (team?.id ?? "")) {
        setDismissal({ dismissed: readDismissal(team?.id ?? ""), team: team?.id ?? "" });
    }

    /**
     * Hide the checklist for this team in this browser.
     */
    const dismiss = () => {
        writeDismissal(team?.id ?? "");

        setOpen(false);
        setDismissal({ dismissed: true, team: team?.id ?? "" });
    };

    if (!team || !onboarding) {
        return null;
    }

    /**
     * The setup steps, in a fixed order whatever is done, each
     * opening the screen where the team takes it.
     */
    const steps = [
        {
            description: t("Accounts hold your balances per currency."),
            done: onboarding.has_account,
            href: accounts.index.url({ team: team.slug }),
            key: "account",
            title: t("Create an account"),
        },
        {
            description: t("Record an income or an expense on an account."),
            done: onboarding.has_transaction,
            href: transactions.index.url({ team: team.slug }),
            key: "transaction",
            title: t("Add a first transaction"),
        },
        {
            description: t("Members see and record this team's activity together."),
            done: onboarding.has_member,
            href: invitations.index.url({ team: team.slug }),
            key: "member",
            title: t("Invite a member"),
        },
    ];

    /**
     * How many steps the team already took.
     */
    const done = steps.filter((step) => step.done).length;

    // A finished team, and a member who put the checklist away,
    // keep the header to themselves.
    if (done === steps.length || dismissal.dismissed) {
        return null;
    }

    return (
        <Dialog onOpenChange={setOpen} open={open}>
            <DialogTrigger
                render={
                    <Button
                        aria-label={`${t("Finish your onboarding")} (${done}/${steps.length})`}
                        size="sm"
                        variant="ghost"
                    />
                }
            >
                <CircleDashedIcon data-icon="inline-start" />
                <span className="hidden sm:inline">{t("Finish your onboarding")}</span>
                <span className="tabular-nums">{`(${done}/${steps.length})`}</span>
            </DialogTrigger>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>{t("Set up :team", { team: team.name })}</DialogTitle>
                    <DialogDescription>{t("Three steps to get going.")}</DialogDescription>
                </DialogHeader>
                <div className="overflow-hidden rounded-xl border">
                    <ItemGroup className="gap-0 has-data-[size=sm]:gap-0">
                        {steps.map((step) => (
                            <Item
                                className="rounded-none border-0 border-b last:border-b-0"
                                key={step.key}
                                // A step already taken leads nowhere: there is
                                // nothing left to do on its screen.
                                {...(step.done
                                    ? {}
                                    : { render: <Link href={step.href} onClick={() => setOpen(false)} prefetch /> })}
                                size="sm"
                                variant="outline"
                            >
                                <ItemMedia variant="icon">
                                    {step.done ? <CheckIcon className="text-success" /> : <CircleDashedIcon />}
                                </ItemMedia>
                                <ItemContent>
                                    <ItemTitle>{step.title}</ItemTitle>
                                    <ItemDescription>{step.description}</ItemDescription>
                                </ItemContent>
                                {step.done ? null : (
                                    <ItemActions>
                                        <ChevronRightIcon className="text-muted-foreground size-4" />
                                    </ItemActions>
                                )}
                            </Item>
                        ))}
                    </ItemGroup>
                </div>
                <DialogFooter>
                    <Button onClick={dismiss} size="sm" variant="ghost">
                        {t("Dismiss")}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
};

const Sidebar = () => {
    /**
     * Translate the provided string to the
     * current locale.
     */
    const t = useLaravelReactI18n().t;

    /**
     * Check if the screen is mobile.
     */
    const isMobile = useIsMobile();

    /**
     * The current Inertia page.
     */
    const page = usePage();

    /**
     * The page's props.
     */
    const { auth, support, user } = page.props;

    /**
     * What the user may do on the current team.
     */
    const { can } = usePermissions();

    /**
     * The team the sidebar's links act on.
     */
    const team = user?.current_team?.slug ?? "";

    /**
     * Whether the current page belongs to one of the given sections, so
     * the sidebar can mark where the user is.
     */
    const isSection = (...prefixes: Array<string>): boolean =>
        prefixes.some((prefix) => page.component.startsWith(prefix));

    return (
        <SidebarComponent collapsible="icon">
            <SidebarHeader>
                <TeamSwitcher />
            </SidebarHeader>
            <SidebarContent>
                <SidebarGroup>
                    <SidebarMenu>
                        <SidebarMenuItem>
                            <SidebarMenuButton
                                isActive={isSection("finance/teams/dashboard")}
                                render={<Link href={dashboard.index.url({ team })} />}
                            >
                                <LayoutDashboardIcon />
                                <span>{t("Dashboard")}</span>
                            </SidebarMenuButton>
                        </SidebarMenuItem>
                        <SidebarMenuItem>
                            <SidebarMenuButton
                                isActive={isSection("finance/teams/monthly")}
                                render={<Link href={monthly.index.url({ team })} />}
                            >
                                <CalendarRangeIcon />
                                <span>{t("Monthly")}</span>
                            </SidebarMenuButton>
                        </SidebarMenuItem>
                    </SidebarMenu>
                </SidebarGroup>
                <SidebarGroup>
                    <SidebarGroupLabel>{t("Day to day")}</SidebarGroupLabel>
                    <SidebarMenu>
                        <SidebarMenuItem>
                            <SidebarMenuButton
                                isActive={isSection("finance/teams/transactions", "finance/teams/transfers")}
                                render={<Link href={transactions.index.url({ team })} />}
                            >
                                <ArrowLeftRightIcon />
                                <span>{t("Transactions")}</span>
                            </SidebarMenuButton>
                        </SidebarMenuItem>
                        <SidebarMenuItem>
                            <SidebarMenuButton
                                isActive={isSection("finance/teams/accounts")}
                                render={<Link href={accounts.index.url({ team })} />}
                            >
                                <BanknoteIcon />
                                <span>{t("Accounts")}</span>
                            </SidebarMenuButton>
                        </SidebarMenuItem>
                    </SidebarMenu>
                </SidebarGroup>
                <SidebarGroup>
                    <SidebarGroupLabel>{t("Planning")}</SidebarGroupLabel>
                    <SidebarMenu>
                        <SidebarMenuItem>
                            <SidebarMenuButton
                                isActive={isSection("finance/teams/budgets")}
                                render={<Link href={budgets.index.url({ team })} />}
                            >
                                <WalletIcon />
                                <span>{t("Budgets")}</span>
                            </SidebarMenuButton>
                        </SidebarMenuItem>
                        <SidebarMenuItem>
                            <SidebarMenuButton
                                isActive={isSection("finance/teams/recurring-rules")}
                                render={<Link href={recurringRules.index.url({ team })} />}
                            >
                                <RepeatIcon />
                                <span>{t("Recurring")}</span>
                            </SidebarMenuButton>
                        </SidebarMenuItem>
                        <SidebarMenuItem>
                            <SidebarMenuButton
                                isActive={isSection("finance/teams/tags")}
                                render={<Link href={tags.index.url({ team })} />}
                            >
                                <TagIcon />
                                <span>{t("Tags")}</span>
                            </SidebarMenuButton>
                        </SidebarMenuItem>
                    </SidebarMenu>
                </SidebarGroup>
                <SidebarGroup>
                    <SidebarGroupLabel>{t("Team")}</SidebarGroupLabel>
                    <SidebarMenu>
                        <SidebarMenuItem>
                            <SidebarMenuButton
                                isActive={isSection("identity/teams/members")}
                                render={<Link href={members.index.url({ team })} />}
                            >
                                <UsersIcon />
                                <span>{t("Members")}</span>
                            </SidebarMenuButton>
                        </SidebarMenuItem>
                        {can(PERMISSIONS.ViewActivity) && (
                            <SidebarMenuItem>
                                <SidebarMenuButton
                                    isActive={isSection("identity/teams/activity")}
                                    render={<Link href={activity.index.url({ team })} />}
                                >
                                    <HistoryIcon />
                                    <span>{t("Activity")}</span>
                                </SidebarMenuButton>
                            </SidebarMenuItem>
                        )}
                        {can(PERMISSIONS.ManageBilling) && (
                            <SidebarMenuItem>
                                <SidebarMenuButton
                                    isActive={isSection("billing/teams/subscriptions")}
                                    render={<Link href={subscriptions.index.url({ team })} />}
                                >
                                    <CreditCardIcon />
                                    <span>{t("Billing")}</span>
                                </SidebarMenuButton>
                            </SidebarMenuItem>
                        )}
                        <SidebarMenuItem>
                            <SidebarMenuButton
                                isActive={isSection("identity/teams/index", "identity/teams/edit")}
                                render={<Link href={settings.index.url({ team })} />}
                            >
                                <SettingsIcon />
                                <span>{t("Configuration")}</span>
                            </SidebarMenuButton>
                        </SidebarMenuItem>
                    </SidebarMenu>
                </SidebarGroup>
            </SidebarContent>
            <SidebarFooter>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <DropdownMenu>
                            <DropdownMenuTrigger
                                render={<SidebarMenuButton className="aria-expanded:bg-muted" size="lg" />}
                            >
                                <Avatar>
                                    <AvatarImage src={user?.avatar ?? undefined} />
                                    <AvatarFallback>{initials(user?.name ?? "")}</AvatarFallback>
                                </Avatar>
                                <div className="flex flex-col">
                                    <span className="truncate font-medium">{user?.name}</span>
                                    <span className="text-muted-foreground truncate text-sm">{user?.email}</span>
                                </div>
                                <ChevronsUpDownIcon className="ml-auto size-4" />
                            </DropdownMenuTrigger>
                            <DropdownMenuContent side={isMobile ? "bottom" : "right"} align="end" sideOffset={4}>
                                {auth.canAccessAdministration && (
                                    <Fragment>
                                        <DropdownMenuGroup>
                                            <DropdownMenuItem
                                                render={
                                                    <a href={administration.pages.dashboard.url()} target="_blank" />
                                                }
                                            >
                                                <ShieldCheckIcon />
                                                <span>{t("Administration")}</span>
                                            </DropdownMenuItem>
                                        </DropdownMenuGroup>
                                        <DropdownMenuSeparator />
                                    </Fragment>
                                )}
                                <DropdownMenuGroup>
                                    <DropdownMenuItem render={<Link href={account.index.url()} />}>
                                        <UserIcon />
                                        <span>{t("Account")}</span>
                                    </DropdownMenuItem>
                                    <DropdownMenuItem render={<a href={`mailto:${support.email}`} />}>
                                        <LifeBuoyIcon />
                                        <span>{t("Support")}</span>
                                    </DropdownMenuItem>
                                </DropdownMenuGroup>
                                <DropdownMenuSeparator />
                                <DropdownMenuGroup>
                                    <DropdownMenuLabel>{t("Theme")}</DropdownMenuLabel>
                                    <AppearanceMenu />
                                </DropdownMenuGroup>
                                <DropdownMenuSeparator />
                                <DropdownMenuGroup>
                                    <DropdownMenuItem render={<a href={logout.url()} />}>
                                        <LogOutIcon />
                                        <span>{t("Log Out")}</span>
                                    </DropdownMenuItem>
                                </DropdownMenuGroup>
                            </DropdownMenuContent>
                        </DropdownMenu>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarFooter>
            <SidebarRail />
        </SidebarComponent>
    );
};

const TeamSwitcher = () => {
    /**
     * Translate the provided string to the
     * current locale.
     */
    const t = useLaravelReactI18n().t;

    /**
     * Check if the screen is mobile.
     */
    const isMobile = useIsMobile();

    /**
     * The page's props.
     */
    const { user } = usePage().props;

    /**
     * The team the user is currently on.
     */
    const currentTeam = user?.current_team;

    if (!currentTeam) {
        return null;
    }

    return (
        <SidebarMenu>
            <SidebarMenuItem>
                <DropdownMenu>
                    <DropdownMenuTrigger
                        render={<SidebarMenuButton className="aria-expanded:bg-muted h-auto" size="sm" />}
                    >
                        <Avatar size="sm">
                            <AvatarFallback>{initials(currentTeam.name)}</AvatarFallback>
                        </Avatar>
                        <div className="flex min-w-0 flex-1 flex-col">
                            <span className="text-muted-foreground truncate text-xs">{t("Team")}</span>
                            <span className="truncate text-sm font-medium">{currentTeam.name}</span>
                        </div>
                        <ChevronsUpDownIcon className="ml-auto" />
                    </DropdownMenuTrigger>
                    <DropdownMenuContent
                        align="start"
                        className="min-w-56"
                        side={isMobile ? "bottom" : "right"}
                        sideOffset={4}
                    >
                        <DropdownMenuGroup>
                            {(user.teams ?? []).map((team) => {
                                // Teams can share a name, so the one in use is
                                // named by its slug and marked with a check.
                                const isCurrent = team.id === currentTeam.id;

                                return (
                                    <DropdownMenuItem
                                        key={team.id}
                                        render={<Link href={dashboard.index.url({ team: team.slug })} />}
                                    >
                                        <Avatar size="sm">
                                            <AvatarFallback>{initials(team.name)}</AvatarFallback>
                                        </Avatar>
                                        <div className="flex min-w-0 flex-1 flex-col">
                                            <span className="truncate">{team.name}</span>
                                            <span className="text-muted-foreground truncate text-xs">{team.slug}</span>
                                        </div>
                                        {isCurrent ? <CheckIcon className="ml-auto size-4 shrink-0" /> : null}
                                    </DropdownMenuItem>
                                );
                            })}
                        </DropdownMenuGroup>
                        <DropdownMenuSeparator />
                        <DropdownMenuItem render={<Link href={teams.create.url()} />}>
                            <PlusIcon />
                            <span>{t("Create :name", { name: t("team") })}</span>
                        </DropdownMenuItem>
                    </DropdownMenuContent>
                </DropdownMenu>
            </SidebarMenuItem>
        </SidebarMenu>
    );
};
