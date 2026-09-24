import { Link, usePage } from "@inertiajs/react";
import { useLaravelReactI18n } from "laravel-react-i18n";
import { BookOpenIcon, LifeBuoyIcon, LogOutIcon, ShieldCheckIcon, UserIcon } from "lucide-react";
import { Fragment } from "react/jsx-runtime";

import { AppearanceMenu } from "@/components/appearance-menu";
import { LanguageMenu } from "@/components/language-menu";
import {
    DropdownMenuGroup,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
} from "@/components/ui/dropdown-menu";
import { logout } from "@/routes";
import docs from "@/routes/docs";
import administration from "@/routes/filament/administration";
import account from "@/routes/identity/users/account";

/**
 * The items of the signed-in user's menu: administration for those
 * who may enter it, the account, the docs, support, the colour scheme, the
 * language and the way out. Layouts wrap them in their own trigger and content, so the
 * same menu opens from the sidebar footer and from the wizard header.
 */
export const UserMenuItems = () => {
    /**
     * Translate the provided string to the
     * current locale.
     */
    const t = useLaravelReactI18n().t;

    /**
     * The page's props.
     */
    const { auth, locale } = usePage().props;

    return (
        <Fragment>
            {auth.canAccessAdministration && (
                <Fragment>
                    <DropdownMenuGroup>
                        <DropdownMenuItem render={<a href={administration.pages.dashboard.url()} target="_blank" />}>
                            <ShieldCheckIcon />
                            <span>{t("Administration")}</span>
                        </DropdownMenuItem>
                    </DropdownMenuGroup>
                    <DropdownMenuSeparator />
                </Fragment>
            )}
            <DropdownMenuGroup>
                <DropdownMenuItem render={<Link href={account.index.url()} />}>
                    <UserIcon />
                    <span>{t("Account")}</span>
                </DropdownMenuItem>
                <DropdownMenuItem render={<a href={docs.index.url({ locale })} rel="noopener" target="_blank" />}>
                    <BookOpenIcon />
                    <span>{t("Help")}</span>
                </DropdownMenuItem>
                <DropdownMenuItem render={<a href={"mailto:hello@contamos.xyz"} />}>
                    <LifeBuoyIcon />
                    <span>{t("Support")}</span>
                </DropdownMenuItem>
            </DropdownMenuGroup>
            <DropdownMenuSeparator />
            <DropdownMenuGroup>
                <DropdownMenuLabel>{t("Theme")}</DropdownMenuLabel>
                <AppearanceMenu />
            </DropdownMenuGroup>
            <DropdownMenuSeparator />
            <DropdownMenuGroup>
                <DropdownMenuLabel>{t("Language")}</DropdownMenuLabel>
                <LanguageMenu />
            </DropdownMenuGroup>
            <DropdownMenuSeparator />
            <DropdownMenuGroup>
                <DropdownMenuItem render={<a href={logout.url()} />}>
                    <LogOutIcon />
                    <span>{t("Log Out")}</span>
                </DropdownMenuItem>
            </DropdownMenuGroup>
        </Fragment>
    );
};
