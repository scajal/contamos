import { type FormComponentSlotProps } from "@inertiajs/core";
import { useLaravelReactI18n } from "laravel-react-i18n";
import { CalendarIcon, CheckIcon, DollarSignIcon, ListIcon, StickyNoteIcon, TagIcon } from "lucide-react";
import { useEffect, useRef, useState } from "react";

import { Button } from "@/components/ui/button";
import { Calendar } from "@/components/ui/calendar";
import {
    Combobox,
    ComboboxChip,
    ComboboxChips,
    ComboboxChipsInput,
    ComboboxContent,
    ComboboxEmpty,
    ComboboxInput,
    ComboboxItem,
    ComboboxList,
    ComboboxTrigger,
    ComboboxValue,
    useComboboxAnchor,
} from "@/components/ui/combobox";
import { Field, FieldDescription, FieldError, FieldGroup, FieldLabel } from "@/components/ui/field";
import { Input } from "@/components/ui/input";
import {
    InputGroup,
    InputGroupAddon,
    InputGroupButton,
    InputGroupInput,
    InputGroupText,
} from "@/components/ui/input-group";
import { Popover, PopoverContent, PopoverTrigger } from "@/components/ui/popover";
import { Textarea } from "@/components/ui/textarea";
import { ToggleGroup, ToggleGroupItem } from "@/components/ui/toggle-group";
import { cn, markFormDirty } from "@/lib/utils";
import { Currency } from "@/types/app/modules/core/catalog/models/currency";
import { Quote } from "@/types/app/modules/core/catalog/models/currency/quote";
import { TransactionType } from "@/types/app/modules/core/finance/enums/transaction/transaction-type";
import { Account } from "@/types/app/modules/core/finance/models/team/account";
import { Tag } from "@/types/app/modules/core/finance/models/team/tag";
import { Transaction } from "@/types/app/modules/core/finance/models/team/transaction";

type FormProps = Partial<
    Omit<Transaction, "account" | "currency" | "tags" | "team" | "user"> & { tag_ids: Array<string> }
>;

type Props = FormComponentSlotProps<FormProps> & {
    accounts: Array<Account>;
    defaultValues?: FormProps;
    onTypeChange: (type: TransactionType) => void;
    tags: Array<Tag>;
    type: TransactionType;
};

/**
 * Format a Date as a datetime-local input value (YYYY-MM-DDTHH:mm).
 */
function toDateTimeLocalValue(value?: string): string {
    const date = value === undefined ? new Date() : parseDateTimeLocal(value);
    const resolved = Number.isNaN(date.getTime()) ? new Date() : date;
    const pad = (part: number) => String(part).padStart(2, "0");

    return `${resolved.getFullYear()}-${pad(resolved.getMonth() + 1)}-${pad(resolved.getDate())}T${pad(resolved.getHours())}:${pad(resolved.getMinutes())}`;
}

/**
 * Parse a datetime-local string as wall-clock time in the browser timezone.
 */
function parseDateTimeLocal(value: string): Date {
    const [datePart, timePart = "00:00"] = value.split("T");
    const [year, month, day] = datePart.split("-").map(Number);
    const [hour, minute] = timePart.split(":").map(Number);

    return new Date(year, (month ?? 1) - 1, day ?? 1, hour ?? 0, minute ?? 0);
}

/**
 * Whether the two dates are the same day in the local timezone.
 */
function isSameLocalDay(left: Date, right: Date): boolean {
    return (
        left.getFullYear() === right.getFullYear() &&
        left.getMonth() === right.getMonth() &&
        left.getDate() === right.getDate()
    );
}

/**
 * Set the date of the occurred at value to the provided date.
 */
