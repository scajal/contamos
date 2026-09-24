import { usePage } from "@inertiajs/react";

import { BrandMark } from "@/components/brand-mark";
import { Avatar, AvatarFallback, AvatarImage } from "@/components/ui/avatar";
import { DropdownMenu, DropdownMenuContent, DropdownMenuTrigger } from "@/components/ui/dropdown-menu";
import { SidebarInset, SidebarProvider } from "@/components/ui/sidebar";
import { UserMenuItems } from "@/components/user-menu";
import { initials } from "@/lib/utils";
import { home } from "@/routes";

export const WizardLayout = ({ children }: { children: React.ReactNode }) => {
    /**
     * The page's props.
     */
    const { locale, user } = usePage().props;

    return (
        <SidebarProvider>
            {/** Wrapper */}
            <SidebarInset>
                {/** Header */}
                <header className="flex h-16 shrink-0 items-center gap-2 transition-[width,height] ease-linear group-has-data-[collapsible=icon]/sidebar-wrapper:h-12">
                    <div className="w-full px-4 md:px-10">
                        <div className="mx-auto flex w-full max-w-5xl items-center justify-between">
                            {/** Logo */}
                            <a href={home.url({ locale })} className="flex shrink-0 items-center gap-2">
                                <BrandMark />
                                <span className="font-heading text-base font-semibold tracking-tight">contamos</span>
                            </a>
                            {/** User menu, when somebody is signed in */}
                            {user && (
                                <DropdownMenu>
                                    <DropdownMenuTrigger className="rounded-full">
                                        <Avatar>
                                            <AvatarImage src={user?.avatar ?? undefined} />
                                            <AvatarFallback>{initials(user?.name ?? "")}</AvatarFallback>
                                        </Avatar>
                                    </DropdownMenuTrigger>
                                    <DropdownMenuContent align="end" className="min-w-56" side="bottom" sideOffset={4}>
                                        <div className="flex flex-col px-2 py-1.5">
                                            <span className="truncate text-sm font-medium">{user?.name}</span>
                                            <span className="text-muted-foreground truncate text-xs">
                                                {user?.email}
                                            </span>
                                        </div>
                                        <UserMenuItems />
                                    </DropdownMenuContent>
                                </DropdownMenu>
                            )}
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
