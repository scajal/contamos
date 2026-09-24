import { usePage } from "@inertiajs/react";
import { CheckIcon } from "lucide-react";

import { DropdownMenuItem } from "@/components/ui/dropdown-menu";

/**
 * The languages the app is translated into, each named in itself so
 * someone who cannot read the current one still finds their own.
 */
const LANGUAGES = [
    { label: "English", locale: "en" },
    { label: "Español", locale: "es" },
] as const;

/**
 * The language options for the user menu. The choice is written to the
 * unencrypted locale cookie the server resolves the locale from, then the
 * page reloads so `<html lang>`, the server render and the translations
 * all switch in one request.
 */
export const LanguageMenu = () => {
    /**
     * The page's props.
     */
    const { locale: current } = usePage().props;

    /**
     * Remember the choice and reload the page in it.
     */
    const choose = (locale: string) => {
        if (locale === current) {
            return;
        }

        document.cookie = `filament_language_switch_locale=${locale}; path=/; max-age=31536000; samesite=lax`;

        window.location.reload();
    };

    return LANGUAGES.map((language) => (
        <DropdownMenuItem
            closeOnClick={false}
            key={language.locale}
            lang={language.locale}
            onClick={() => choose(language.locale)}
        >
            <span>{language.label}</span>
            {current === language.locale ? <CheckIcon className="ml-auto size-4" /> : null}
        </DropdownMenuItem>
    ));
};
