import { Deferred, InfiniteScroll, usePage } from "@inertiajs/react";
import { format, parse } from "date-fns";
import { useLaravelReactI18n } from "laravel-react-i18n";
import { ChevronDownIcon, CircleDashedIcon, ListFilterPlusIcon, SearchXIcon, XIcon } from "lucide-react";
import { useState } from "react";
import { type DateRange } from "react-day-picker";

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
import { cn, formatRelativeTime } from "@/lib/utils";
import routes from "@/routes/identity/teams/activity";
import { ActivitySubjectOption } from "@/types/app/modules/core/identity/activity-subject-option";
import { ActivitySubject } from "@/types/app/modules/core/identity/enums/team/activity/activity-subject";
import { ActivityCauser, ActivityChange, ActivityEntry } from "@/types/app/modules/core/identity/models/team/activity";
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
    members: Pick<User, "id" | "name">[];
    subjects: ActivitySubjectOption[];
};

/**
 * The date format used in the URL.
 */
const DATE_FORMAT = "yyyy-MM-dd";

/**
 * The changed fields whose values are instants rather
 * than plain text.
 */
const DATE_FIELDS = ["accepted_at", "ends_at", "expires_at", "occurred_at", "paused_at", "trial_ends_at"];

/**
 * The translated label of a changed field, so nobody needs to
 * know the column names. A field with no label of its own is
 * shown as it is stored.
 */
