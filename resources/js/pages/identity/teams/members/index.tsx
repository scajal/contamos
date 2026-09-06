import { Deferred, Link, usePage } from "@inertiajs/react";
import { format } from "date-fns";
import { useLaravelReactI18n } from "laravel-react-i18n";
import {
    ChevronLeftIcon,
    ChevronRightIcon,
    CircleDashedIcon,
    LogOutIcon,
    MoreHorizontalIcon,
    SearchIcon,
    SearchXIcon,
    TrashIcon,
    XIcon,
} from "lucide-react";
import { useState } from "react";

import { ConfirmAction } from "@/components/confirm-action";
import { Avatar, AvatarFallback, AvatarImage } from "@/components/ui/avatar";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuGroup,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from "@/components/ui/dropdown-menu";
import { Empty, EmptyDescription, EmptyHeader, EmptyMedia, EmptyTitle } from "@/components/ui/empty";
import { Field, FieldLabel } from "@/components/ui/field";
import { InputGroup, InputGroupAddon, InputGroupInput } from "@/components/ui/input-group";
import { Item, ItemActions, ItemContent, ItemDescription, ItemGroup, ItemMedia, ItemTitle } from "@/components/ui/item";
import { Pagination as PaginationComponent, PaginationContent, PaginationItem } from "@/components/ui/pagination";
import { Skeleton } from "@/components/ui/skeleton";
import { useFilters } from "@/hooks/use-filters";
import { ApplicationLayout } from "@/layouts/application/layout";
import routes from "@/routes/identity/teams/members";
import { User } from "@/types/app/modules/core/identity/models/user";
import { LengthAwarePaginator } from "@/types/illuminate/pagination/length-aware-paginator";

type PageFilters = {
    search: string | null;
};

type PageProps = {
    filters: Partial<PageFilters>;
    members: LengthAwarePaginator<User>;
};

/**
 * The initials of the given name, used as the
 * avatar fallback.
 */
const initials = (name: string) =>
    name
        .split(" ")
        .filter(Boolean)
        .slice(0, 2)
        .map((part) => part[0]?.toUpperCase() ?? "")
        .join("");

const Index = () => {
    return (
        <div className="flex flex-col gap-8">
            {/** Header */}
            <Header />

            {/** Content */}
            <div className="flex flex-col gap-4">
                {/** Filters */}
                <Filters />

                {/** Members */}
                <Listing />
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
            <h1 className="text-2xl font-medium">{t("Members")}</h1>
            <p className="text-muted-foreground text-sm">
                {t("People who share this team's accounts and transactions.")}
            </p>
        </header>
    );
};

const Filters = () => {
    /**
     * Translate the provided string
     * to the current locale.
     */
    const t = useLaravelReactI18n().t;

    /**
     * The page's props.
     */
    const { filters: initial, user } = usePage<PageProps>().props;

    /**
     * The filters synced with the URL.
     */
    const { clear, filters, isFiltered, setFilters } = useFilters<PageFilters>({
        initial: {
            search: initial?.search ?? null,
        },
        url: (query) => routes.index.url({ team: user?.current_team?.slug ?? "" }, { query }),
        only: ["members", "filters"],
    });

    return (
        <div className="flex items-center gap-2">
            {/** Search */}
            <Field className="flex-1">
                <FieldLabel className="sr-only" htmlFor="members-search">
                    {t("Search")}
                </FieldLabel>
                <InputGroup>
                    <InputGroupAddon>
                        <SearchIcon />
                    </InputGroupAddon>
                    <InputGroupInput
                        id="members-search"
                        onChange={(event) => setFilters({ search: event.target.value }, { debounced: true })}
                        placeholder={t("Search by email or name")}
                        value={filters.search ?? ""}
                    />
                </InputGroup>
            </Field>

            {/** Clear */}
            <Button disabled={!isFiltered} onClick={clear} size="icon" variant="outline">
                <XIcon />
                <span className="sr-only">{t("Clear filters")}</span>
            </Button>
        </div>
    );
};

const MemberActions = ({ member }: { member: User }) => {
    /**
     * Translate the provided string
     * to the current locale.
     */
    const t = useLaravelReactI18n().t;

    /**
     * The page's props.
     */
    const { user } = usePage<PageProps>().props;

    /**
     * Whether the member is the authenticated user, who leaves
     * the team instead of being removed from it.
     */
    const isSelf = member.id === user?.id;

    /**
     * Whether the removal confirmation dialog is open.
     */
    const [removeOpen, setRemoveOpen] = useState(false);

    return (
        <>
            <DropdownMenu>
                <DropdownMenuTrigger render={<Button size="icon" variant="ghost" />}>
                    <MoreHorizontalIcon />
                    <span className="sr-only">{t("Actions")}</span>
                </DropdownMenuTrigger>
                <DropdownMenuContent align="end" className="w-auto min-w-(--anchor-width)">
                    <DropdownMenuGroup>
                        <DropdownMenuItem onClick={() => setRemoveOpen(true)} variant="destructive">
                            {isSelf ? <LogOutIcon /> : <TrashIcon />}
                            <span>{isSelf ? t("Leave team") : t("Remove")}</span>
                        </DropdownMenuItem>
                    </DropdownMenuGroup>
                </DropdownMenuContent>
            </DropdownMenu>
            <ConfirmAction
                action={
                    <Link
                        href={routes.destroy.url({
                            team: user?.current_team?.slug ?? "",
                            user: member.id,
                        })}
                        method="delete"
                    />
                }
                confirmLabel={isSelf ? t("Leave team") : t("Remove member")}
                description={
                    isSelf
                        ? t("You will lose access to this team's accounts and transactions.")
                        : t("The member will lose access to this team's accounts and transactions.")
                }
                onOpenChange={setRemoveOpen}
                open={removeOpen}
                title={
                    isSelf
                        ? t("Are you sure you want to leave the team?")
                        : t("Are you sure you want to remove the member?")
                }
            />
        </>
    );
};

