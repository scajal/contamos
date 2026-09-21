import { useLaravelReactI18n } from "laravel-react-i18n";

import { Badge } from "@/components/ui/badge";
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from "@/components/ui/dialog";
import { Field, FieldGroup, FieldLabel } from "@/components/ui/field";
import { Modal } from "@/components/ui/modal";
import { ACTIVITY_DATE_FIELDS, ACTIVITY_FIELD_LABELS, causerName } from "@/lib/activity";
import { ActivityChange, ActivityEntry } from "@/types/app/modules/core/identity/models/team/activity";

type PageProps = {
    entries: Array<ActivityEntry & { changes: ActivityChange[] }>;
};

export default function Show({ entries }: PageProps) {
    /**
     * Translate the provided string
     * to the current locale.
     */
    const { currentLocale, t } = useLaravelReactI18n();

    /**
     * The entry the dialog is about, which is the first row the
     * action wrote.
     */
    const head = entries[0];

    /**
     * When the action happened, to the minute.
     */
    const occurredAt = head?.created_at
        ? new Intl.DateTimeFormat(currentLocale(), { dateStyle: "long", timeStyle: "short" }).format(
              new Date(head.created_at),
          )
        : null;

    return (
        <Modal>
            {({ rootProps }) => (
                <Dialog {...rootProps}>
                    <DialogContent>
                        <DialogHeader>
                            <DialogTitle>{t("Activity details")}</DialogTitle>
                            <DialogDescription suppressHydrationWarning>
                                {[head ? causerName(head.causer, t) : null, occurredAt].filter(Boolean).join(" · ")}
                            </DialogDescription>
                        </DialogHeader>
                        <div className="flex-1">
                            <FieldGroup>
                                {entries.map((entry) => (
                                    <Field key={entry.id}>
                                        <FieldLabel className="flex flex-wrap items-center gap-2">
                                            <Badge variant="secondary">{entry.event}</Badge>
                                            <span className="text-muted-foreground min-w-0 truncate">
                                                {entry.subject_label ?? t("Record")}
                                            </span>
                                        </FieldLabel>
                                        {entry.changes.length ? (
                                            <Changes changes={entry.changes} />
                                        ) : (
                                            <p className="text-muted-foreground text-sm">
                                                {t("This change recorded no values.")}
                                            </p>
                                        )}
                                    </Field>
                                ))}
                            </FieldGroup>
                        </div>
                    </DialogContent>
                </Dialog>
            )}
        </Modal>
    );
}

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

        if (ACTIVITY_DATE_FIELDS.includes(field)) {
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
                    <dt className="text-muted-foreground">{t(ACTIVITY_FIELD_LABELS[change.field] ?? change.field)}</dt>
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
