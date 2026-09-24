import { useLaravelReactI18n } from "laravel-react-i18n";
import { useEffect, useState } from "react";

import { cn } from "@/lib/utils";
import type { DocumentHeading } from "@/types/app/modules/marketing/document";

interface Props {
    headings: Array<DocumentHeading>;
}

/**
 * The "On this page" list: every section of the page being read,
 * linked by its anchor, with the section in view highlighted.
 */
export const TableOfContents = ({ headings }: Props) => {
    /**
     * Translate the provided string to the current locale.
     */
    const t = useLaravelReactI18n().t;

    /**
     * The anchor of the section the reader has scrolled to.
     */
    const [active, setActive] = useState<string | null>(headings[0]?.id ?? null);

    // The section in view is the last heading that has passed
    // under the sticky header, read again on every scroll.
    useEffect(() => {
        const update = () => {
            let current = headings[0]?.id ?? null;

            for (const heading of headings) {
                const element = document.getElementById(heading.id);

                if (element && element.getBoundingClientRect().top <= 120) {
                    current = heading.id;
                }
            }

            setActive(current);
        };

        update();
        window.addEventListener("scroll", update, { passive: true });

        return () => window.removeEventListener("scroll", update);
    }, [headings]);

    if (headings.length === 0) {
        return null;
    }

    return (
        <nav aria-label={t("On this page")} className="flex flex-col gap-3">
            <h2 className="text-foreground text-xs font-medium">{t("On this page")}</h2>
            <ul className="flex flex-col gap-2 border-l">
                {headings.map((heading) => (
                    <li key={heading.id}>
                        <a
                            aria-current={active === heading.id ? "location" : undefined}
                            className={cn(
                                "-ml-px block border-l py-0.5 text-sm transition-colors",
                                heading.level === 3 ? "pl-6" : "pl-3",
                                active === heading.id
                                    ? "border-foreground text-foreground"
                                    : "text-muted-foreground hover:text-foreground border-transparent",
                            )}
                            href={`#${heading.id}`}
                        >
                            {heading.text}
                        </a>
                    </li>
                ))}
            </ul>
        </nav>
    );
};
