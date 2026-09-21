import { usePage } from "@inertiajs/react";
import { useLaravelReactI18n } from "laravel-react-i18n";
import { CheckIcon, MonitorIcon, MoonIcon, SunIcon } from "lucide-react";
import { useEffect, useState } from "react";

import { DropdownMenuItem } from "@/components/ui/dropdown-menu";

/**
 * The colour schemes the app offers. "system" follows the device, which is
 * what someone who never opened this menu gets.
 */
export type Appearance = "dark" | "light" | "system";

const MEDIA_QUERY = "(prefers-color-scheme: dark)";

/**
 * Whether the given scheme should paint the app dark.
 */
const prefersDark = (appearance: Appearance): boolean =>
    appearance === "dark" || (appearance === "system" && window.matchMedia(MEDIA_QUERY).matches);

/**
 * Paint the app in the given scheme.
 *
 * A scheme change rewrites colour, background, border and shadow on nearly
 * every element at once. Every transition on those properties would fire
 * together and the switch would smear, so they are suppressed for one frame.
 */
const apply = (appearance: Appearance): void => {
    const style = document.createElement("style");
    style.append(document.createTextNode("*,*::before,*::after{transition:none !important}"));
    document.head.append(style);

    document.documentElement.classList.toggle("dark", prefersDark(appearance));

    // Force a reflow, so the suppression covers the repaint.
    void window.getComputedStyle(document.body).opacity;

    requestAnimationFrame(() => style.remove());
};

/**
 * The colour scheme options for the user menu. The choice is written to an
 * unencrypted `appearance` cookie, so the server can render the right one on
 * the next visit's first paint.
 */
export const AppearanceMenu = () => {
    /**
     * Translate the provided string to the
     * current locale.
     */
    const t = useLaravelReactI18n().t;

    /**
     * The page's props.
     */
    const { appearance: initialAppearance } = usePage().props;

    /**
     * The chosen scheme, seeded from the server-read cookie.
     */
    const [appearance, setAppearance] = useState<Appearance>(initialAppearance);

    // Following the device means following it as it changes, which a
    // cookie read on the server cannot see.
    useEffect(() => {
        if (appearance !== "system") {
            return;
        }

        const media = window.matchMedia(MEDIA_QUERY);
        const onChange = () => apply("system");

        media.addEventListener("change", onChange);

        return () => media.removeEventListener("change", onChange);
    }, [appearance]);

    /**
     * Remember the choice and paint the app in it.
     */
    const choose = (next: Appearance) => {
        document.cookie = `appearance=${next}; path=/; max-age=31536000; samesite=lax`;

        setAppearance(next);
        apply(next);
    };

    const options: Array<{ icon: React.ReactNode; label: string; value: Appearance }> = [
        { icon: <SunIcon />, label: t("Light"), value: "light" },
        { icon: <MoonIcon />, label: t("Dark"), value: "dark" },
        { icon: <MonitorIcon />, label: t("System"), value: "system" },
    ];

    return options.map((option) => (
        <DropdownMenuItem closeOnClick={false} key={option.value} onClick={() => choose(option.value)}>
            {option.icon}
            <span>{option.label}</span>
            {appearance === option.value ? <CheckIcon className="ml-auto size-4" /> : null}
        </DropdownMenuItem>
    ));
};
