import { Deferred, InfiniteScroll, usePage } from "@inertiajs/react";
import { ModalLink } from "@inertiaui/modal-react";
import { format, parse } from "date-fns";
import { useLaravelReactI18n } from "laravel-react-i18n";
import { ChevronRightIcon, CircleDashedIcon, ListFilterPlusIcon, SearchXIcon, XIcon } from "lucide-react";
import { type DateRange } from "react-day-picker";

import { AssistantBadge } from "@/components/assistant-badge";
import { FeatureLock } from "@/components/feature-lock";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Calendar } from "@/components/ui/calendar";
import { Empty, EmptyDescription, EmptyHeader, EmptyMedia, EmptyTitle } from "@/components/ui/empty";
import { Field, FieldGroup, FieldLabel } from "@/components/ui/field";
import { Popover, PopoverContent, PopoverHeader, PopoverTitle, PopoverTrigger } from "@/components/ui/popover";
import { Select, SelectContent, SelectGroup, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import { Skeleton } from "@/components/ui/skeleton";
import { useFilters } from "@/hooks/use-filters";
import { ApplicationLayout } from "@/layouts/application/layout";
import { causerName } from "@/lib/activity";
import { formatRelativeTime } from "@/lib/utils";
import routes from "@/routes/identity/teams/activity";
import { ActivitySubjectOption } from "@/types/app/modules/core/identity/activity-subject-option";
import { ActivitySubject } from "@/types/app/modules/core/identity/enums/team/activity/activity-subject";
import { ActivityEntry } from "@/types/app/modules/core/identity/models/team/activity";
import { User } from "@/types/app/modules/core/identity/models/user";
import { LengthAwarePaginator } from "@/types/illuminate/pagination/length-aware-paginator";

type PageFilters = {
    subject: ActivitySubject | null;
    causer: string | null;
    from: string | null;
    to: string | null;
};

type PageProps = {
    activities: LengthAwarePaginator<ActivityEntry>;
    filters: Partial<PageFilters>;
    is_locked: boolean;
    members: Pick<User, "id" | "name">[];
    subjects: ActivitySubjectOption[];
};

/**
 * The date format used in the URL.
 */
const DATE_FORMAT = "yyyy-MM-dd";

/**
 * Parse a URL date into a Date, if any.
 */
const toDate = (value: string | null | undefined): Date | undefined =>
    value ? parse(value, DATE_FORMAT, new Date()) : undefined;

/**
 * Group the consecutive entries a single action wrote, so the
 * timeline reads as what people did rather than as rows. A batch
 * split across two pages reads as two entries.
 */
const toBatches = (entries: ActivityEntry[]): ActivityEntry[][] =>
    entries.reduce<ActivityEntry[][]>((batches, entry) => {
        const last = batches.at(-1);

        if (last && entry.batch_uuid && last[0]?.batch_uuid === entry.batch_uuid) {
            last.push(entry);

            return batches;
        }

        return [...batches, [entry]];
    }, []);

const Index = () => {
    /**
     * The page's props.
     */
    const { is_locked } = usePage<PageProps>().props;

    return (
        <div className="flex flex-col gap-8">
            {/** Header */}
            <Header />

            {/** Content */}
            {is_locked ? (
                <LockedActivity />
            ) : (
                <div className="flex flex-col gap-4">
                    {/** Filters */}
                    <Filters />

                    {/** Timeline */}
                    <Listing />
                </div>
            )}
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
            <h1 className="font-heading text-[1.75rem] leading-tight font-semibold tracking-tight">{t("Activity")}</h1>
            <p className="text-muted-foreground text-[15px] leading-relaxed">
                {t("Every change made inside this team, and who made it.")}
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
    const { filters: initial, members, subjects, user } = usePage<PageProps>().props;

    /**
     * The filters synced with the URL.
     */
    const { clear, filters, isFiltered, setFilters } = useFilters<PageFilters>({
        initial: {
            subject: initial?.subject ?? null,
            causer: initial?.causer ?? null,
            from: initial?.from ?? null,
            to: initial?.to ?? null,
        },
        url: (query) => routes.index.url({ team: user?.current_team?.slug ?? "" }, { query }),
        only: ["activities", "filters"],
        reset: ["activities"],
    });

    /**
     * The selected date range.
     */
    const range: DateRange = { from: toDate(filters.from), to: toDate(filters.to) };

    return (
        <div className="flex items-center gap-2">
            {/** Record type */}
            <Field className="flex-1">
                <FieldLabel className="sr-only" htmlFor="activity-subject">
                    {t("Record")}
                </FieldLabel>
                <Select onValueChange={(value) => setFilters({ subject: value })} value={filters.subject}>
                    <SelectTrigger className="w-full" id="activity-subject">
                        <SelectValue>
                            {(value: ActivitySubject | null) =>
                                `${subjects.find((subject) => subject.value === value)?.label ?? t("All records")}`
                            }
                        </SelectValue>
                    </SelectTrigger>
                    <SelectContent>
                        <SelectGroup>
                            <SelectItem value={null}>{t("All records")}</SelectItem>
                            {subjects.map((subject) => (
                                <SelectItem key={subject.value} value={subject.value}>
                                    {subject.label}
                                </SelectItem>
                            ))}
                        </SelectGroup>
                    </SelectContent>
                </Select>
            </Field>

            {/** Member and dates */}
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
                        {/** Member */}
                        <Field>
                            <FieldLabel>{t("Member")}</FieldLabel>
                            <Select onValueChange={(value) => setFilters({ causer: value })} value={filters.causer}>
                                <SelectTrigger className="w-full">
                                    <SelectValue>
                                        {(value: string | null) =>
                                            `${members.find((member) => member.id === value)?.name ?? t("All")}`
                                        }
                                    </SelectValue>
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectGroup>
                                        <SelectItem value={null}>{t("All")}</SelectItem>
                                        {members.map((member) => (
                                            <SelectItem key={member.id} value={member.id}>
                                                {member.name}
                                            </SelectItem>
                                        ))}
                                    </SelectGroup>
                                </SelectContent>
                            </Select>
                        </Field>

                        {/** Date range */}
                        <Field>
                            <FieldLabel>{t("Date")}</FieldLabel>
                            <Calendar
                                defaultMonth={range.from}
                                mode="range"
                                onSelect={(selected) =>
                                    setFilters({
                                        from: selected?.from ? format(selected.from, DATE_FORMAT) : null,
                                        to: selected?.to ? format(selected.to, DATE_FORMAT) : null,
                                    })
                                }
                                selected={range}
                            />
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

const Entry = ({ batch }: { batch: ActivityEntry[] }) => {
    /**
     * Translate the provided string
     * to the current locale.
     */
    const { currentLocale, t } = useLaravelReactI18n();

    /**
     * The page's props.
     */
    const { subjects, user } = usePage<PageProps>().props;

    /**
     * The entry the batch reads as, which is the first row
     * the action wrote. The timeline arrives newest first, so
     * that row is the batch's last: a transaction created with
     * tags reads as created, not as its tags changing.
     */
    const head = batch.at(-1);
    const rest = batch.slice(0, -1);

    if (!head) {
        return null;
    }

    /**
     * The translated name of the kind of record the entry
     * is about.
     */
    const subject = subjects.find((option) => option.value === head.subject_type)?.label ?? "";

    return (
        <ModalLink
            className="hover:bg-muted/50 flex items-start gap-3 border-b px-4 py-3 transition-colors last:border-b-0"
            href={routes.show.url({ team: user?.current_team?.slug ?? "", activity: head.id })}
        >
            <div className="flex min-w-0 flex-1 flex-col gap-1">
                <div className="flex flex-wrap items-center gap-2 text-sm">
                    <span className="font-medium">{causerName(head.causer, t)}</span>
                    <Badge variant="secondary">{head.event_label}</Badge>
                    <AssistantBadge assistant={head.assistant} />
                    <span className="text-muted-foreground truncate">
                        {[subject, head.subject_label].filter(Boolean).join(" · ")}
                    </span>
                    {rest.length ? (
                        <span className="text-muted-foreground text-xs">
                            {t("+:count more", { count: String(rest.length) })}
                        </span>
                    ) : null}
                </div>
                {head.created_at ? (
                    <span
                        className="text-muted-foreground text-xs"
                        suppressHydrationWarning
                        title={new Date(head.created_at).toLocaleString(currentLocale(), {
                            dateStyle: "long",
                            timeStyle: "short",
                        })}
                    >
                        {formatRelativeTime(head.created_at, currentLocale())}
                    </span>
                ) : null}
            </div>
            <ChevronRightIcon className="text-muted-foreground mt-0.5 size-4 shrink-0" />
        </ModalLink>
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
    const { activities, filters } = usePage<PageProps>().props;

    /**
     * Whether the listing is being filtered.
     */
    const isFiltered = Object.values(filters ?? {}).some((value) => value !== null && value !== "");

    return (
        <Deferred data="activities" fallback={<SkeletonRows />}>
            <div>
                <InfiniteScroll data="activities" onlyNext>
                    {!activities?.data?.length ? (
                        <div className="overflow-hidden rounded-xl border">
                            <Empty>
                                <EmptyHeader>
                                    <EmptyMedia variant="icon">
                                        {isFiltered ? <SearchXIcon /> : <CircleDashedIcon />}
                                    </EmptyMedia>
                                    <EmptyTitle>
                                        {isFiltered
                                            ? t("No results for the current filters")
                                            : t("No :name yet", { name: t("activity") })}
                                    </EmptyTitle>
                                    <EmptyDescription>
                                        {isFiltered
                                            ? t("Try adjusting or clearing the filters.")
                                            : t("Changes made in this team will show up here.")}
                                    </EmptyDescription>
                                </EmptyHeader>
                            </Empty>
                        </div>
                    ) : (
                        <div className="overflow-hidden rounded-xl border">
                            {toBatches(activities.data).map((batch) => (
                                <Entry batch={batch} key={batch[0]?.id} />
                            ))}
                        </div>
                    )}
                </InfiniteScroll>
            </div>
        </Deferred>
    );
};

/**
 * What a team on a plan without the activity log sees. The log
 * does not travel to a locked page, so the panel stands on its own.
 */
const LockedActivity = () => {
    /**
     * Translate the provided string
     * to the current locale.
     */
    const t = useLaravelReactI18n().t;

    /**
     * The page's props.
     */
    const { user } = usePage<PageProps>().props;

    return (
        <FeatureLock
            description={t("See every change made inside this team, who made it, and when.")}
            feature="activity:view"
            team={user?.current_team?.slug ?? ""}
            title={t("Activity is part of Pro")}
        />
    );
};

const SkeletonRows = () => {
    return (
        <div className="overflow-hidden rounded-xl border">
            {[0, 1, 2].map((row) => (
                <div className="flex flex-col gap-2 border-b px-4 py-3 last:border-b-0" key={row}>
                    <Skeleton className="h-4 w-64" />
                    <Skeleton className="h-3 w-24" />
                </div>
            ))}
        </div>
    );
};

Index.layout = (page: React.ReactNode) => <ApplicationLayout>{page}</ApplicationLayout>;

export default Index;
