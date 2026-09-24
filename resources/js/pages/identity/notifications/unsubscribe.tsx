import { Link } from "@inertiajs/react";
import { useLaravelReactI18n } from "laravel-react-i18n";
import { ArrowRightIcon, BellOffIcon } from "lucide-react";

import { Button } from "@/components/ui/button";
import { WizardLayout } from "@/layouts/wizard/layout";
import notifications from "@/routes/identity/teams/settings/notifications";
import { Team } from "@/types/app/modules/core/identity/models/team";

type PageProps = {
    isMember: boolean;
    team: Pick<Team, "name" | "slug">;
    type: string;
};

const Unsubscribe = ({ isMember, team, type }: PageProps) => {
    /**
     * Translate the provided string
     * to the current locale.
     */
    const t = useLaravelReactI18n().t;

    return (
        <div className="flex flex-col gap-8">
            {/** Header */}
            <header className="flex flex-col items-center gap-2 text-center">
                <BellOffIcon />
                <h1 className="font-heading mt-4 text-[1.75rem] leading-tight font-semibold tracking-tight">
                    {isMember ? t("You unsubscribed") : t("Nothing to unsubscribe from")}
                </h1>
                <p className="text-muted-foreground max-w-sm text-sm">
                    {isMember
                        ? t("You turned off :type for :team.", { team: team.name, type })
                        : t("You are no longer on :team, so it sends you no emails.", { team: team.name })}
                </p>
            </header>

            {/** Actions */}
            {isMember && (
                <div className="flex justify-center">
                    <Button
                        nativeButton={false}
                        render={<Link href={notifications.edit.url({ team: team.slug })} />}
                        variant="outline"
                    >
                        {t("Manage notifications")}
                        <ArrowRightIcon />
                    </Button>
                </div>
            )}
        </div>
    );
};

Unsubscribe.layout = (page: React.ReactNode) => <WizardLayout>{page}</WizardLayout>;

export default Unsubscribe;
