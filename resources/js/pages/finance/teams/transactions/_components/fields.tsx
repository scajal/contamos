import { type FormComponentSlotProps } from "@inertiajs/core";
import { usePage } from "@inertiajs/react";
import { ModalLink } from "@inertiaui/modal-react";
import { useLaravelReactI18n } from "laravel-react-i18n";
import { DollarSignIcon, ListIcon, LockIcon, RepeatIcon } from "lucide-react";
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
import subscriptions from "@/routes/billing/teams/subscriptions";
import { Currency } from "@/types/app/modules/core/catalog/models/currency";
import { TransactionType } from "@/types/app/modules/core/finance/enums/transaction/transaction-type";
import { Account } from "@/types/app/modules/core/finance/models/team/account";
import { Tag } from "@/types/app/modules/core/finance/models/team/tag";
import { Transaction } from "@/types/app/modules/core/finance/models/team/transaction";

import { RepeatChip, RepeatValue } from "./repeat-chip";

/**
 * What a team on a plan without recurring transactions sees in place
 * of the Repeat chip: the feature is named rather than hidden, and the
 * chip opens the upgrade prompt.
 */
const RepeatUpgradeChip = () => {
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
            className="text-muted-foreground w-full min-w-0 justify-start max-sm:h-10"
            nativeButton={false}
            render={
                <ModalLink
                    href={subscriptions.upgrade.url(
                        { team: user?.current_team?.slug ?? "" },
                        { query: { feature: "transactions:recur" } },
                    )}
                >
                    <RepeatIcon data-icon="inline-start" />
                    <span className="min-w-0 truncate">{t("Repeat")}</span>
                    <LockIcon className="ml-auto size-3.5 shrink-0" data-icon="inline-end" />
                </ModalLink>
            }
            variant="outline"
        />
    );
};

type FormProps = Partial<
    Omit<Transaction, "account" | "currency" | "recurringRule" | "tags" | "team" | "transfer" | "user"> & {
        recurring_ends_on: string | null;
        recurring_frequency: string;
        recurring_interval: string;
        repeat: boolean;
        tag_ids: Array<string>;
    }
>;

type Props = FormComponentSlotProps<FormProps> & {
    accounts: Array<Account>;
    allowRepeat?: boolean;
    canRepeat?: boolean;
    defaultValues?: FormProps;
    onTypeChange: (type: TransactionType) => void;
    tags: Array<Tag>;
    trackTypeChanges?: boolean;
    type: TransactionType;
};

export const Fields = ({
    accounts,
    allowRepeat = false,
    canRepeat = false,
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
     * The recurring schedule, when the transaction repeats.
     */
    const [repeat, setRepeat] = useState<RepeatValue>({
        ends_on: null,
        frequency: "monthly",
        interval: "1",
        repeat: false,
    });

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
            {allowRepeat && canRepeat && (
                <>
                    <input name="repeat" type="hidden" value={repeat.repeat ? "1" : "0"} />
                    {repeat.repeat && (
                        <>
                            <input name="recurring_frequency" type="hidden" value={repeat.frequency} />
                            <input name="recurring_interval" type="hidden" value={repeat.interval} />
                            <input name="recurring_ends_on" type="hidden" value={repeat.ends_on ?? ""} />
                        </>
                    )}
                </>
            )}
            <div className="grid min-w-0 grid-cols-6 gap-2">
                <div className="col-span-2 min-w-0">
                    <DateChip
                        fields={fields}
                        idPrefix="transaction"
                        occurredAt={occurredAt}
                        onOccurredAtChange={setOccurredAt}
                    />
                </div>
                <div className="col-span-2 min-w-0">
                    <NotesChip
                        description={description}
                        error={errors.description}
                        fields={fields}
                        idPrefix="transaction"
                        invalid={invalid("description")}
                        onDescriptionChange={setDescription}
                    />
                </div>
                <div className="col-span-2 min-w-0">
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
                {allowRepeat && (
                    <div className={cn("min-w-0", currency ? "col-span-3" : "col-span-6")}>
                        {canRepeat ? (
                            <RepeatChip
                                errors={{
                                    ends_on: errors.recurring_ends_on,
                                    frequency: errors.recurring_frequency,
                                    interval: errors.recurring_interval,
                                }}
                                fields={fields}
                                invalid={invalid}
                                onChange={setRepeat}
                                value={repeat}
                            />
                        ) : (
                            <RepeatUpgradeChip />
                        )}
                    </div>
                )}
                {currency ? (
                    <div className={cn("min-w-0", allowRepeat ? "col-span-3" : "col-span-6")}>
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
                                        className="w-full min-w-0 justify-start max-sm:h-10"
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
                    </div>
                ) : null}
            </div>
            {invalid("occurred_at") && <FieldError>{errors.occurred_at}</FieldError>}
            {invalid("description") && <FieldError>{errors.description}</FieldError>}
            {invalid("tag_ids") && <FieldError>{errors.tag_ids}</FieldError>}
            {invalid("quote") && <FieldError>{errors.quote}</FieldError>}
        </FieldGroup>
    );
};
