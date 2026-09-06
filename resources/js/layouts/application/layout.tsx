import { Link, usePage } from "@inertiajs/react";
import { ModalLink } from "@inertiaui/modal-react";
import { useLaravelReactI18n } from "laravel-react-i18n";
import {
    ArrowDownIcon,
    ArrowLeftRightIcon,
    ArrowUpIcon,
    BanknoteIcon,
    ChevronDownIcon,
    ChevronsUpDownIcon,
    LayoutDashboardIcon,
    LogOutIcon,
    ShieldCheckIcon,
    TagIcon,
} from "lucide-react";
import { Fragment } from "react/jsx-runtime";

import { Avatar, AvatarFallback } from "@/components/ui/avatar";
import { Breadcrumb, BreadcrumbItem, BreadcrumbList, BreadcrumbPage } from "@/components/ui/breadcrumb";
import { Button } from "@/components/ui/button";
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuGroup,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from "@/components/ui/dropdown-menu";
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
import { logout } from "@/routes";
import administration from "@/routes/filament/administration";
import accounts from "@/routes/finance/teams/accounts";
import tags from "@/routes/finance/teams/tags";
import transactions from "@/routes/finance/teams/transactions";

const PAGE_TITLES: Array<{ prefix: string; title: string }> = [
    { prefix: "finance/teams/accounts", title: "Accounts" },
    { prefix: "finance/teams/transactions", title: "Transactions" },
    { prefix: "finance/teams/dashboard", title: "Dashboard" },
    { prefix: "finance/teams/tags", title: "Tags" },
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
            <SidebarInset>
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
                            <DropdownMenu>
                                <DropdownMenuTrigger render={<Button />}>
                                    <span>{t("Create :name", { name: t("transaction") })}</span>
                                    <ChevronDownIcon className="transition-transform in-data-popup-open:rotate-180" />
                                </DropdownMenuTrigger>
                                <DropdownMenuContent align="end" className="w-auto min-w-(--anchor-width)">
                                    <DropdownMenuGroup>
                                        <DropdownMenuItem
                                            render={
                                                <ModalLink
                                                    href={transactions.create.url({
                                                        team: user.current_team.slug,
                                                        type: "income",
                                                    })}
                                                >
                                                    <ArrowDownIcon />
                                                    <span>{t("Register an :name", { name: t("income") })}</span>
                                                </ModalLink>
                                            }
                                        />
                                        <DropdownMenuItem
                                            render={
                                                <ModalLink
                                                    href={transactions.create.url({
                                                        team: user.current_team.slug,
                                                        type: "expense",
                                                    })}
                                                >
                                                    <ArrowUpIcon />
                                                    <span>{t("Register an :name", { name: t("expense") })}</span>
                                                </ModalLink>
                                            }
                                        />
                                    </DropdownMenuGroup>
                                </DropdownMenuContent>
                            </DropdownMenu>
                        )}
                    </div>
                </header>
                {/** Content */}
                <main className="flex flex-1 p-4 md:p-10">
                    <div className="mx-auto flex w-full max-w-5xl flex-col">{children}</div>
                </main>
            </SidebarInset>
        </SidebarProvider>
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
     * The page's props.
     */
    const { auth, user } = usePage().props;

    return (
        <SidebarComponent collapsible="icon">
            <SidebarHeader>
                <img src="/images/iso-logo.png" alt="ISO" className="ml-2 size-8" />
            </SidebarHeader>
            <SidebarContent>
                <SidebarGroup>
                    <SidebarMenu>
                        <SidebarMenuItem>
                            <SidebarMenuButton render={<Link href="/" />}>
                                <LayoutDashboardIcon />
                                <span>{t("Dashboard")}</span>
                            </SidebarMenuButton>
                        </SidebarMenuItem>
                    </SidebarMenu>
                </SidebarGroup>
                <SidebarGroup>
                    <SidebarGroupLabel>{t("Team")}</SidebarGroupLabel>
                    <SidebarMenu>
                        <SidebarMenuItem>
                            <SidebarMenuButton
                                render={<Link href={accounts.index.url({ team: user?.current_team?.slug ?? "" })} />}
                            >
                                <BanknoteIcon />
                                <span>{t("Accounts")}</span>
                            </SidebarMenuButton>
                        </SidebarMenuItem>
                        <SidebarMenuItem>
                            <SidebarMenuButton
                                render={<Link href={tags.index.url({ team: user?.current_team?.slug ?? "" })} />}
                            >
                                <TagIcon />
                                <span>{t("Tags")}</span>
                            </SidebarMenuButton>
                        </SidebarMenuItem>
                        <SidebarMenuItem>
                            <SidebarMenuButton
                                render={<Link href={transactions.index.url({ team: user?.current_team?.slug ?? "" })} />}
                            >
                                <ArrowLeftRightIcon />
                                <span>{t("Transactions")}</span>
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
                                    <AvatarFallback>CP</AvatarFallback>
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
                                    <DropdownMenuItem render={<a href={logout.url()} />}>
                                        <LogOutIcon />
                                        <span>{t("Logout")}</span>
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
