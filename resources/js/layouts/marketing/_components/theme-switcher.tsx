import { useLaravelReactI18n } from "laravel-react-i18n";
import { MoonIcon, SunIcon } from "lucide-react";

import { Button } from "@/components/ui/button";
import { usePreference } from "@/hooks/use-preference";
import { cn } from "@/lib/utils";

export type Appearance = "light" | "dark";

interface ThemeSwitcherProps {
    appearance: Appearance;
    onChange: (appearance: Appearance) => void;
    className?: string;
}

/**
 * Toggles the marketing site between its dark and light worlds. The
 * choice is written to an unencrypted `appearance` cookie so the
 * server can render the right one on the next visit's first paint,
 * and stored on the account when someone is signed in.
 */
export const ThemeSwitcher = ({ appearance, onChange, className }: ThemeSwitcherProps) => {
    /**
     * Translate the provided string to the current locale.
     */
    const t = useLaravelReactI18n().t;

    /**
     * Remember the scheme in the cookie and on the account.
     */
    const remember = usePreference("appearance");

    const next: Appearance = appearance === "dark" ? "light" : "dark";

    return (
        <Button
            variant="ghost"
            size="icon-sm"
            className={cn("rounded-full", className)}
            aria-label={t(next === "dark" ? "Switch to dark theme" : "Switch to light theme")}
            onClick={() => {
                void remember(next);
                onChange(next);
            }}
        >
            {appearance === "dark" ? <SunIcon /> : <MoonIcon />}
        </Button>
    );
};
