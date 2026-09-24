import { Deferred, Link, usePage } from "@inertiajs/react";
import { ModalLink } from "@inertiaui/modal-react";
import { useLaravelReactI18n } from "laravel-react-i18n";
import { ArrowUpRightIcon, BotIcon, CircleAlertIcon, PlugZapIcon, SparklesIcon, UnplugIcon } from "lucide-react";
import { useState } from "react";
import { Fragment } from "react/jsx-runtime";

import { ConnectionSteps, ExamplePrompts, ServerAddress } from "@/components/assistant-guide";
import { ConfirmAction } from "@/components/confirm-action";
import { Button } from "@/components/ui/button";
import { Empty, EmptyDescription, EmptyHeader, EmptyMedia, EmptyTitle } from "@/components/ui/empty";
import { Item, ItemActions, ItemContent, ItemDescription, ItemGroup, ItemMedia, ItemTitle } from "@/components/ui/item";
import { Skeleton } from "@/components/ui/skeleton";
import { ApplicationLayout } from "@/layouts/application/layout";
import subscriptions from "@/routes/billing/teams/subscriptions";
import assistants from "@/routes/identity/users/assistants";
import { ConnectedApp, ConnectedApps } from "@/types/app/modules/core/identity/connected-app";
import type { AssistantConnectionGuide, AssistantExamplePrompt } from "@/types/app/modules/mcp/assistant-guide";

type PageProps = {
    connected_apps?: ConnectedApps;
    guideUrl: string;
    guides: Array<AssistantConnectionGuide>;
    is_locked: boolean;
    prompts: Array<AssistantExamplePrompt>;
    server_url: string;
};

