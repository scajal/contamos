import { Deferred, Link, usePage } from "@inertiajs/react";
import { ModalLink } from "@inertiaui/modal-react";
import { useLaravelReactI18n } from "laravel-react-i18n";
import {
    CircleDashedIcon,
    ListFilterPlusIcon,
    MailIcon,
    MoreHorizontalIcon,
    PlusIcon,
    RefreshCwIcon,
    SearchIcon,
    SearchXIcon,
    TrashIcon,
    XIcon,
} from "lucide-react";
import { useState } from "react";

import { ConfirmAction } from "@/components/confirm-action";
import { PaginationControls } from "@/components/pagination-controls";
import { RoleBadge } from "@/components/role-badge";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuGroup,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from "@/components/ui/dropdown-menu";
import { Empty, EmptyContent, EmptyDescription, EmptyHeader, EmptyMedia, EmptyTitle } from "@/components/ui/empty";
import { Field, FieldGroup, FieldLabel } from "@/components/ui/field";
import { InputGroup, InputGroupAddon, InputGroupInput } from "@/components/ui/input-group";
import { Item, ItemActions, ItemContent, ItemDescription, ItemGroup, ItemMedia, ItemTitle } from "@/components/ui/item";
import { Popover, PopoverContent, PopoverHeader, PopoverTitle, PopoverTrigger } from "@/components/ui/popover";
import { Select, SelectContent, SelectGroup, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import { Skeleton } from "@/components/ui/skeleton";
import { useFilters } from "@/hooks/use-filters";
import { ApplicationLayout } from "@/layouts/application/layout";
import { formatDate, invitationStatus, type InvitationStatus } from "@/lib/utils";
import routes from "@/routes/identity/teams/invitations";
import { TeamInvitation } from "@/types/app/modules/core/identity/models/team/team-invitation";
import { LengthAwarePaginator } from "@/types/illuminate/pagination/length-aware-paginator";

type PageFilters = {
    search: string | null;
    status: string | null;
};

type PageProps = {
    filters: Partial<PageFilters>;
    invitations: LengthAwarePaginator<TeamInvitation>;
};

const Index = () => {
    return (
        <div className="flex flex-col gap-8">
            {/** Header */}
            <Header />

            {/** Content */}
            <div className="flex flex-col gap-4">
                {/** Filters and actions */}
                <div className="flex items-center justify-between gap-2 max-sm:flex-col">
                    <div className="flex-1 max-sm:w-full">
                        <Filters />
                    </div>
                    <div className="flex justify-end max-sm:order-first max-sm:w-full">
                        <InviteMemberButton className="max-sm:w-full" />
                    </div>
                </div>

                {/** Invitations */}
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
            <h1 className="font-heading text-[1.75rem] leading-tight font-semibold tracking-tight">
                {t("Invitations")}
            </h1>
            <p className="text-muted-foreground text-[15px] leading-relaxed">
                {t("People invited to join this team.")}
            </p>
        </header>
    );
};

const InviteMemberButton = ({ className }: { className?: string }) => {
    /**
     * Translate the provided string
     * to the current locale.
     */
    const t = useLaravelReactI18n().t;

    /**
     * The page's props.
     */
    const { user } = usePage().props;

    return (
        <Button
            className={className}
            nativeButton={false}
            render={
                <ModalLink href={routes.create.url({ team: user?.current_team?.slug ?? "" })}>
                    <PlusIcon data-icon="inline-start" />
                    {t("Invite member")}
                </ModalLink>
            }
        />
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
            status: initial?.status ?? null,
        },
        url: (query) => routes.index.url({ team: user?.current_team?.slug ?? "" }, { query }),
        only: ["invitations", "filters"],
    });

    /**
     * The label of the given status filter value.
     */
    const statusLabel = (value: string | null) => {
        switch (value) {
            case "pending":
                return t("Pending");
            case "accepted":
                return t("Accepted");
            case "expired":
                return t("Expired");
            default:
                return t("All");
        }
    };

    return (
        <div className="flex items-center gap-2">
            {/** Search */}
            <Field className="flex-1">
                <FieldLabel className="sr-only" htmlFor="invitations-search">
                    {t("Search")}
                </FieldLabel>
                <InputGroup>
                    <InputGroupAddon>
                        <SearchIcon />
                    </InputGroupAddon>
                    <InputGroupInput
                        id="invitations-search"
                        onChange={(event) => setFilters({ search: event.target.value }, { debounced: true })}
                        placeholder={t("Search by email")}
                        value={filters.search ?? ""}
                    />
                </InputGroup>
            </Field>

            {/** Advanced filters */}
            <Popover>
                <PopoverTrigger className="relative" render={<Button size="icon" variant="outline" />}>
                    <ListFilterPlusIcon />
                    <span className="sr-only">{t("More filters")}</span>
                    {isFiltered && <span className="bg-primary absolute -top-1 -right-1 size-3 rounded-full" />}
                </PopoverTrigger>
                <PopoverContent>
                    <PopoverHeader>
                        <PopoverTitle>{t("More filters")}</PopoverTitle>
                    </PopoverHeader>
                    <FieldGroup className="gap-4">
                        {/** Status */}
                        <Field>
                            <FieldLabel>{t("Status")}</FieldLabel>
                            <Select onValueChange={(value) => setFilters({ status: value })} value={filters.status}>
                                <SelectTrigger className="w-full">
                                    <SelectValue>{(value: string | null) => statusLabel(value)}</SelectValue>
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectGroup>
                                        <SelectItem value={null}>{t("All")}</SelectItem>
                                        <SelectItem value="pending">{t("Pending")}</SelectItem>
                                        <SelectItem value="accepted">{t("Accepted")}</SelectItem>
                                        <SelectItem value="expired">{t("Expired")}</SelectItem>
                                    </SelectGroup>
                                </SelectContent>
                            </Select>
                        </Field>
                    </FieldGroup>
                </PopoverContent>
            </Popover>

            {/** Clear */}
            <Button disabled={!isFiltered} onClick={clear} size="icon" variant="outline">
                <XIcon />
                <span className="sr-only">{t("Clear filters")}</span>
            </Button>
        </div>
    );
};

