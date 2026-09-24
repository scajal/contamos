import { useHttp, usePage } from "@inertiajs/react";

import preferences from "@/routes/identity/users/preferences";

/**
 * The choices a member makes from the menus, as the server names them.
 */
export type PreferenceKey = "appearance" | "locale";

/**
 * The unencrypted cookie each choice is mirrored to, which the server and the
 * marketing pages read on every request.
 */
const COOKIES: Record<PreferenceKey, string> = {
    appearance: "appearance",
    locale: "filament_language_switch_locale",
};

/**
 * Remember a choice from the menus. The cookie is written at once, so this
 * browser follows it straight away; a signed-in member's choice is also
 * stored on their account, so it follows them to every device. Signed-out
 * visitors keep it in the cookie alone.
 */
export function usePreference(key: PreferenceKey): (value: string) => Promise<void> {
    /**
     * The signed-in member, if any.
     */
    const { user } = usePage().props;

    /**
     * The request that stores the choice on the account.
     */
    const http = useHttp({ key, value: "" });

    return async (value: string): Promise<void> => {
        document.cookie = `${COOKIES[key]}=${value}; path=/; max-age=31536000; samesite=lax`;

        if (user === null) {
            return;
        }

        // A choice the server could not store still holds in this browser.
        http.transform(() => ({ key, value }));

        await http.patch(preferences.update.url()).catch(() => undefined);
    };
}