function withLocalDate(occurredAt: string, date: Date): string {
    const current = parseDateTimeLocal(occurredAt);
    const pad = (part: number) => String(part).padStart(2, "0");

    return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}T${pad(current.getHours())}:${pad(current.getMinutes())}`;
}

/**
 * Set the time of the occurred at value to the provided time.
 */
function withLocalTime(occurredAt: string, time: string): string {
    const [datePart] = occurredAt.split("T");

    return `${datePart}T${time || "00:00"}`;
}

/**
 * Get the quote for the currency at the provided date.
 */
function quoteAsOf(currency: Currency | null, at: Date): Quote | null {
    if (!currency) {
        return null;
    }

    const atMs = at.getTime();
    let best: Quote | null = null;
    let bestMs = Number.NEGATIVE_INFINITY;

    for (const quote of currency.quotes ?? []) {
        const observedMs = new Date(quote.observed_at).getTime();

        if (Number.isNaN(observedMs) || observedMs > atMs) {
            continue;
        }

        if (observedMs >= bestMs) {
            best = quote;
            bestMs = observedMs;
        }
    }

    return best;
}

/**
 * Strip padded decimal:18 zeros so a catalog rate can sit in an input.
 */
function trimTrailingZeros(value: string): string {
    if (!value.includes(".")) {
        return value;
    }

    return value.replace(/(\.\d*?)0+$/, "$1").replace(/\.$/, "");
}

/**
 * Right-aligned check on a chip whose popover holds a non-default value.
 */
function ChipSetCheck({ show }: { show: boolean }) {
    if (!show) {
        return null;
    }

    return <CheckIcon aria-hidden className="text-success ml-auto size-3.5 shrink-0" data-icon="inline-end" />;
}

export const Fields = ({ accounts, defaultValues, errors, invalid, onTypeChange, tags, type }: Props) => {
    /**
     * Translate the provided string
     * to the current locale.
     */
    const { currentLocale, t } = useLaravelReactI18n();

    /**
     * The currently selected account, used to derive
     * which currencies the combobox may offer.
     */
    const [account, setAccount] = useState<Account | null>(
        () =>
            accounts.find((candidate) => candidate.id === defaultValues?.account_id) ??
            (accounts.length === 1 ? accounts[0] : null),
    );

    /**
     * The currently selected currency, used to show its
     * code in the trigger.
     */
    const [currency, setCurrency] = useState<Currency | null>(() => {
        const selected =
            accounts.find((candidate) => candidate.id === defaultValues?.account_id) ??
            (accounts.length === 1 ? accounts[0] : null);
        const currencies = selected?.currencies ?? [];
        return (
            currencies.find((candidate) => candidate.id === defaultValues?.currency_id) ??
            (currencies.length === 1 ? currencies[0] : null)
        );
    });

    /**
     * The tags currently selected for the transaction.
     */
    const [selectedTags, setSelectedTags] = useState<Array<Tag>>(() =>
        tags.filter((tag) => defaultValues?.tag_ids?.includes(tag.id)),
    );

    /**
     * The occurred-at wall-clock value submitted as datetime-local.
     */
    const [occurredAt, setOccurredAt] = useState(() => toDateTimeLocalValue(defaultValues?.occurred_at));

    /**
     * The note, kept when the popover closes.
     */
    const [description, setDescription] = useState(defaultValues?.description ?? "");

    /**
     * Whether the rate popover is open.
     */
    const [quoteOpen, setQuoteOpen] = useState(false);

    /**
     * Whether the user typed a rate that must not be replaced
     * when the date or currency changes.
     */
    const [quoteOverridden, setQuoteOverridden] = useState(() => Boolean(defaultValues?.quote));

    /**
     * The rate the user typed, when overriding or filling a gap.
     */
    const [quoteValue, setQuoteValue] = useState(() =>
        defaultValues?.quote ? trimTrailingZeros(String(defaultValues.quote)) : "",
    );

    /**
     * Anchor the tags dropdown to the full chips field.
     */
    const tagsAnchor = useComboboxAnchor();

    /**
     * The fields ref.
     */
    const fields = useRef<HTMLDivElement>(null);

    /**
     * The occurred at date.
     */
    const occurredAtDate = parseDateTimeLocal(occurredAt);

    /**
     * The quote for the currency at the occurred at date.
     */
    const asOfQuote = quoteAsOf(currency, occurredAtDate);

    /**
     * Whether the quote is missing.
     */
    const quoteMissing = currency !== null && asOfQuote === null;

    /**
     * Prefill the rate editor with the catalog rate when the user
     * has not typed one yet.
     */
    const prefillQuoteValue = (): void => {
        if (asOfQuote && quoteValue.trim() === "") {
            setQuoteValue(trimTrailingZeros(asOfQuote.rate));
        }
    };

    /**
     * The date label for the quote.
     */
    const quoteDateLabel = asOfQuote
        ? new Intl.DateTimeFormat(currentLocale(), { dateStyle: "medium" }).format(new Date(asOfQuote.observed_at))
        : new Intl.DateTimeFormat(currentLocale(), { dateStyle: "medium" }).format(occurredAtDate);

    // Drop a catalog prefill when the currency or date changes, and
    // open the rate popover when no observation exists for that instant.
    useEffect(() => {
        if (!quoteOverridden) {
            setQuoteValue("");
        }
    }, [currency?.id, occurredAt, quoteOverridden]);

    useEffect(() => {
        if (quoteMissing) {
            setQuoteOpen(true);
        }
    }, [quoteMissing]);

    return (
        <FieldGroup className="gap-5" ref={fields}>
            {/** Type */}
            <input name="type" type="hidden" value={type} />
            <Field>
                <FieldLabel className="sr-only" id="transaction-type-label">
                    {t("Type")}
                </FieldLabel>
                <ToggleGroup
                    aria-labelledby="transaction-type-label"
                    className="w-full"
                    onValueChange={(value) => {
                        const nextType = value[0];

                        if (nextType === "income" || nextType === "expense") {
                            onTypeChange(nextType);
                        }
                    }}
                    spacing={0}
                    value={[type]}
                    variant="outline"
                >
                    <ToggleGroupItem className="flex-1" value="income">
                        <span className="text-success">+</span>
                        {t("enums.income")}
                    </ToggleGroupItem>
                    <ToggleGroupItem className="flex-1" value="expense">
                        <span className="text-destructive">−</span>
                        {t("enums.expense")}
                    </ToggleGroupItem>
                </ToggleGroup>
            </Field>

            {/** Account */}
            {accounts.length === 1 ? (
                <input name="account_id" type="hidden" value={account?.id ?? ""} />
            ) : (
                <Field data-invalid={invalid("account_id") || undefined}>
                    <FieldLabel>{t("Account")}</FieldLabel>
                    <Combobox<Account>
                        items={accounts}
                        itemToStringLabel={(item) => item.name}
                        itemToStringValue={(item) => item.id}
                        name="account_id"
                        onValueChange={(value) => {
                            setAccount(value);
                            const currencies = value?.currencies ?? [];
                            setCurrency(currencies.length === 1 ? currencies[0] : null);
                            markFormDirty(fields.current);
                        }}
                        value={account}
                    >
                        <ComboboxInput aria-invalid={invalid("account_id")} placeholder={t("Select an account")}>
                            <InputGroupAddon>
                                <ListIcon />
                            </InputGroupAddon>
                            <ComboboxContent alignOffset={-28}>
                                <ComboboxList>
                                    {(item) => (
                                        <ComboboxItem key={item.id} value={item}>
                                            {item.name}
                                        </ComboboxItem>
                                    )}
                                </ComboboxList>
                            </ComboboxContent>
                        </ComboboxInput>
                    </Combobox>
                    {invalid("account_id") && <FieldError>{errors.account_id}</FieldError>}
                </Field>
            )}

            {/** Amount & Currency */}
            <Field data-invalid={invalid("amount") || invalid("currency_id") || undefined}>
                <FieldLabel htmlFor="transaction-amount">{t("Amount")}</FieldLabel>
                <Combobox<Currency>
                    key={account?.id ?? "none"}
                    disabled={!account}
                    items={account?.currencies ?? []}
                    itemToStringLabel={(item) => item.code}
                    itemToStringValue={(item) => item.id}
                    name="currency_id"
                    onValueChange={(value) => {
                        setCurrency(value);
                        markFormDirty(fields.current);
                    }}
                    value={currency}
                >
                    <InputGroup className="h-auto min-h-14 items-center">
                        <InputGroupAddon>
                            <InputGroupButton
                                aria-invalid={invalid("currency_id")}
                                disabled={!account}
                                render={<ComboboxTrigger />}
                                size="xs"
                                variant="ghost"
                            >
                                <ComboboxValue placeholder={t("Currency")} />
                            </InputGroupButton>
                        </InputGroupAddon>
                        <InputGroupInput
                            aria-invalid={invalid("amount")}
                            autoComplete="off"
                            autoFocus
                            className={cn(
                                "h-auto py-2 text-right text-3xl font-semibold tabular-nums md:text-3xl",
                                type === "income" ? "text-success" : "text-destructive",
                            )}
                            defaultValue={defaultValues?.amount}
                            disabled={!account}
                            id="transaction-amount"
                            inputMode="decimal"
                            name="amount"
                            placeholder="0"
                            required
                            spellCheck={false}
                            type="text"
                        />
                    </InputGroup>
                    <ComboboxContent>
                        <ComboboxList>
                            {(item) => (
                                <ComboboxItem key={item.id} value={item}>
                                    {item.code}
                                </ComboboxItem>
                            )}
                        </ComboboxList>
                    </ComboboxContent>
                </Combobox>
                {invalid("amount") && <FieldError>{errors.amount}</FieldError>}
                {invalid("currency_id") && <FieldError>{errors.currency_id}</FieldError>}
            </Field>

            {/** Date, note, tags, rate */}
            <input name="occurred_at" type="hidden" value={occurredAt} />
            <input name="description" type="hidden" value={description} />
            {selectedTags.map((tag) => (
                <input key={tag.id} name="tag_ids[]" type="hidden" value={tag.id} />
            ))}
            {quoteMissing || quoteOverridden ? <input name="quote" type="hidden" value={quoteValue} /> : null}
            <div className="flex flex-col gap-2">
                <div className="grid min-w-0 grid-cols-[auto_minmax(0,1fr)_minmax(0,1fr)] gap-2">
                    <Popover>
                        <PopoverTrigger
                            render={
                                <Button
                                    className="max-w-full min-w-0 justify-start"
                                    size="sm"
                                    type="button"
                                    variant="outline"
                                />
                            }
                        >
                            <CalendarIcon data-icon="inline-start" />
                            <span className="min-w-0 truncate">
                                {isSameLocalDay(occurredAtDate, new Date())
                                    ? t("Today")
                                    : new Intl.DateTimeFormat(currentLocale(), { dateStyle: "medium" }).format(
                                          occurredAtDate,
                                      )}
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

                                    setOccurredAt(withLocalDate(occurredAt, date));
                                    markFormDirty(fields.current);
                                }}
                                selected={occurredAtDate}
                            />
                            <Field>
                                <FieldLabel htmlFor="transaction-occurred-at-time">{t("Time")}</FieldLabel>
                                <Input
                                    id="transaction-occurred-at-time"
                                    onChange={(event) => {
                                        setOccurredAt(withLocalTime(occurredAt, event.target.value));
                                        markFormDirty(fields.current);
                                    }}
                                    type="time"
                                    value={`${String(occurredAtDate.getHours()).padStart(2, "0")}:${String(occurredAtDate.getMinutes()).padStart(2, "0")}`}
                                />
                            </Field>
                        </PopoverContent>
                    </Popover>
                    <Popover>
                        <PopoverTrigger
                            className="w-full min-w-0"
                            render={
                                <Button
                                    aria-invalid={invalid("description")}
                                    className="w-full min-w-0 justify-start"
                                    size="sm"
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
                            <Field data-invalid={invalid("description") || undefined}>
                                <FieldLabel htmlFor="transaction-description">{t("Notes")}</FieldLabel>
                                <Textarea
                                    aria-invalid={invalid("description")}
                                    id="transaction-description"
                                    onChange={(event) => {
                                        setDescription(event.target.value);
                                        markFormDirty(fields.current);
                                    }}
                                    placeholder={t("Enter a description")}
                                    value={description}
                                />
                                {invalid("description") && <FieldError>{errors.description}</FieldError>}
                            </Field>
                        </PopoverContent>
                    </Popover>
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
                                    aria-invalid={invalid("tag_ids")}
                                    className="w-full min-w-0 justify-start"
                                    size="sm"
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
                            <Field data-invalid={invalid("tag_ids") || undefined}>
                                <FieldLabel htmlFor="transaction-tags">{t("Tags")}</FieldLabel>
                                <Combobox<Tag, true>
                                    isItemEqualToValue={(left, right) => left.id === right.id}
                                    items={tags}
                                    itemToStringLabel={(item) => item.name}
                                    itemToStringValue={(item) => item.id}
                                    multiple
                                    onValueChange={(value) => {
                                        setSelectedTags(value);
                                        markFormDirty(fields.current);
                                    }}
                                    value={selectedTags}
                                >
                                    <ComboboxChips
                                        aria-invalid={invalid("tag_ids")}
                                        className="w-full"
                                        ref={tagsAnchor}
                                    >
                                        <InputGroupAddon>
                                            <TagIcon />
                                        </InputGroupAddon>
                                        <ComboboxValue>
                                            {selectedTags.map((tag) => (
                                                <ComboboxChip key={tag.id}>{tag.name}</ComboboxChip>
                                            ))}
                                        </ComboboxValue>
                                        <ComboboxChipsInput
                                            id="transaction-tags"
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
                                {invalid("tag_ids") && <FieldError>{errors.tag_ids}</FieldError>}
                            </Field>
                        </PopoverContent>
                    </Popover>
                </div>
                {currency ? (
                    <Popover
                        onOpenChange={(open) => {
                            if (open) {
                                prefillQuoteValue();
                            }

                            setQuoteOpen(open);
                        }}
                        open={quoteOpen}
                    >
                        <PopoverTrigger
                            render={
                                <Button
                                    aria-invalid={invalid("quote") || (quoteMissing && quoteValue.trim() === "")}
                                    className="max-w-full min-w-0 justify-start"
                                    size="sm"
                                    type="button"
                                    variant="outline"
                                />
                            }
                        >
                            <DollarSignIcon data-icon="inline-start" />
                            <span className="min-w-0 truncate">{t("Rate")}</span>
                            <ChipSetCheck show={quoteOverridden} />
                        </PopoverTrigger>
                        <PopoverContent align="start" className="w-80">
                            <Field data-invalid={invalid("quote") || undefined}>
                                <FieldLabel htmlFor="transaction-quote">
                                    {t("1 US dollar = :code", { code: currency.code })}
                                </FieldLabel>
                                <InputGroup>
                                    <InputGroupAddon>
                                        <InputGroupText>{currency.code}</InputGroupText>
                                    </InputGroupAddon>
                                    <InputGroupInput
                                        aria-invalid={invalid("quote")}
                                        autoComplete="off"
                                        className="text-right tabular-nums"
                                        id="transaction-quote"
                                        inputMode="decimal"
                                        onChange={(event) => {
                                            setQuoteValue(event.target.value);
                                            setQuoteOverridden(event.target.value.trim() !== "");
                                            markFormDirty(fields.current);
                                        }}
                                        placeholder="0"
                                        spellCheck={false}
                                        type="text"
                                        value={quoteValue}
                                    />
                                </InputGroup>
                                <FieldDescription>
                                    {quoteMissing
                                        ? t("No rate for :date. We need it to save.", { date: quoteDateLabel })
                                        : t("Rate of :date", { date: quoteDateLabel })}
                                </FieldDescription>
                                {invalid("quote") && <FieldError>{errors.quote}</FieldError>}
                            </Field>
                        </PopoverContent>
                    </Popover>
                ) : null}
            </div>
            {invalid("occurred_at") && <FieldError>{errors.occurred_at}</FieldError>}
            {invalid("description") && <FieldError>{errors.description}</FieldError>}
            {invalid("tag_ids") && <FieldError>{errors.tag_ids}</FieldError>}
            {invalid("quote") && <FieldError>{errors.quote}</FieldError>}
        </FieldGroup>
    );
};