const StatusBadge = ({ status }: { status: InvitationStatus }) => {
    /**
     * Translate the provided string
     * to the current locale.
     */
    const t = useLaravelReactI18n().t;

    switch (status) {
        case "accepted":
            return <Badge variant="ghost">{t("Accepted")}</Badge>;
        case "expired":
            return <Badge variant="destructive">{t("Expired")}</Badge>;
        default:
            return <Badge variant="secondary">{t("Pending")}</Badge>;
    }
};

const InvitationActions = ({ invitation }: { invitation: TeamInvitation }) => {
    /**
     * Translate the provided string
     * to the current locale.
     */
    const t = useLaravelReactI18n().t;

    /**
     * The page's props.
     */
    const { user } = usePage().props;

    /**
     * Whether the cancel confirmation dialog is open.
     */
    const [cancelOpen, setCancelOpen] = useState(false);

    return (
        <>
            <DropdownMenu>
                <DropdownMenuTrigger render={<Button size="icon" variant="ghost" />}>
                    <MoreHorizontalIcon />
                    <span className="sr-only">{t("Actions")}</span>
                </DropdownMenuTrigger>
                <DropdownMenuContent align="end" className="w-auto min-w-(--anchor-width)">
                    <DropdownMenuGroup>
                        <DropdownMenuItem
                            nativeButton
                            render={
                                <Link
                                    className="w-full"
                                    href={routes.resend.url({
                                        invitation: invitation.code,
                                        team: user?.current_team?.slug ?? "",
                                    })}
                                    method="patch"
                                >
                                    <RefreshCwIcon />
                                    <span>{t("Resend")}</span>
                                </Link>
                            }
                        />
                        <DropdownMenuItem onClick={() => setCancelOpen(true)} variant="destructive">
                            <TrashIcon />
                            <span>{t("Cancel")}</span>
                        </DropdownMenuItem>
                    </DropdownMenuGroup>
                </DropdownMenuContent>
            </DropdownMenu>
            <ConfirmAction
                action={
                    <Link
                        href={routes.destroy.url({
                            invitation: invitation.code,
                            team: user?.current_team?.slug ?? "",
                        })}
                        method="delete"
                    />
                }
                confirmLabel={t("Cancel invitation")}
                description={t("This action cannot be undone.")}
                onOpenChange={setCancelOpen}
                open={cancelOpen}
                title={t("Are you sure you want to cancel the invitation?")}
            />
        </>
    );
};

