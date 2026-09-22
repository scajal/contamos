import { ModalLink } from "@inertiaui/modal-react";
import { useLaravelReactI18n } from "laravel-react-i18n";
import { LockIcon } from "lucide-react";
import { ReactNode } from "react";

import { Button } from "@/components/ui/button";
import { Empty, EmptyContent, EmptyDescription, EmptyHeader, EmptyMedia, EmptyTitle } from "@/components/ui/empty";
import subscriptions from "@/routes/billing/teams/subscriptions";

type Props = {
    children?: ReactNode;
    description: string;
    feature: string;
    team: string;
    title: string;
};

/**
 * What a team on a plan without the feature sees in place of its
 * controls. Whatever the team already recorded is kept, so it is
 * still rendered underneath, blurred and inert, with the panel
 * sitting over it: the records are visibly there without being
 * readable or reachable until the team upgrades. With nothing
 * recorded yet the panel stands on its own.
 */
export const FeatureLock = ({ children, description, feature, team, title }: Props) => {
    /**
     * Translate the provided string
     * to the current locale.
     */
    const t = useLaravelReactI18n().t;

    const panel = (
        <Empty>
            <EmptyHeader>
                <EmptyMedia variant="icon">
                    <LockIcon />
                </EmptyMedia>
                <EmptyTitle>{title}</EmptyTitle>
                <EmptyDescription>{description}</EmptyDescription>
            </EmptyHeader>
            <EmptyContent>
                <Button
                    nativeButton={false}
                    render={
                        <ModalLink href={subscriptions.upgrade.url({ team }, { query: { feature } })}>
                            {t("Upgrade to Pro")}
                        </ModalLink>
                    }
                />
            </EmptyContent>
        </Empty>
    );

    if (!children) {
        return <div className="overflow-hidden rounded-xl border">{panel}</div>;
    }

    return (
        <div className="relative isolate overflow-hidden rounded-xl border">
            {/** The records sit behind the panel, out of the flow so the panel sets the height. */}
            <div
                aria-hidden
                className="pointer-events-none absolute inset-0 overflow-hidden blur-[5px] select-none"
                inert
            >
                {children}
            </div>

            {/** The panel is what the box is sized by, on a wash that keeps it readable. */}
            <div className="bg-background/75 relative flex items-center justify-center p-2 backdrop-blur-[2px]">
                {panel}
            </div>
        </div>
    );
};
