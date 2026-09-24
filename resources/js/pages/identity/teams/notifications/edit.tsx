import { router } from "@inertiajs/react";
import { useLaravelReactI18n } from "laravel-react-i18n";
import { useState } from "react";

import { Field, FieldContent, FieldDescription, FieldLabel } from "@/components/ui/field";
import { Switch } from "@/components/ui/switch";
import { ApplicationLayout } from "@/layouts/application/layout";
import notifications from "@/routes/identity/teams/settings/notifications";
import { NotificationType } from "@/types/app/modules/core/identity/enums/team/notification-type";
import { Team } from "@/types/app/modules/core/identity/models/team";

import { SettingsTabs } from "../_components/settings-tabs";

type NotificationSwitch = {
    description: string;
    enabled: boolean;
    label: string;
    value: NotificationType;
};

type PageProps = {
    notifications: Array<NotificationSwitch>;
    team: Team;
};

const Edit = ({ notifications: switches, team }: PageProps) => {
    /**
     * Translate the provided string
     * to the current locale.
     */
    const t = useLaravelReactI18n().t;

    /**
     * The switches as the member last set them, ahead of the
     * server so a switch flips the moment it is pressed.
     */
    const [enabled, setEnabled] = useState<Record<string, boolean>>(() =>
        Object.fromEntries(switches.map(({ enabled, value }) => [value, enabled])),
    );

    /**
     * Flip one switch and save all of them, putting the
     * previous state back when the server refuses.
     */
    const toggle = (type: NotificationType, checked: boolean) => {
        const previous = enabled;
        const next = { ...enabled, [type]: checked };

        setEnabled(next);

        router.patch(notifications.update.url({ team: team.slug }), next, {
            onError: () => setEnabled(previous),
            preserveScroll: true,
        });
    };

    return (
        <div className="flex flex-col gap-8">
            {/** Header */}
            <header className="flex flex-col gap-1">
                <h1 className="font-heading text-[1.75rem] leading-tight font-semibold tracking-tight">
                    {t("Configuration")}
                </h1>
                <p className="text-muted-foreground text-[15px] leading-relaxed">
                    {t("Choose the emails you receive about this team.")}
                </p>
            </header>

            {/** Tabs */}
            <SettingsTabs value="notifications" />

            {/** Switches */}
            <div className="flex flex-col divide-y rounded-xl border">
                {switches.map(({ description, label, value }) => (
                    <Field className="p-4" key={value} orientation="horizontal">
                        <FieldContent>
                            <FieldLabel htmlFor={value}>{label}</FieldLabel>
                            <FieldDescription>{description}</FieldDescription>
                        </FieldContent>
                        <Switch
                            checked={enabled[value] ?? true}
                            id={value}
                            onCheckedChange={(checked) => toggle(value, checked)}
                        />
                    </Field>
                ))}
            </div>
        </div>
    );
};

Edit.layout = (page: React.ReactNode) => <ApplicationLayout>{page}</ApplicationLayout>;

export default Edit;