const FIELD_LABELS: Record<string, string> = {
    accepted_at: "Accepted",
    account_id: "Account",
    amount: "Amount",
    currency_id: "Currency",
    description: "Description",
    email: "Email",
    ends_at: "Ends",
    expires_at: "Expires",
    is_active: "Active",
    name: "Name",
    occurred_at: "Date",
    paused_at: "Paused",
    quote: "Rate",
    role: "Role",
    slug: "Handle",
    status: "Status",
    tags: "Tags",
    trial_ends_at: "Trial ends",
    type: "Type",
};

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
    return (
        <div className="flex flex-col gap-8">
            {/** Header */}
            <Header />

            {/** Content */}
            <div className="flex flex-col gap-4">
                {/** Filters */}
                <Filters />

                {/** Timeline */}
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
            <h1 className="text-2xl font-medium">{t("Activity")}</h1>
            <p className="text-muted-foreground text-sm">{t("Every change made inside this team, and who made it.")}</p>
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

const Causer = ({ causer }: { causer: ActivityCauser }) => {
    /**
     * Translate the provided string
     * to the current locale.
     */
    const t = useLaravelReactI18n().t;

    const name =
        causer.kind === "member"
            ? (causer.name ?? t("A former member"))
            : causer.kind === "support"
              ? t("Support")
              : causer.kind === "system"
                ? t("System")
                : t("A former member");

    return <span className="font-medium">{name}</span>;
};

const Value = ({ label, value }: { label: ActivityChange["old_label"]; value: ActivityChange["old"] }) => {
    /**
     * Translate the provided string
     * to the current locale.
     */
    const t = useLaravelReactI18n().t;

    // A list reads as its resolved names, or as its raw keys
    // when nothing named them.
    if (Array.isArray(value)) {
        const items = Array.isArray(label) ? label : value.map(String);

        return <span>{items.length ? items.join(", ") : t("None")}</span>;
    }

    if (value === null) {
        return <span className="text-muted-foreground">{t("None")}</span>;
    }

    if (typeof value === "boolean") {
        return <span>{value ? t("Yes") : t("No")}</span>;
    }

    return <span>{typeof label === "string" ? label : value}</span>;
};

const Changes = ({ changes }: { changes: ActivityChange[] }) => {
    /**
     * Translate the provided string
     * to the current locale.
     */
    const { currentLocale, t } = useLaravelReactI18n();

    /**
     * Present one stored value the way its field reads: an instant
     * as a date, an amount without the padding the database keeps,
     * and anything else as it is.
     */
    const present = (field: string, value: ActivityChange["old"]): ActivityChange["old"] => {
        if (typeof value !== "string") {
            return value;
        }

        if (DATE_FIELDS.includes(field)) {
            const parsed = new Date(value);

            return Number.isNaN(parsed.getTime())
                ? value
                : parsed.toLocaleDateString(currentLocale(), { dateStyle: "medium" });
        }

        // Decimal columns keep every scale digit, which nobody
        // reads. Trailing zeros carry no value.
        if (["amount", "quote"].includes(field) && value.includes(".")) {
            return value.replace(/0+$/, "").replace(/\.$/, "");
        }

        return value;
    };

    if (!changes.length) {
        return null;
    }

    /**
     * An entry that only recorded values it had reads as what
     * went, and one that only recorded values it gained reads
     * as what arrived. Everything else is a replacement.
     */
    const isRemoval = changes.every((change) => change.new === null);
    const isAddition = changes.every((change) => change.old === null);

    return (
        <dl className="grid grid-cols-[auto_1fr] gap-x-4 gap-y-1 text-sm">
            {changes.map((change) => (
                <div className="col-span-2 grid grid-cols-subgrid" key={change.field}>
                    <dt className="text-muted-foreground">{t(FIELD_LABELS[change.field] ?? change.field)}</dt>
                    <dd className="flex flex-wrap items-center gap-2">
                        {isRemoval ? (
                            <Value label={change.old_label} value={present(change.field, change.old)} />
                        ) : isAddition ? (
                            <Value label={change.new_label} value={present(change.field, change.new)} />
                        ) : (
                            <>
                                <span className="text-muted-foreground line-through">
                                    <Value label={change.old_label} value={present(change.field, change.old)} />
                                </span>
                                <Value label={change.new_label} value={present(change.field, change.new)} />
                            </>
                        )}
                    </dd>
                </div>
            ))}
        </dl>
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
    const { subjects } = usePage<PageProps>().props;

    /**
     * Whether the values before and after are shown.
     */
    const [open, setOpen] = useState(false);

    /**
     * The entry the batch reads as, which is the first row
     * the action wrote.
     */
    const [head, ...rest] = batch;

    if (!head) {
        return null;
    }

    /**
     * The translated name of the kind of record a row is about.
     */
    const subjectLabel = (entry: ActivityEntry) =>
        subjects.find((subject) => subject.value === entry.subject_type)?.label ?? "";

    /**
     * Whether any row of the batch recorded values worth opening.
     */
    const hasChanges = batch.some((entry) => entry.changes.length);

    return (
        <div className="flex flex-col gap-3 border-b px-4 py-3 last:border-b-0">
            <div className="flex items-start gap-3">
                <div className="flex min-w-0 flex-1 flex-col gap-1">
                    <div className="flex flex-wrap items-center gap-2 text-sm">
                        <Causer causer={head.causer} />
                        <Badge variant="secondary">{head.event}</Badge>
                        <span className="text-muted-foreground truncate">
                            {[subjectLabel(head), head.subject_label].filter(Boolean).join(" · ")}
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
                            title={new Date(head.created_at).toLocaleString(currentLocale(), {
                                dateStyle: "long",
                                timeStyle: "short",
                            })}
                        >
                            {formatRelativeTime(head.created_at, currentLocale())}
                        </span>
                    ) : null}
                </div>
                {hasChanges ? (
                    <Button onClick={() => setOpen(!open)} size="icon" variant="ghost">
                        <ChevronDownIcon className={cn("transition-transform", open && "rotate-180")} />
                        <span className="sr-only">{t("Details")}</span>
                    </Button>
                ) : null}
            </div>
            {open ? (
                <div className="flex flex-col gap-3 pl-1">
                    {batch.map((entry) => (
                        <div className="flex flex-col gap-1" key={entry.id}>
                            {batch.length > 1 ? (
                                <span className="text-muted-foreground text-xs">
                                    {[subjectLabel(entry), entry.subject_label, entry.event]
                                        .filter(Boolean)
                                        .join(" · ")}
                                </span>
                            ) : null}
                            <Changes changes={entry.changes} />
                        </div>
                    ))}
                </div>
            ) : null}
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
                        <div className="overflow-hidden rounded-sm border">
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
                        <div className="overflow-hidden rounded-sm border">
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

const SkeletonRows = () => {
    return (
        <div className="overflow-hidden rounded-sm border">
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
