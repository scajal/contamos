import { type FormComponentSlotProps } from "@inertiajs/core";
import { useLaravelReactI18n } from "laravel-react-i18n";
import { DollarSignIcon, ListIcon } from "lucide-react";
import { useEffect, useRef, useState } from "react";

import { ChipSetCheck } from "@/components/chip-set-check";
import { DateChip, NotesChip, TagsChip } from "@/components/movement-chips";
import { Button } from "@/components/ui/button";
import {
    Combobox,
    ComboboxContent,
    ComboboxInput,
    ComboboxItem,
    ComboboxList,
    ComboboxTrigger,
    ComboboxValue,
} from "@/components/ui/combobox";
import { Field, FieldDescription, FieldError, FieldGroup, FieldLabel } from "@/components/ui/field";
import {
    InputGroup,
    InputGroupAddon,
    InputGroupButton,
    InputGroupInput,
    InputGroupText,
} from "@/components/ui/input-group";
import { Popover, PopoverContent, PopoverTrigger } from "@/components/ui/popover";
import { ToggleGroup, ToggleGroupItem } from "@/components/ui/toggle-group";
import {
    TRANSACTION_AMOUNT_COLOR,
    parseDateTimeLocal,
    quoteAsOf,
    toDateTimeLocalValue,
    trimTrailingZeros,
} from "@/lib/transactions";
import { cn, markFormDirty } from "@/lib/utils";
import { Currency } from "@/types/app/modules/core/catalog/models/currency";
import { TransactionType } from "@/types/app/modules/core/finance/enums/transaction/transaction-type";
import { Account } from "@/types/app/modules/core/finance/models/team/account";
import { Tag } from "@/types/app/modules/core/finance/models/team/tag";
import { Transaction } from "@/types/app/modules/core/finance/models/team/transaction";

type FormProps = Partial<
    Omit<Transaction, "account" | "currency" | "tags" | "team" | "transfer" | "user"> & { tag_ids: Array<string> }
>;

type Props = FormComponentSlotProps<FormProps> & {
    accounts: Array<Account>;
    defaultValues?: FormProps;
    onTypeChange: (type: TransactionType) => void;
    tags: Array<Tag>;
    trackTypeChanges?: boolean;
    type: TransactionType;
};

export const Fields = ({
    accounts,
    defaultValues,
    errors,
    invalid,
    onTypeChange,
    tags,
    trackTypeChanges = false,
    type,
}: Props) => {
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
        if (quoteMissing && quoteValue.trim() === "") {
            setQuoteOpen(true);
        }
    }, [quoteMissing, quoteValue]);

    return (
        <FieldGroup className="gap-5" ref={fields}>
            {/** Type */}
            <input name="type" type="hidden" value={type} />
            {type === "income" || type === "expense" ? (
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

                                if (trackTypeChanges) {
                                    markFormDirty(fields.current);
                                }
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
            ) : (
                <p className={cn("text-sm font-medium", TRANSACTION_AMOUNT_COLOR[type])}>{t(`enums.${type}`)}</p>
            )}

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
                                TRANSACTION_AMOUNT_COLOR[type],
                            )}
                            defaultValue={defaultValues?.amount ? trimTrailingZeros(defaultValues.amount) : undefined}
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
                    <DateChip
                        fields={fields}
                        idPrefix="transaction"
                        occurredAt={occurredAt}
                        onOccurredAtChange={setOccurredAt}
                    />
                    <NotesChip
                        description={description}
                        error={errors.description}
                        fields={fields}
                        idPrefix="transaction"
                        invalid={invalid("description")}
                        onDescriptionChange={setDescription}
                    />
                    <TagsChip
                        error={errors.tag_ids}
                        fields={fields}
                        idPrefix="transaction"
                        invalid={invalid("tag_ids")}
                        onSelectedTagsChange={setSelectedTags}
                        selectedTags={selectedTags}
                        tags={tags}
                    />
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