const Listing = () => {
    /**
     * Translate the provided string
     * to the current locale.
     */
    const t = useLaravelReactI18n().t;

    /**
     * The page's props.
     */
    const { filters, members, user } = usePage<PageProps>().props;

    /**
     * Whether the listing is being filtered.
     */
    const isFiltered = Object.values(filters ?? {}).some((value) => value !== null && value !== "");

    return (
        <Deferred data="members" fallback={<SkeletonRows />}>
            {!members?.data?.length ? (
                <div className="rounded-sm border">
                    <Empty>
                        <EmptyHeader>
                            <EmptyMedia variant="icon">
                                {isFiltered ? <SearchXIcon /> : <CircleDashedIcon />}
                            </EmptyMedia>
                            <EmptyTitle>
                                {isFiltered
                                    ? t("No results for the current filters")
                                    : t("No :name yet", { name: t("members") })}
                            </EmptyTitle>
                            <EmptyDescription>
                                {isFiltered
                                    ? t("Try adjusting or clearing the filters.")
                                    : t("Invite someone to get started.")}
                            </EmptyDescription>
                        </EmptyHeader>
                    </Empty>
                </div>
            ) : (
                <div className="flex flex-col gap-4">
                    <div className="overflow-hidden rounded-sm border">
                        <ItemGroup className="gap-0">
                            {(members?.data ?? []).map((member) => (
                                <Item
                                    className="rounded-none border-0 border-b last:border-b-0"
                                    key={member.id}
                                    size="sm"
                                    variant="outline"
                                >
                                    <ItemMedia>
                                        <Avatar>
                                            <AvatarImage src={member.avatar ?? undefined} />
                                            <AvatarFallback>{initials(member.name)}</AvatarFallback>
                                        </Avatar>
                                    </ItemMedia>
                                    <ItemContent>
                                        <ItemTitle>{member.name}</ItemTitle>
                                        <ItemDescription>
                                            {member.email}
                                            {member.membership?.created_at
                                                ? ` · ${t("Joined on :date", { date: format(new Date(member.membership.created_at), "MMMM d, yyyy") })}`
                                                : null}
                                        </ItemDescription>
                                    </ItemContent>
                                    <ItemActions className="flex-wrap justify-end">
                                        {member.id === user?.id ? <Badge variant="secondary">{t("You")}</Badge> : null}
                                        {(members?.total ?? 0) > 1 ? <MemberActions member={member} /> : null}
                                    </ItemActions>
                                </Item>
                            ))}
                        </ItemGroup>
                    </div>
                    <Pagination />
                </div>
            )}
        </Deferred>
    );
};

const SkeletonRows = () => {
    return (
        <div className="overflow-hidden rounded-sm border">
            {[0, 1, 2].map((row) => (
                <div className="flex items-center gap-3 border-b px-3 py-2.5 last:border-b-0" key={row}>
                    <Skeleton className="size-8 rounded-full" />
                    <div className="flex flex-1 flex-col gap-2">
                        <Skeleton className="h-4 w-40" />
                        <Skeleton className="h-3 w-56" />
                    </div>
                </div>
            ))}
        </div>
    );
};

const Pagination = () => {
    /**
     * Translate the provided string
     * to the current locale.
     */
    const t = useLaravelReactI18n().t;

    /**
     * The page's props.
     */
    const { members } = usePage<PageProps>().props;

    return (
        <PaginationComponent>
            <PaginationContent>
                <PaginationItem>
                    <Button
                        disabled={!members?.prev_page_url}
                        nativeButton={false}
                        render={<Link href={members?.prev_page_url ?? undefined} />}
                        variant="ghost"
                    >
                        <ChevronLeftIcon data-icon="inline-start" />
                        <span>{t("Previous")}</span>
                    </Button>
                </PaginationItem>
                <PaginationItem>
                    <Button
                        disabled={!members?.next_page_url}
                        nativeButton={false}
                        render={<Link href={members?.next_page_url ?? undefined} />}
                        variant="ghost"
                    >
                        <span>{t("Next")}</span>
                        <ChevronRightIcon data-icon="inline-end" />
                    </Button>
                </PaginationItem>
            </PaginationContent>
        </PaginationComponent>
    );
};

Index.layout = (page: React.ReactNode) => <ApplicationLayout>{page}</ApplicationLayout>;

export default Index;
