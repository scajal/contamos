import { Link, usePage } from "@inertiajs/react";
import { ModalLink } from "@inertiaui/modal-react";
import { useLaravelReactI18n } from "laravel-react-i18n";
import { AtSignIcon, BotIcon, ChevronRightIcon, TrashIcon, TypeIcon } from "lucide-react";
import { Fragment } from "react/jsx-runtime";

import { Avatar, AvatarFallback, AvatarImage } from "@/components/ui/avatar";
import { Button } from "@/components/ui/button";
import { Item, ItemActions, ItemContent, ItemDescription, ItemGroup, ItemMedia, ItemTitle } from "@/components/ui/item";
import { ApplicationLayout } from "@/layouts/application/layout";
import { initials } from "@/lib/utils";
import account from "@/routes/identity/users/account";
import assistants from "@/routes/identity/users/assistants";

const Index = () => {
    return (
        <div className="flex flex-col gap-8">
            {/** Header */}
            <Header />

            {/** Content */}
            <div className="flex flex-col gap-8">
                {/** Details */}
                <Details />
                {/** Assistants */}
                <Assistants />
                {/** Danger */}
                <Danger />
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
            <h1 className="font-heading text-[1.75rem] leading-tight font-semibold tracking-tight">{t("Account")}</h1>
            <p className="text-muted-foreground text-[15px] leading-relaxed">
                {t("Your own account, across every team.")}
            </p>
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
     * The authenticated user, shared with every page.
     */
    const { user } = usePage().props;

    return (
        <Fragment>
            <header className="flex w-full justify-between gap-1">
                <div className="flex flex-col gap-1">
                    <h2 className="font-heading font-medium">{t("Details")}</h2>
                    <p className="text-muted-foreground text-[15px] leading-relaxed">
                        {t("How you sign in and how others see you.")}
                    </p>
                </div>

                {/** The identity comes from the sign-in provider,
                 * so it is shown as it stands. */}
                <Avatar className="size-10">
                    <AvatarImage src={user?.avatar ?? undefined} />
                    <AvatarFallback>{initials(user?.name ?? "")}</AvatarFallback>
                </Avatar>
            </header>
            <div className="overflow-hidden rounded-xl border">
                <ItemGroup className="gap-0 has-data-[size=sm]:gap-0">
                    <Item className="rounded-none border-0 border-b last:border-b-0" size="sm" variant="outline">
                        <ItemMedia variant="icon">
                            <TypeIcon />
                        </ItemMedia>
                        <ItemContent>
                            <ItemDescription>{t("Name")}</ItemDescription>
                            <ItemTitle>{user?.name}</ItemTitle>
                        </ItemContent>
                    </Item>
                    <Item className="rounded-none border-0 border-b last:border-b-0" size="sm" variant="outline">
                        <ItemMedia variant="icon">
                            <AtSignIcon />
                        </ItemMedia>
                        <ItemContent>
                            <ItemDescription>{t("Email")}</ItemDescription>
                            <ItemTitle>{user?.email}</ItemTitle>
                        </ItemContent>
                    </Item>
                </ItemGroup>
            </div>
        </Fragment>
    );
};

const Assistants = () => {
    /**
     * Translate the provided string
     * to the current locale.
     */
    const t = useLaravelReactI18n().t;

    return (
        <Fragment>
            <header className="flex flex-col gap-1">
                <h2 className="font-heading font-medium">{t("AI assistants")}</h2>
                <p className="text-muted-foreground text-[15px] leading-relaxed">
                    {t("Assistants you connected act as you on every team.")}
                </p>
            </header>
            <div className="overflow-hidden rounded-xl border">
                <Item
                    className="rounded-none border-0"
                    render={<Link href={assistants.index.url()} />}
                    size="sm"
                    variant="outline"
                >
                    <ItemMedia variant="icon">
                        <BotIcon />
                    </ItemMedia>
                    <ItemContent>
                        <ItemTitle>{t("Manage AI assistants")}</ItemTitle>
                        <ItemDescription>{t("Connect Claude or ChatGPT, or disconnect one.")}</ItemDescription>
                    </ItemContent>
                    <ItemActions>
                        <ChevronRightIcon className="text-muted-foreground size-4" />
                    </ItemActions>
                </Item>
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

    return (
        <Fragment>
            <header className="flex flex-col gap-1">
                <h2 className="font-heading text-destructive font-medium">{t("Danger")}</h2>
                <p className="text-muted-foreground text-[15px] leading-relaxed">
                    {t("Destructive actions that cannot be undone.")}
                </p>
            </header>
            <div className="border-destructive/40 flex flex-wrap items-center justify-between gap-4 rounded-xl border p-4">
                <div className="flex max-w-lg flex-col gap-1">
                    <p className="font-medium">{t("Delete account")}</p>
                    <p className="text-muted-foreground text-[15px] leading-relaxed">
                        {t(
                            "Deleting your account permanently removes it, along with every team where you are the only member.",
                        )}
                    </p>
                </div>
                <Button
                    nativeButton={false}
                    render={
                        <ModalLink href={account.delete.url()}>
                            <TrashIcon data-icon="inline-start" />
                            {t("Delete account")}
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
