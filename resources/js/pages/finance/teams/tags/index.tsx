import { Deferred, Link, usePage } from "@inertiajs/react";
import { ModalLink } from "@inertiaui/modal-react";
import { useLaravelReactI18n } from "laravel-react-i18n";
import {
    CheckIcon,
    CircleDashedIcon,
    ListFilterPlusIcon,
    MoreHorizontalIcon,
    PencilIcon,
    PlusIcon,
    SearchIcon,
    SearchXIcon,
    TagIcon,
    XIcon,
} from "lucide-react";

import { PaginationControls } from "@/components/pagination-controls";
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
import { Item, ItemActions, ItemContent, ItemGroup, ItemMedia, ItemTitle } from "@/components/ui/item";
import { Popover, PopoverContent, PopoverHeader, PopoverTitle, PopoverTrigger } from "@/components/ui/popover";
import { Select, SelectContent, SelectGroup, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import { Skeleton } from "@/components/ui/skeleton";
import { useFilters } from "@/hooks/use-filters";
import { ApplicationLayout } from "@/layouts/application/layout";
import routes from "@/routes/finance/teams/tags";
import { Tag } from "@/types/app/modules/core/finance/models/team/tag";
import { LengthAwarePaginator } from "@/types/illuminate/pagination/length-aware-paginator";

type PageFilters = {
    search: string | null;
    is_active: string | null;
};

type PageProps = {
    filters: Partial<PageFilters>;
    tags: LengthAwarePaginator<Tag>;
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
                        <CreateTagButton className="max-sm:w-full" />
                    </div>
                </div>

                {/** Tags */}
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
            <h1 className="font-heading text-[1.75rem] leading-tight font-semibold tracking-tight">{t("Tags")}</h1>
            <p className="text-muted-foreground text-[15px] leading-relaxed">
                {t("Labels this team can attach to transactions.")}
            </p>
        </header>
    );
};

const CreateTagButton = ({ className }: { className?: string }) => {
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
                    {t("Create :name", { name: t("tag") })}
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
            is_active: initial?.is_active ?? null,
        },
        url: (query) => routes.index.url({ team: user?.current_team?.slug ?? "" }, { query }),
        only: ["tags", "filters"],
    });

    return (
        <div className="flex items-center gap-2">
            {/** Search */}
            <Field className="flex-1">
                <FieldLabel className="sr-only" htmlFor="tags-search">
                    {t("Search")}
                </FieldLabel>
                <InputGroup>
                    <InputGroupAddon>
                        <SearchIcon />
                    </InputGroupAddon>
                    <InputGroupInput
                        id="tags-search"
                        onChange={(event) => setFilters({ search: event.target.value }, { debounced: true })}
                        placeholder={t("Search by name")}
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
                            <Select
                                onValueChange={(value) => setFilters({ is_active: value })}
                                value={filters.is_active}
                            >
                                <SelectTrigger className="w-full">
                                    <SelectValue>
                                        {(value: string | null) =>
                                            `${value === null ? t("All") : value === "true" ? t("Active") : t("Inactive")}`
                                        }
                                    </SelectValue>
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectGroup>
                                        <SelectItem value={null}>{t("All")}</SelectItem>
                                        <SelectItem value="true">{t("Active")}</SelectItem>
                                        <SelectItem value="false">{t("Inactive")}</SelectItem>
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

const Listing = () => {
    /**
     * Translate the provided string
     * to the current locale.
     */
    const t = useLaravelReactI18n().t;

    /**
     * The page's props.
     */
    const { filters, tags, user } = usePage<PageProps>().props;

    /**
     * Whether the listing is being filtered.
     */
    const isFiltered = Object.values(filters ?? {}).some((value) => value !== null && value !== "");

    return (
        <Deferred data="tags" fallback={<SkeletonRows />}>
            {!tags?.data?.length ? (
                <div className="rounded-xl border">
                    <Empty>
                        <EmptyHeader>
                            <EmptyMedia variant="icon">
                                {isFiltered ? <SearchXIcon /> : <CircleDashedIcon />}
                            </EmptyMedia>
                            <EmptyTitle>
                                {isFiltered
                                    ? t("No results for the current filters")
                                    : t("No :name yet", { name: t("tags") })}
                            </EmptyTitle>
                            <EmptyDescription>
                                {isFiltered
                                    ? t("Try adjusting or clearing the filters.")
                                    : t("Add :name to get started.", { name: t("tags") })}
                            </EmptyDescription>
                        </EmptyHeader>
                        {!isFiltered ? (
                            <EmptyContent>
                                <CreateTagButton />
                            </EmptyContent>
                        ) : null}
                    </Empty>
                </div>
            ) : (
                <div className="flex flex-col gap-4">
                    <div className="overflow-hidden rounded-xl border">
                        <ItemGroup className="gap-0">
                            {(tags?.data ?? []).map((tag) => (
                                <Item
                                    className="rounded-none border-0 border-b last:border-b-0"
                                    key={tag.id}
                                    size="sm"
                                    variant="outline"
                                >
                                    <ItemMedia variant="icon">
                                        <TagIcon />
                                    </ItemMedia>
                                    <ItemContent>
                                        <ItemTitle>{tag.name}</ItemTitle>
                                    </ItemContent>
                                    <ItemActions className="flex-wrap justify-end">
                                        {/** Active is the normal state, so only the exception is labelled. */}
                                        {tag.is_active ? null : <Badge variant="secondary">{t("Inactive")}</Badge>}
                                        <DropdownMenu>
                                            <DropdownMenuTrigger render={<Button size="icon" variant="ghost" />}>
                                                <MoreHorizontalIcon />
                                                <span className="sr-only">{t("Actions")}</span>
                                            </DropdownMenuTrigger>
                                            <DropdownMenuContent align="end">
                                                <DropdownMenuGroup>
                                                    <DropdownMenuItem
                                                        render={
                                                            <ModalLink
                                                                href={routes.edit.url({
                                                                    tag: { id: tag.id },
                                                                    team: user?.current_team?.slug ?? "",
                                                                })}
                                                            >
                                                                <PencilIcon />
                                                                <span>{t("Edit")}</span>
                                                            </ModalLink>
                                                        }
                                                    />
                                                    <DropdownMenuItem
                                                        nativeButton
                                                        render={
                                                            <Link
                                                                className="w-full"
                                                                href={
                                                                    tag.is_active
                                                                        ? routes.deactivate.url({
                                                                              tag: tag.id,
                                                                              team: user?.current_team?.slug ?? "",
                                                                          })
                                                                        : routes.activate.url({
                                                                              tag: tag.id,
                                                                              team: user?.current_team?.slug ?? "",
                                                                          })
                                                                }
                                                                method="patch"
                                                            >
                                                                {tag.is_active ? <XIcon /> : <CheckIcon />}
                                                                <span>
                                                                    {tag.is_active ? t("Deactivate") : t("Activate")}
                                                                </span>
                                                            </Link>
                                                        }
                                                    />
                                                </DropdownMenuGroup>
                                            </DropdownMenuContent>
                                        </DropdownMenu>
                                    </ItemActions>
                                </Item>
                            ))}
                        </ItemGroup>
                    </div>
                    <PaginationControls nextUrl={tags?.next_page_url} previousUrl={tags?.prev_page_url} />
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
                        <Skeleton className="h-4 w-40" />
                    </div>
                    <Skeleton className="h-5 w-16" />
                </div>
            ))}
        </div>
    );
};

Index.layout = (page: React.ReactNode) => <ApplicationLayout>{page}</ApplicationLayout>;

export default Index;
