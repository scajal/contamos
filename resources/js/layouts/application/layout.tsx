import { Link, usePage } from "@inertiajs/react";
import { useLaravelReactI18n } from "laravel-react-i18n";
import { BanknoteIcon, ChevronsUpDownIcon, CircleDollarSignIcon, LayoutDashboardIcon, LogOutIcon } from "lucide-react";

import { Avatar, AvatarFallback } from "@/components/ui/avatar";
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuGroup,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from "@/components/ui/dropdown-menu";
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
import currencies from "@/routes/catalog/currencies";
import accounts from "@/routes/finance/teams/accounts";

export const ApplicationLayout = ({ children }: { children: React.ReactNode }) => {
    return (
        <SidebarProvider>
            {/** Sidebar */}
            <Sidebar />
            {/** Wrapper */}
            <SidebarInset>
                {/** Header */}
                <header className="flex h-16 shrink-0 items-center gap-2 border-b transition-[width,height] ease-linear group-has-data-[collapsible=icon]/sidebar-wrapper:h-12">
                    <div className="px-4">
                        <SidebarTrigger className="-ml-1" />
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
    const { user } = usePage().props;

    return (
        <SidebarComponent collapsible="icon">
            <SidebarHeader>Logo</SidebarHeader>
            <SidebarContent>
                <SidebarGroup>
                    <SidebarMenu>
                        <SidebarMenuItem>
                            <SidebarMenuButton render={<Link href="/" />}>
                                <LayoutDashboardIcon />
                                <span>{t("Overview")}</span>
                            </SidebarMenuButton>
                        </SidebarMenuItem>
                        <SidebarMenuItem>
                            <SidebarMenuButton
                                render={<Link href={accounts.index.url({ team: user?.current_team_id ?? "" })} />}
                            >
                                <BanknoteIcon />
                                <span>{t("Accounts")}</span>
                            </SidebarMenuButton>
                        </SidebarMenuItem>
                    </SidebarMenu>
                </SidebarGroup>
                <SidebarGroup>
                    <SidebarGroupLabel>{t("Administration")}</SidebarGroupLabel>
                    <SidebarMenu>
                        <SidebarMenuItem>
                            <SidebarMenuButton render={<Link href={currencies.index.url()} />}>
                                <CircleDollarSignIcon />
                                <span>{t("Currencies")}</span>
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
                                <span className="truncate font-medium">{user?.email}</span>
                                <ChevronsUpDownIcon className="ml-auto size-4" />
                            </DropdownMenuTrigger>
                            <DropdownMenuContent side={isMobile ? "bottom" : "right"} align="end" sideOffset={4}>
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
