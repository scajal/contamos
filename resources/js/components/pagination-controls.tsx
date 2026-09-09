import { Link } from "@inertiajs/react";
import { useLaravelReactI18n } from "laravel-react-i18n";
import { ChevronLeftIcon, ChevronRightIcon } from "lucide-react";

import { Button } from "@/components/ui/button";
import { Pagination, PaginationContent, PaginationItem } from "@/components/ui/pagination";

type Props = {
    previousUrl?: string | null;
    nextUrl?: string | null;
};

export function PaginationControls({ previousUrl, nextUrl }: Props) {
    /**
     * Translate the provided string
     * to the current locale.
     */
    const t = useLaravelReactI18n().t;

    return (
        <Pagination>
            <PaginationContent>
                <PaginationItem>
                    {previousUrl ? (
                        <Button nativeButton={false} render={<Link href={previousUrl} />} variant="ghost">
                            <ChevronLeftIcon data-icon="inline-start" />
                            <span>{t("Previous")}</span>
                        </Button>
                    ) : (
                        <Button disabled variant="ghost">
                            <ChevronLeftIcon data-icon="inline-start" />
                            <span>{t("Previous")}</span>
                        </Button>
                    )}
                </PaginationItem>
                <PaginationItem>
                    {nextUrl ? (
                        <Button nativeButton={false} render={<Link href={nextUrl} />} variant="ghost">
                            <span>{t("Next")}</span>
                            <ChevronRightIcon data-icon="inline-end" />
                        </Button>
                    ) : (
                        <Button disabled variant="ghost">
                            <span>{t("Next")}</span>
                            <ChevronRightIcon data-icon="inline-end" />
                        </Button>
                    )}
                </PaginationItem>
            </PaginationContent>
        </Pagination>
    );
}
