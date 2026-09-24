import { Link } from "@inertiajs/react";

import { ProBadge } from "@/components/pro-badge";
import { cn } from "@/lib/utils";
import type { DocsNavigationGroup } from "@/types/app/modules/marketing/docs";

interface Props {
    className?: string;
    current: string;
    navigation: Array<DocsNavigationGroup>;
    onNavigate?: () => void;
}

/**
 * Every page of the docs under its group, with the page being read
 * highlighted. The docs sidebar shows it from `lg` up, and the
 * header drawer shows it below that, so a reader on a phone has a
 * single menu instead of two.
 */
export const DocsNavigation = ({ className, current, navigation, onNavigate }: Props) => (
    <nav className={cn("flex flex-col gap-6", className)}>
        {navigation.map((group) => (
            <div className="flex flex-col gap-1" key={group.label}>
                <h2 className="text-foreground px-2 pb-1 text-xs font-medium">{group.label}</h2>
                <ul className="flex flex-col gap-0.5">
                    {group.pages.map((item) => {
                        const active = item.page === current;

                        return (
                            <li key={item.page}>
                                <Link
                                    aria-current={active ? "page" : undefined}
                                    className={cn(
                                        "flex items-center justify-between gap-2 rounded-md px-2 py-1.5 text-sm transition-colors",
                                        active
                                            ? "bg-muted text-foreground font-medium"
                                            : "text-muted-foreground hover:bg-muted/60 hover:text-foreground",
                                    )}
                                    href={item.url}
                                    onClick={onNavigate}
                                >
                                    {item.label}
                                    {item.pro && <ProBadge />}
                                </Link>
                            </li>
                        );
                    })}
                </ul>
            </div>
        ))}
    </nav>
);
