import { Link } from "@inertiajs/react";
import { useLaravelReactI18n } from "laravel-react-i18n";
import { MenuIcon } from "lucide-react";
import { useState } from "react";

import { Button } from "@/components/ui/button";
import { Sheet, SheetContent, SheetHeader, SheetTitle, SheetTrigger } from "@/components/ui/sheet";
import { cn } from "@/lib/utils";
import docs from "@/routes/docs";
import type { DocsNavigationGroup } from "@/types/app/modules/marketing/docs";

import { DocsNavigation } from "./docs-navigation";

/**
 * The homepage's sections the header leads to, by anchor.
 */
const SECTIONS = [
    { hash: "features", label: "Features" },
    { hash: "pricing", label: "Pricing" },
] as const;

interface Props {
    variant: "inline" | "drawer";
    docsPage: string | null;
    homeUrl: string;
    isHome: boolean;
    navigation: Array<DocsNavigationGroup> | null;
}

/**
 * The links of the marketing header: Features, Pricing and Docs.
 * From `md` up they sit inline in the pill. Below that a menu
 * button opens them in a drawer from the right, which on a docs
 * page also lists every page of the docs, standing in for the docs
 * sidebar the page hides on small screens.
 */
export const HeaderMenu = ({ variant, docsPage, homeUrl, isHome, navigation }: Props) => {
    /**
     * Translate the provided string to the current locale.
     */
    const { t, currentLocale } = useLaravelReactI18n();

    /**
     * Whether the drawer is open.
     */
    const [open, setOpen] = useState(false);

    /**
     * The URL of the docs in the locale being read.
     */
    const docsUrl = docs.index.url({ locale: currentLocale() });

    /**
     * The section links stay bare fragments on the homepage, so the
     * browser scrolls instead of reloading, and lead home first
     * from anywhere else.
     */
    const sectionUrl = (hash: string) => (isHome ? `#${hash}` : `${homeUrl}#${hash}`);

    /**
     * The links, shared by the pill and the drawer.
     */
    const links = (className: string, onNavigate?: () => void) => (
        <>
            {SECTIONS.map(({ hash, label }) => (
                <a className={className} href={sectionUrl(hash)} key={hash} onClick={onNavigate}>
                    {t(label)}
                </a>
            ))}
            <Link
                aria-current={docsPage ? "page" : undefined}
                className={cn(className, docsPage && "text-foreground")}
                href={docsUrl}
                onClick={onNavigate}
            >
                {t("Docs")}
            </Link>
        </>
    );

    if (variant === "inline") {
        return (
            <nav aria-label={t("Main")} className="hidden items-center gap-0.5 md:flex">
                {links(
                    "text-muted-foreground hover:text-foreground hover:bg-muted/60 rounded-full px-3 py-1.5 text-sm font-medium transition-colors",
                )}
            </nav>
        );
    }

    return (
        <>
            {/** Drawer, below md, or below lg on a docs page, where it
             * also stands in for the docs sidebar */}
            <Sheet onOpenChange={setOpen} open={open}>
                <SheetTrigger
                    render={
                        <Button
                            aria-label={t("Open menu")}
                            className={cn("rounded-full", docsPage ? "lg:hidden" : "md:hidden")}
                            size="icon-sm"
                            variant="ghost"
                        />
                    }
                >
                    <MenuIcon />
                </SheetTrigger>
                <SheetContent className="w-4/5 max-w-xs gap-0 overflow-y-auto" side="right">
                    <SheetHeader>
                        <SheetTitle>{t("Menu")}</SheetTitle>
                    </SheetHeader>
                    <nav aria-label={t("Main")} className="flex flex-col gap-0.5 px-2 pb-4">
                        {links(
                            "text-muted-foreground hover:text-foreground hover:bg-muted/60 rounded-md px-2 py-2 text-base font-medium transition-colors",
                            () => setOpen(false),
                        )}
                    </nav>
                    {docsPage && navigation && (
                        <DocsNavigation
                            className="border-t px-2 pt-4 pb-8"
                            current={docsPage}
                            navigation={navigation}
                            onNavigate={() => setOpen(false)}
                        />
                    )}
                </SheetContent>
            </Sheet>
        </>
    );
};