const Listing = () => {
    /**
     * Translate the provided string
     * to the current locale.
     */
    const { currentLocale, t } = useLaravelReactI18n();

    /**
     * The page's props.
     */
    const { filters, invitations } = usePage<PageProps>().props;

    /**
     * Whether the listing is being filtered.
     */
    const isFiltered = Object.values(filters ?? {}).some((value) => value !== null && value !== "");

    return (
        <Deferred data="invitations" fallback={<SkeletonRows />}>
            {!invitations?.data?.length ? (
                <div className="rounded-xl border">
                    <Empty>
                        <EmptyHeader>
                            <EmptyMedia variant="icon">
                                {isFiltered ? <SearchXIcon /> : <CircleDashedIcon />}
                            </EmptyMedia>
                            <EmptyTitle>
                                {isFiltered
                                    ? t("No results for the current filters")
                                    : t("No :name yet", { name: t("invitations") })}
                            </EmptyTitle>
                            <EmptyDescription>
                                {isFiltered
                                    ? t("Try adjusting or clearing the filters.")
                                    : t("Invite someone to get started.")}
                            </EmptyDescription>
                        </EmptyHeader>
                        {!isFiltered ? (
                            <EmptyContent>
                                <InviteMemberButton />
                            </EmptyContent>
                        ) : null}
                    </Empty>
                </div>
            ) : (
                <div className="flex flex-col gap-4">
                    <div className="overflow-hidden rounded-xl border">
                        <ItemGroup className="gap-0">
                            {(invitations?.data ?? []).map((invitation) => {
                                const status = invitationStatus(invitation);

                                return (
                                    <Item
                                        className="rounded-none border-0 border-b last:border-b-0"
                                        key={invitation.id}
                                        size="sm"
                                        variant="outline"
                                    >
                                        <ItemMedia variant="icon">
                                            <MailIcon />
                                        </ItemMedia>
                                        <ItemContent>
                                            <ItemTitle>{invitation.email}</ItemTitle>
                                            <ItemDescription>
                                                {t("Invited by :name", { name: invitation.inviter?.name ?? "" })}
                                                {invitation.expires_at && status === "pending"
                                                    ? ` · ${t("Expires on :date", { date: formatDate(invitation.expires_at, currentLocale()) })}`
                                                    : null}
                                            </ItemDescription>
                                        </ItemContent>
                                        <ItemActions className="flex-wrap justify-end">
                                            <RoleBadge role={invitation.role} />
                                            <StatusBadge status={status} />
                                            {status !== "accepted" ? (
                                                <InvitationActions invitation={invitation} />
                                            ) : null}
                                        </ItemActions>
                                    </Item>
                                );
                            })}
                        </ItemGroup>
                    </div>
                    <PaginationControls nextUrl={invitations?.next_page_url} previousUrl={invitations?.prev_page_url} />
                </div>
            )}
        </Deferred>
    );
};

const SkeletonRows = () => {
    return (
        <div className="overflow-hidden rounded-xl border">
            {[0, 1, 2].map((row) => (
                <div className="flex items-center gap-3 border-b px-3 py-2.5 last:border-b-0" key={row}>
                    <Skeleton className="size-8 rounded-xl" />
                    <div className="flex flex-1 flex-col gap-2">
                        <Skeleton className="h-4 w-48" />
                        <Skeleton className="h-3 w-32" />
                    </div>
                    <Skeleton className="h-5 w-16" />
                </div>
            ))}
        </div>
    );
};

Index.layout = (page: React.ReactNode) => <ApplicationLayout>{page}</ApplicationLayout>;

export default Index;
