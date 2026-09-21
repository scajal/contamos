import { useLaravelReactI18n } from "laravel-react-i18n";
import { CalendarIcon, StickyNoteIcon, TagIcon } from "lucide-react";

import { ChipSetCheck } from "@/components/chip-set-check";
import { Button } from "@/components/ui/button";
import { Calendar } from "@/components/ui/calendar";
import {
    Combobox,
    ComboboxChip,
    ComboboxChips,
    ComboboxChipsInput,
    ComboboxContent,
    ComboboxEmpty,
    ComboboxItem,
    ComboboxList,
    ComboboxValue,
    useComboboxAnchor,
} from "@/components/ui/combobox";
import { Field, FieldError, FieldLabel } from "@/components/ui/field";
import { Input } from "@/components/ui/input";
import { InputGroupAddon } from "@/components/ui/input-group";
import { Popover, PopoverContent, PopoverTrigger } from "@/components/ui/popover";
import { Textarea } from "@/components/ui/textarea";
import { isSameLocalDay, parseDateTimeLocal, withLocalDate, withLocalTime } from "@/lib/transactions";
import { markFormDirty } from "@/lib/utils";
import { Tag } from "@/types/app/modules/core/finance/models/team/tag";

/**
 * The chips a transaction and a transfer share below their amounts: when it
 * happened, the note, and the tags. Each writes a hidden input in React, so
 * every change marks the surrounding Inertia form dirty.
 */

type ChipProps = {
    /** The field group the chip sits in, used to find the form to mark dirty. */
    fields: React.RefObject<HTMLDivElement | null>;
    /** Prefix for the control ids, so two forms never collide. */
    idPrefix: string;
};

/**
 * The date and time the movement happened.
 */
export const DateChip = ({
    fields,
    idPrefix,
    occurredAt,
    onOccurredAtChange,
}: ChipProps & {
    occurredAt: string;
    onOccurredAtChange: (occurredAt: string) => void;
}) => {
    /**
     * Translate the provided string
     * to the current locale.
     */
    const { currentLocale, t } = useLaravelReactI18n();

    /**
     * The occurred at date.
     */
    const occurredAtDate = parseDateTimeLocal(occurredAt);

    return (
        <Popover>
            <PopoverTrigger
                render={<Button className="w-full min-w-0 justify-start max-sm:h-10" type="button" variant="outline" />}
            >
                <CalendarIcon data-icon="inline-start" />
                <span className="min-w-0 truncate">
                    {isSameLocalDay(occurredAtDate, new Date())
                        ? t("Today")
                        : new Intl.DateTimeFormat(currentLocale(), { dateStyle: "medium" }).format(occurredAtDate)}
                </span>
                <ChipSetCheck show={!isSameLocalDay(occurredAtDate, new Date())} />
            </PopoverTrigger>
            <PopoverContent align="start" className="w-auto">
                <Calendar
                    mode="single"
                    onSelect={(date) => {
                        if (!date) {
                            return;
                        }

                        onOccurredAtChange(withLocalDate(occurredAt, date));
                        markFormDirty(fields.current);
                    }}
                    selected={occurredAtDate}
                />
                <Field>
                    <FieldLabel htmlFor={`${idPrefix}-occurred-at-time`}>{t("Time")}</FieldLabel>
                    <Input
                        id={`${idPrefix}-occurred-at-time`}
                        onChange={(event) => {
                            onOccurredAtChange(withLocalTime(occurredAt, event.target.value));
                            markFormDirty(fields.current);
                        }}
                        type="time"
                        value={`${String(occurredAtDate.getHours()).padStart(2, "0")}:${String(occurredAtDate.getMinutes()).padStart(2, "0")}`}
                    />
                </Field>
            </PopoverContent>
        </Popover>
    );
};

/**
 * The free-text note on the movement.
 */
