import { usePage } from "@inertiajs/react";
import { ModalLink } from "@inertiaui/modal-react";
import { useLaravelReactI18n } from "laravel-react-i18n";
import { CrownIcon, LinkIcon, PencilIcon, TrashIcon, TypeIcon } from "lucide-react";
import { Fragment } from "react/jsx-runtime";

import { Button } from "@/components/ui/button";
import { Item, ItemContent, ItemDescription, ItemGroup, ItemMedia, ItemTitle } from "@/components/ui/item";
import { usePermissions } from "@/hooks/use-permissions";
import { ApplicationLayout } from "@/layouts/application/layout";
import { PERMISSIONS } from "@/lib/permissions";
import settings from "@/routes/identity/teams/settings";
import { Team } from "@/types/app/modules/core/identity/models/team";
import { User } from "@/types/app/modules/core/identity/models/user";

type PageProps = {
    owner: User | null;
    team: Team;
};

const Index = () => {
    /**
     * What the user may do on the team.
     */
    const { can } = usePermissions();

    return (
        <div className="flex flex-col gap-8">
            {/** Header */}
            <Header />

            {/** Content */}
            <div className="flex flex-col gap-8">
                {/** Details */}
                <Details />
                {/** Danger */}
                {can(PERMISSIONS.DeleteTeam) && <Danger />}
            </div>
        </div>
    );
};

const Header = () => {
    /**
     * Translate the provided string
     * to the current locale.
     */
    const t = useLaravelReactI18n().t;

    return (
        <header className="flex flex-col gap-1">
            <h1 className="text-2xl font-medium">{t("Configuration")}</h1>
            <p className="text-muted-foreground text-sm">{t("Manage your team's details.")}</p>
        </header>
    );
};

const Details = () => {
    /**
     * Translate the provided string
     * to the current locale.
     */
    const t = useLaravelReactI18n().t;

    /**
     * The page's props.
     */
    const { owner, team } = usePage<PageProps>().props;

    /**
     * What the user may do on the team.
     */
    const { can } = usePermissions();

    return (
        <Fragment>
            <div className="flex w-full flex-wrap items-end justify-between gap-4">
                <header className="flex w-full justify-between gap-1">
                    <div className="flex flex-col gap-1">
                        <h1 className="font-medium">{t("Details")}</h1>
                        <p className="text-muted-foreground text-sm">{t("The name and address of your team.")}</p>
                    </div>

                    {/** Actions */}
                    {can(PERMISSIONS.UpdateTeam) && (
                        <Button
                            nativeButton={false}
                            render={
                                <ModalLink href={settings.edit.url({ team: team.slug })}>
                                    <PencilIcon data-icon="inline-start" />
                                    {t("Edit")}
                                </ModalLink>
                            }
                            size="sm"
                            variant="outline"
                        />
                    )}
                </header>
            </div>
            <div className="overflow-hidden rounded-sm border">
                <ItemGroup className="gap-0 has-data-[size=sm]:gap-0">
                    <Item className="rounded-none border-0 border-b last:border-b-0" size="sm" variant="outline">
                        <ItemMedia variant="icon">
                            <TypeIcon />
                        </ItemMedia>
                        <ItemContent>
                            <ItemDescription>{t("Name")}</ItemDescription>
                            <ItemTitle>{team.name}</ItemTitle>
                        </ItemContent>
                    </Item>
                    <Item className="rounded-none border-0 border-b last:border-b-0" size="sm" variant="outline">
                        <ItemMedia variant="icon">
                            <LinkIcon />
                        </ItemMedia>
                        <ItemContent>
                            <ItemDescription>{t("Handle")}</ItemDescription>
                            <ItemTitle>{team.slug}</ItemTitle>
                        </ItemContent>
                    </Item>
                    <Item className="rounded-none border-0 border-b last:border-b-0" size="sm" variant="outline">
                        <ItemMedia variant="icon">
                            <CrownIcon />
                        </ItemMedia>
                        <ItemContent>
                            <ItemDescription>{t("Owner")}</ItemDescription>
                            <ItemTitle>{owner?.name ?? "—"}</ItemTitle>
                        </ItemContent>
                    </Item>
                </ItemGroup>
            </div>
        </Fragment>
    );
};

const Danger = () => {
    /**
     * Translate the provided string
     * to the current locale.
     */
    const t = useLaravelReactI18n().t;

    /**
     * The page's props.
     */
    const { team } = usePage<PageProps>().props;

    return (
        <Fragment>
            <header className="flex flex-col gap-1">
                <h1 className="text-destructive font-medium">{t("Danger")}</h1>
                <p className="text-muted-foreground text-sm">{t("Destructive actions that cannot be undone.")}</p>
            </header>
            <div className="border-destructive/40 flex flex-wrap items-center justify-between gap-4 rounded-sm border p-4">
                <div className="flex max-w-lg flex-col gap-1">
                    <p className="font-medium">{t("Delete team")}</p>
                    <p className="text-muted-foreground text-sm">
                        {t(
                            "Deleting the team permanently removes its accounts, transactions, tags, members, and invitations.",
                        )}
                    </p>
                </div>
                <Button
                    nativeButton={false}
                    render={
                        <ModalLink href={settings.delete.url({ team: team.slug })}>
                            <TrashIcon data-icon="inline-start" />
                            {t("Delete team")}
                        </ModalLink>
                    }
                    variant="destructive"
                />
            </div>
        </Fragment>
    );
};

Index.layout = (page: React.ReactNode) => <ApplicationLayout>{page}</ApplicationLayout>;

export default Index;
