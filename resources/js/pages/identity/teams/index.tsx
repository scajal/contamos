import { usePage } from "@inertiajs/react";
import { ModalLink } from "@inertiaui/modal-react";
import { useLaravelReactI18n } from "laravel-react-i18n";
import { LinkIcon, PencilIcon, TypeIcon } from "lucide-react";
import { Fragment } from "react/jsx-runtime";

import { Button } from "@/components/ui/button";
import { Item, ItemActions, ItemContent, ItemDescription, ItemGroup, ItemMedia, ItemTitle } from "@/components/ui/item";
import { ApplicationLayout } from "@/layouts/application/layout";
import settings from "@/routes/identity/teams/settings";
import { Team } from "@/types/app/modules/core/identity/models/team";

type PageProps = {
    team: Team;
};

const Index = () => {
    return (
        <div className="flex flex-col gap-8">
            {/** Header */}
            <Header />

            {/** Content */}
            <div className="flex flex-col gap-8">
                {/** Details */}
                <Details />
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
    const { team } = usePage<PageProps>().props;

    return (
        <Fragment>
            <div className="flex flex-wrap items-end justify-between gap-4">
                <header className="flex flex-col gap-1">
                    <h1 className="font-medium">{t("Details")}</h1>
                    <p className="text-muted-foreground text-sm">{t("The name and address of your team.")}</p>
                </header>
            </div>
            <div className="overflow-hidden rounded-sm border">
                <ItemGroup className="gap-0">
                    <Item className="rounded-none border-0 border-b last:border-b-0" size="sm" variant="outline">
                        <ItemMedia variant="icon">
                            <TypeIcon />
                        </ItemMedia>
                        <ItemContent>
                            <ItemDescription>{t("Name")}</ItemDescription>
                            <ItemTitle>{team.name}</ItemTitle>
                        </ItemContent>
                        <ItemActions>
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
                        </ItemActions>
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
                </ItemGroup>
            </div>
        </Fragment>
    );
};

Index.layout = (page: React.ReactNode) => <ApplicationLayout>{page}</ApplicationLayout>;

export default Index;