export const NotesChip = ({
    description,
    error,
    fields,
    idPrefix,
    invalid,
    onDescriptionChange,
}: ChipProps & {
    description: string;
    error?: string;
    invalid: boolean;
    onDescriptionChange: (description: string) => void;
}) => {
    /**
     * Translate the provided string
     * to the current locale.
     */
    const t = useLaravelReactI18n().t;

    return (
        <Popover>
            <PopoverTrigger
                className="w-full min-w-0"
                render={
                    <Button
                        aria-invalid={invalid}
                        className="w-full min-w-0 justify-start max-sm:h-10"
                        type="button"
                        variant="outline"
                    />
                }
            >
                <StickyNoteIcon data-icon="inline-start" />
                <span className="min-w-0 truncate">{t("Notes")}</span>
                <ChipSetCheck show={description.trim() !== ""} />
            </PopoverTrigger>
            <PopoverContent align="start" className="w-80">
                <Field data-invalid={invalid || undefined}>
                    <FieldLabel htmlFor={`${idPrefix}-description`}>{t("Notes")}</FieldLabel>
                    <Textarea
                        aria-invalid={invalid}
                        id={`${idPrefix}-description`}
                        onChange={(event) => {
                            onDescriptionChange(event.target.value);
                            markFormDirty(fields.current);
                        }}
                        placeholder={t("Enter a description")}
                        value={description}
                    />
                    {invalid && <FieldError>{error}</FieldError>}
                </Field>
            </PopoverContent>
        </Popover>
    );
};

/**
 * The tags attached to the movement.
 */
export const TagsChip = ({
    error,
    fields,
    idPrefix,
    invalid,
    onSelectedTagsChange,
    selectedTags,
    tags,
}: ChipProps & {
    error?: string;
    invalid: boolean;
    onSelectedTagsChange: (tags: Array<Tag>) => void;
    selectedTags: Array<Tag>;
    tags: Array<Tag>;
}) => {
    /**
     * Translate the provided string
     * to the current locale.
     */
    const t = useLaravelReactI18n().t;

    /**
     * Anchor the tags dropdown to the full chips field.
     */
    const tagsAnchor = useComboboxAnchor();

    return (
        <Popover
            onOpenChange={(open, details) => {
                if (
                    !open &&
                    details.reason === "outside-press" &&
                    details.event.target instanceof Element &&
                    details.event.target.closest("[data-slot=combobox-content]")
                ) {
                    details.cancel();
                }
            }}
        >
            <PopoverTrigger
                className="w-full min-w-0"
                render={
                    <Button
                        aria-invalid={invalid}
                        className="w-full min-w-0 justify-start max-sm:h-10"
                        type="button"
                        variant="outline"
                    />
                }
            >
                <TagIcon data-icon="inline-start" />
                <span className="min-w-0 truncate">{t("Tags")}</span>
                <ChipSetCheck show={selectedTags.length > 0} />
            </PopoverTrigger>
            <PopoverContent align="end" className="w-80">
                <Field data-invalid={invalid || undefined}>
                    <FieldLabel htmlFor={`${idPrefix}-tags`}>{t("Tags")}</FieldLabel>
                    <Combobox<Tag, true>
                        isItemEqualToValue={(left, right) => left.id === right.id}
                        items={tags}
                        itemToStringLabel={(item) => item.name}
                        itemToStringValue={(item) => item.id}
                        multiple
                        onValueChange={(value) => {
                            onSelectedTagsChange(value);
                            markFormDirty(fields.current);
                        }}
                        value={selectedTags}
                    >
                        <ComboboxChips aria-invalid={invalid} className="w-full" ref={tagsAnchor}>
                            <InputGroupAddon>
                                <TagIcon />
                            </InputGroupAddon>
                            <ComboboxValue>
                                {selectedTags.map((tag) => (
                                    <ComboboxChip key={tag.id}>{tag.name}</ComboboxChip>
                                ))}
                            </ComboboxValue>
                            <ComboboxChipsInput
                                id={`${idPrefix}-tags`}
                                placeholder={selectedTags.length > 0 ? undefined : t("Select tags")}
                            />
                        </ComboboxChips>
                        <ComboboxContent anchor={tagsAnchor}>
                            <ComboboxEmpty>{t("No tags found")}</ComboboxEmpty>
                            <ComboboxList>
                                {(tag) => (
                                    <ComboboxItem key={tag.id} value={tag}>
                                        {tag.name}
                                    </ComboboxItem>
                                )}
                            </ComboboxList>
                        </ComboboxContent>
                    </Combobox>
                    {invalid && <FieldError>{error}</FieldError>}
                </Field>
            </PopoverContent>
        </Popover>
    );
};
