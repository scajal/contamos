import { useLaravelReactI18n } from "laravel-react-i18n";
import { MoonIcon, SunIcon } from "lucide-react";

import { Button } from "@/components/ui/button";
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
 * server can render the right one on the next visit's first paint.
 */
export const ThemeSwitcher = ({ appearance, onChange, className }: ThemeSwitcherProps) => {
    /**
     * Translate the provided string to the current locale.
     */
    const t = useLaravelReactI18n().t;

    const next: Appearance = appearance === "dark" ? "light" : "dark";

    return (
        <Button
            variant="ghost"
            size="icon-sm"
            className={cn("rounded-full", className)}
            aria-label={t(next === "dark" ? "Switch to dark theme" : "Switch to light theme")}
            onClick={() => {
                document.cookie = `appearance=${next}; path=/; max-age=31536000; samesite=lax`;
                onChange(next);
            }}
        >
            {appearance === "dark" ? <SunIcon /> : <MoonIcon />}
        </Button>
    );
};
