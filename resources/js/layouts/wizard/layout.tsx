import { usePage } from "@inertiajs/react";
import { useLaravelReactI18n } from "laravel-react-i18n";
import { LogOutIcon } from "lucide-react";

import { Avatar, AvatarFallback, AvatarImage } from "@/components/ui/avatar";
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuGroup,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from "@/components/ui/dropdown-menu";
import { SidebarInset, SidebarProvider } from "@/components/ui/sidebar";
import { logout } from "@/routes";

/**
 * The initials of the given name, used as the
 * avatar fallback.
 */
const initials = (name: string) =>
    name
        .split(" ")
        .filter(Boolean)
        .slice(0, 2)
        .map((part) => part[0]?.toUpperCase() ?? "")
        .join("");

export const WizardLayout = ({ children }: { children: React.ReactNode }) => {
    /**
     * Translate the provided string to the
     * current locale.
     */
    const t = useLaravelReactI18n().t;

    /**
     * The page's props.
     */
    const { user } = usePage().props;

    return (
        <SidebarProvider>
            {/** Wrapper */}
            <SidebarInset>
                {/** Header */}
                <header className="flex h-16 shrink-0 items-center gap-2 transition-[width,height] ease-linear group-has-data-[collapsible=icon]/sidebar-wrapper:h-12">
                    <div className="w-full px-4 md:px-10">
                        <div className="mx-auto flex w-full max-w-5xl items-center justify-between">
                            <div>Logo</div>
                            <DropdownMenu>
                                <DropdownMenuTrigger>
                                    <Avatar>
                                        <AvatarImage src={user?.avatar ?? undefined} />
                                        <AvatarFallback>{initials(user?.name ?? "")}</AvatarFallback>
                                    </Avatar>
                                </DropdownMenuTrigger>
                                <DropdownMenuContent
                                    align="center"
                                    className="w-auto min-w-(--anchor-width)"
                                    side="bottom"
                                    sideOffset={4}
                                >
                                    <DropdownMenuGroup>
                                        <DropdownMenuItem render={<a href={logout.url()} />}>
                                            <LogOutIcon />
                                            <span>{t("Logout")}</span>
                                        </DropdownMenuItem>
                                    </DropdownMenuGroup>
                                </DropdownMenuContent>
                            </DropdownMenu>
                        </div>
                    </div>
                </header>
                {/** Content */}
                <main className="flex flex-1 p-4 md:p-10">
                    <div className="mx-auto flex w-full max-w-3xl flex-col justify-center">{children}</div>
                </main>
            </SidebarInset>
        </SidebarProvider>
    );
};