const Index = () => {
    return (
        <div className="flex flex-col gap-8">
            {/** Header */}
            <Header />

            {/** Content */}
            <div className="flex flex-col gap-8">
                {/** Availability */}
                <Availability />
                {/** Server */}
                <Server />
                {/** Setup */}
                <Setup />
                {/** Prompts */}
                <Prompts />
                {/** Connected */}
                <Connected />
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

    /**
     * The page's props.
     */
    const { guideUrl } = usePage<PageProps>().props;

    return (
        <header className="flex flex-col gap-1">
            <h1 className="font-heading text-[1.75rem] leading-tight font-semibold tracking-tight">
                {t("AI assistants")}
            </h1>
            <p className="text-muted-foreground text-[15px] leading-relaxed">
                {t("Let Claude or ChatGPT read and record your finances, acting as you on your teams.")}{" "}
                <a
                    className="text-foreground inline-flex items-center gap-1 font-medium underline-offset-4 hover:underline"
                    href={guideUrl}
                    rel="noopener"
                    target="_blank"
                >
                    {t("Read the guide")}
                    <ArrowUpRightIcon className="size-3.5" />
                </a>
            </p>
        </header>
    );
};

/**
 * Where a connected assistant works: on every team, within the
 * limits of its plan, so the page says so up front. It offers
 * the upgrade only when the current team's plan leaves
 * assistants out.
 */
const Availability = () => {
    /**
     * Translate the provided string
     * to the current locale.
     */
    const t = useLaravelReactI18n().t;

    /**
     * The page's props.
     */
    const { is_locked, user } = usePage<PageProps>().props;

    /**
     * The team the user is working in, if any.
     */
    const team = user?.current_team;

    return (
        <Item variant="outline">
            <ItemMedia variant="icon">
                <SparklesIcon />
            </ItemMedia>
            <ItemContent>
                <ItemTitle>{t("Works on every team, within its plan")}</ItemTitle>
                <ItemDescription className="line-clamp-none">
                    {t(
                        "You can connect an assistant at any time. It reaches every team you belong to with the same limits you have in the app: on Free it sees the same history, and budgets, recurring transactions and the activity log need Pro.",
                    )}
                    {is_locked && team
                        ? ` ${t(":team's plan does not include AI assistants.", { team: team.name })}`
                        : null}
                </ItemDescription>
            </ItemContent>
            {is_locked && team ? (
                <ItemActions>
                    <Button
                        nativeButton={false}
                        render={
                            <ModalLink
                                href={subscriptions.upgrade.url({ team: team.slug }, { query: { feature: "mcp:use" } })}
                            >
                                {t("Upgrade to Pro")}
                            </ModalLink>
                        }
                    />
                </ItemActions>
            ) : null}
        </Item>
    );
};

const Server = () => {
    /**
     * Translate the provided string
     * to the current locale.
     */
    const t = useLaravelReactI18n().t;

    /**
     * The page's props.
     */
    const { server_url } = usePage<PageProps>().props;

    return (
        <Fragment>
            <header className="flex flex-col gap-1">
                <h2 className="font-heading font-medium">{t("Server address")}</h2>
                <p className="text-muted-foreground text-[15px] leading-relaxed">
                    {t("Paste this address into your assistant to connect it.")}
                </p>
            </header>
            <ServerAddress url={server_url} />
        </Fragment>
    );
};

const Setup = () => {
    /**
     * Translate the provided string
     * to the current locale.
     */
    const t = useLaravelReactI18n().t;

    /**
     * The page's props.
     */
    const { guides } = usePage<PageProps>().props;

    return (
        <Fragment>
            <header className="flex flex-col gap-1">
                <h2 className="font-heading font-medium">{t("How to connect")}</h2>
                <p className="text-muted-foreground text-[15px] leading-relaxed">
                    {t("You sign in once; the assistant then acts as you, with your role on each team.")}
                </p>
            </header>
            <ConnectionSteps guides={guides} />
        </Fragment>
    );
};

/**
 * What to ask a connected assistant first, the same examples
 * the docs show.
 */
const Prompts = () => {
    /**
     * Translate the provided string
     * to the current locale.
     */
    const t = useLaravelReactI18n().t;

    /**
     * The page's props.
     */
    const { prompts } = usePage<PageProps>().props;

    return (
        <Fragment>
            <header className="flex flex-col gap-1">
                <h2 className="font-heading font-medium">{t("Example prompts")}</h2>
                <p className="text-muted-foreground text-[15px] leading-relaxed">
                    {t("Copy one into your assistant once it is connected.")}
                </p>
            </header>
            <ExamplePrompts prompts={prompts} />
        </Fragment>
    );
};

const Connected = () => {
    /**
     * Translate the provided string
     * to the current locale.
     */
    const t = useLaravelReactI18n().t;

    /**
     * The page's props.
     */
    const { connected_apps } = usePage<PageProps>().props;

    return (
        <Fragment>
            <header className="flex flex-col gap-1">
                <h2 className="font-heading font-medium">{t("Connected assistants")}</h2>
                <p className="text-muted-foreground text-[15px] leading-relaxed">
                    {t("Disconnecting one stops it at once, on every team.")}
                </p>
            </header>
            <Deferred data="connected_apps" fallback={<SkeletonRows />}>
                {connected_apps?.failed ? (
                    <div className="rounded-xl border">
                        <Empty>
                            <EmptyHeader>
                                <EmptyMedia variant="icon">
                                    <CircleAlertIcon />
                                </EmptyMedia>
                                <EmptyTitle>{t("Connected assistants could not be loaded")}</EmptyTitle>
                                <EmptyDescription>{t("Try again in a moment.")}</EmptyDescription>
                            </EmptyHeader>
                        </Empty>
                    </div>
                ) : !connected_apps?.items.length ? (
                    <div className="rounded-xl border">
                        <Empty>
                            <EmptyHeader>
                                <EmptyMedia variant="icon">
                                    <PlugZapIcon />
                                </EmptyMedia>
                                <EmptyTitle>{t("No assistants connected yet")}</EmptyTitle>
                                <EmptyDescription>
                                    {t("Once you connect one, it shows up here so you can disconnect it.")}
                                </EmptyDescription>
                            </EmptyHeader>
                        </Empty>
                    </div>
                ) : (
                    <div className="overflow-hidden rounded-xl border">
                        <ItemGroup className="gap-0 has-data-[size=sm]:gap-0">
                            {connected_apps.items.map((app) => (
                                <ConnectedRow app={app} key={app.id} />
                            ))}
                        </ItemGroup>
                    </div>
                )}
            </Deferred>
        </Fragment>
    );
};

const ConnectedRow = ({ app }: { app: ConnectedApp }) => {
    /**
     * Translate the provided string
     * to the current locale.
     */
    const t = useLaravelReactI18n().t;

    /**
     * Whether the disconnect confirmation is open.
     */
    const [confirming, setConfirming] = useState(false);

    return (
        <Item className="rounded-none border-0 border-b last:border-b-0" size="sm" variant="outline">
            <ItemMedia variant="icon">
                <BotIcon />
            </ItemMedia>
            <ItemContent>
                <ItemTitle>{app.name}</ItemTitle>
            </ItemContent>
            <ItemActions>
                <Button onClick={() => setConfirming(true)} size="sm" variant="outline">
                    <UnplugIcon data-icon="inline-start" />
                    {t("Disconnect")}
                </Button>
                <ConfirmAction
                    action={
                        <Link href={assistants.destroy.url({ application: app.id })} method="delete" preserveScroll />
                    }
                    confirmLabel={t("Disconnect")}
                    description={t("It loses access to your account at once. You can connect it again later.")}
                    onOpenChange={setConfirming}
                    open={confirming}
                    title={t("Disconnect :name?", { name: app.name })}
                />
            </ItemActions>
        </Item>
    );
};

const SkeletonRows = () => {
    return (
        <div className="overflow-hidden rounded-xl border">
            {[0, 1].map((row) => (
                <div className="flex items-center gap-3 border-b px-3 py-2.5 last:border-b-0" key={row}>
                    <Skeleton className="size-8 rounded-xl" />
                    <div className="flex flex-1 flex-col gap-2">
                        <Skeleton className="h-4 w-40" />
                        <Skeleton className="h-3 w-28" />
                    </div>
                    <Skeleton className="h-8 w-24" />
                </div>
            ))}
        </div>
    );
};

Index.layout = (page: React.ReactNode) => <ApplicationLayout>{page}</ApplicationLayout>;

export default Index;
