import { Link, usePage } from "@inertiajs/react";
import { useLaravelReactI18n } from "laravel-react-i18n";

import { Tabs, TabsList, TabsTrigger } from "@/components/ui/tabs";
import { usePermissions } from "@/hooks/use-permissions";
import { PERMISSIONS } from "@/lib/permissions";
import settings from "@/routes/identity/teams/settings";

type Props = {
    value: "details" | "notifications";
};

/**
 * The tabs across the team settings pages. Each tab is a
 * page of its own, so a tab is a link rather than a panel.
 */
export const SettingsTabs = ({ value }: Props) => {
    /**
     * Translate the provided string
     * to the current locale.
     */
    const t = useLaravelReactI18n().t;

    /**
     * The team the settings belong to.
     */
    const team = usePage().props.user?.current_team?.slug ?? "";

    /**
     * What the user may do on the team.
     */
    const { can } = usePermissions();

    // A single tab is no choice at all, so the tabs only show
    // when there is somewhere else to go.
    if (!can(PERMISSIONS.ManageNotifications)) {
        return null;
    }

    return (
        <Tabs value={value}>
            <TabsList>
                <TabsTrigger nativeButton={false} render={<Link href={settings.index.url({ team })} />} value="details">
                    {t("Details")}
                </TabsTrigger>
                <TabsTrigger
                    nativeButton={false}
                    render={<Link href={settings.notifications.edit.url({ team })} />}
                    value="notifications"
                >
                    {t("Notifications")}
                </TabsTrigger>
            </TabsList>
        </Tabs>
    );
};
