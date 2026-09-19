import { type FormComponentSlotProps } from "@inertiajs/core";
import { useLaravelReactI18n } from "laravel-react-i18n";
import { ArrowDownIcon, DollarSignIcon, ListIcon } from "lucide-react";
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
import { parseDateTimeLocal, quoteAsOf, toDateTimeLocalValue, trimTrailingZeros } from "@/lib/transactions";
import { markFormDirty } from "@/lib/utils";
import { Currency } from "@/types/app/modules/core/catalog/models/currency";
import { Account } from "@/types/app/modules/core/finance/models/team/account";
import { Tag } from "@/types/app/modules/core/finance/models/team/tag";

/**
 * The two sides of a transfer.
 */
type Side = "origin" | "destination";

export type TransferFormValues = {
    amount_received?: string;
    amount_sent?: string;
    description?: string;
    destination_account_id?: string;
    destination_currency_id?: string;
    destination_fee?: string;
    destination_quote?: string;
    occurred_at?: string;
    origin_account_id?: string;
    origin_currency_id?: string;
    origin_fee?: string;
    origin_quote?: string;
    tag_ids?: Array<string>;
};

type Props = FormComponentSlotProps<TransferFormValues> & {
    accounts: Array<Account>;
    defaultValues?: TransferFormValues;
    tags: Array<Tag>;
};

/**
 * Resolve the account of one side out of the submitted defaults.
 */
function initialAccount(accounts: Array<Account>, accountId?: string): Account | null {
    return accounts.find((candidate) => candidate.id === accountId) ?? null;
}

/**
 * Resolve the currency of one side out of the submitted defaults, falling
 * back to the account's only currency.
 */
function initialCurrency(account: Account | null, currencyId?: string): Currency | null {
    const currencies = account?.currencies ?? [];

    return (
        currencies.find((candidate) => candidate.id === currencyId) ?? (currencies.length === 1 ? currencies[0] : null)
    );
}

export const Fields = ({ accounts, defaultValues, errors, invalid, tags }: Props) => {
    /**
     * Translate the provided string
     * to the current locale.
     */
    const t = useLaravelReactI18n().t;

    /**
     * The account the money leaves from.
     */
    const [originAccount, setOriginAccount] = useState<Account | null>(() =>
        initialAccount(accounts, defaultValues?.origin_account_id),
    );

    /**
     * The account the money arrives at.
     */
    const [destinationAccount, setDestinationAccount] = useState<Account | null>(() =>
        initialAccount(accounts, defaultValues?.destination_account_id),
    );

    /**
     * The currency the money leaves in.
     */
    const [originCurrency, setOriginCurrency] = useState<Currency | null>(() =>
        initialCurrency(initialAccount(accounts, defaultValues?.origin_account_id), defaultValues?.origin_currency_id),
    );

    /**
     * The currency the money arrives in.
     */
    const [destinationCurrency, setDestinationCurrency] = useState<Currency | null>(() =>
        initialCurrency(
            initialAccount(accounts, defaultValues?.destination_account_id),
            defaultValues?.destination_currency_id,
        ),
    );

    /**
     * The amount leaving the origin account.
     */
    const [amountSent, setAmountSent] = useState(() =>
        defaultValues?.amount_sent ? trimTrailingZeros(defaultValues.amount_sent) : "",
    );

    /**
     * The amount arriving at the destination account.
     */
    const [amountReceived, setAmountReceived] = useState(() =>
        defaultValues?.amount_received ? trimTrailingZeros(defaultValues.amount_received) : "",
    );

    /**
     * Whether the user typed an amount received, which stops it from
     * mirroring the amount sent.
     */
    const [amountReceivedOverridden, setAmountReceivedOverridden] = useState(() =>
        Boolean(defaultValues?.amount_received && defaultValues.amount_received !== defaultValues.amount_sent),
    );

    /**
     * The tags currently selected for the transfer.
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
     * The side whose rate popover is open, if any.
     */
    const [openRate, setOpenRate] = useState<Side | null>(null);

    /**
     * Whether the user typed a rate per side, which must not be replaced
     * when the date or that side's currency changes.
     */
    const [quoteOverridden, setQuoteOverridden] = useState<Record<Side, boolean>>(() => ({
        destination: Boolean(defaultValues?.destination_quote),
        origin: Boolean(defaultValues?.origin_quote),
    }));

    /**
     * The rate the user typed per side, when overriding or filling a gap.
     */
    const [quoteValue, setQuoteValue] = useState<Record<Side, string>>(() => ({
        destination: defaultValues?.destination_quote ? trimTrailingZeros(defaultValues.destination_quote) : "",
        origin: defaultValues?.origin_quote ? trimTrailingZeros(defaultValues.origin_quote) : "",
    }));

    /**
     * The fields ref.
     */
    const fields = useRef<HTMLDivElement>(null);

    /**
     * The occurred at date.
     */
    const occurredAtDate = parseDateTimeLocal(occurredAt);

    /**
     * The catalog rate of each side at the occurred at date.
     */
    const originQuote = quoteAsOf(originCurrency, occurredAtDate);
    const destinationQuote = quoteAsOf(destinationCurrency, occurredAtDate);

    /**
     * Whether either side has no rate to fall back on.
     */
    const originQuoteMissing = originCurrency !== null && originQuote === null;
    const destinationQuoteMissing = destinationCurrency !== null && destinationQuote === null;

    /**
     * Whether both sides move the same currency, so the amount received
     * can mirror the amount sent.
     */
    const sameCurrency = Boolean(originCurrency && destinationCurrency && originCurrency.id === destinationCurrency.id);

    /**
     * The amount submitted as received, mirroring the amount sent while
     * both currencies match and the user has not typed one.
     */
    const effectiveAmountReceived = sameCurrency && !amountReceivedOverridden ? amountSent : amountReceived;

    /**
     * Update the rate the user typed for one side.
     */
    const setQuoteFor = (side: Side, value: string): void => {
        setQuoteValue((current) => ({ ...current, [side]: value }));
        setQuoteOverridden((current) => ({ ...current, [side]: value.trim() !== "" }));
        markFormDirty(fields.current);
    };

    // Drop a catalog prefill when a side's currency or the date changes.
    useEffect(() => {
        setQuoteValue((current) => ({
            destination: quoteOverridden.destination ? current.destination : "",
            origin: quoteOverridden.origin ? current.origin : "",
        }));
    }, [originCurrency?.id, destinationCurrency?.id, occurredAt, quoteOverridden.destination, quoteOverridden.origin]);

    // Open the rate popover of the first side with no observation for
    // that instant, so the user can fill the gap before saving.
    useEffect(() => {
        if (originQuoteMissing && quoteValue.origin.trim() === "") {
            setOpenRate("origin");

            return;
        }

        if (destinationQuoteMissing && quoteValue.destination.trim() === "") {
            setOpenRate("destination");
        }
    }, [originQuoteMissing, destinationQuoteMissing, quoteValue.origin, quoteValue.destination]);

    return (
        <FieldGroup className="gap-5" ref={fields}>
            {/** Origin */}
            <SideFields
                account={originAccount}
                accounts={accounts}
                accountName="origin_account_id"
                amount={amountSent}
                amountName="amount_sent"
                currency={originCurrency}
                currencyName="origin_currency_id"
                errors={errors}
                feeName="origin_fee"
                defaultFee={defaultValues?.origin_fee}
                fields={fields}
                invalid={invalid}
                label={t("Origin")}
                onAccountChange={(value) => {
                    setOriginAccount(value);
                    setOriginCurrency(initialCurrency(value));
                }}
                onAmountChange={setAmountSent}
                onCurrencyChange={setOriginCurrency}
            />

            <div className="text-muted-foreground flex items-center justify-center" aria-hidden>
                <ArrowDownIcon className="size-4" />
            </div>

            {/** Destination */}
            <SideFields
                account={destinationAccount}
                accounts={accounts}
                accountName="destination_account_id"
                amount={effectiveAmountReceived}
                amountName="amount_received"
                currency={destinationCurrency}
                currencyName="destination_currency_id"
                errors={errors}
                feeName="destination_fee"
                defaultFee={defaultValues?.destination_fee}
                fields={fields}
                invalid={invalid}
                label={t("Destination")}
                onAccountChange={(value) => {
                    setDestinationAccount(value);
                    setDestinationCurrency(initialCurrency(value));
                }}
                onAmountChange={(value) => {
                    setAmountReceived(value);
                    setAmountReceivedOverridden(value.trim() !== "");
                }}
                onCurrencyChange={setDestinationCurrency}
            />

            {/** Date, note, tags, rates */}
            <input name="occurred_at" type="hidden" value={occurredAt} />
            <input name="description" type="hidden" value={description} />
            {selectedTags.map((tag) => (
                <input key={tag.id} name="tag_ids[]" type="hidden" value={tag.id} />
            ))}
            {originQuoteMissing || quoteOverridden.origin ? (
                <input name="origin_quote" type="hidden" value={quoteValue.origin} />
            ) : null}
            {destinationQuoteMissing || quoteOverridden.destination ? (
                <input name="destination_quote" type="hidden" value={quoteValue.destination} />
            ) : null}
            <div className="flex flex-col gap-2">
                <div className="grid min-w-0 grid-cols-[auto_minmax(0,1fr)_minmax(0,1fr)] gap-2">
                    <DateChip
                        fields={fields}
                        idPrefix="transfer"
                        occurredAt={occurredAt}
                        onOccurredAtChange={setOccurredAt}
                    />
                    <NotesChip
                        description={description}
                        error={errors.description}
                        fields={fields}
                        idPrefix="transfer"
                        invalid={invalid("description")}
                        onDescriptionChange={setDescription}
                    />
                    <TagsChip
                        error={errors.tag_ids}
                        fields={fields}
                        idPrefix="transfer"
                        invalid={invalid("tag_ids")}
                        onSelectedTagsChange={setSelectedTags}
                        selectedTags={selectedTags}
                        tags={tags}
                    />
                </div>
                <div className="grid min-w-0 grid-cols-2 gap-2">
                    <RateChip
                        currency={originCurrency}
                        error={errors.origin_quote}
                        id="transfer-origin-quote"
                        invalid={invalid("origin_quote")}
                        label={t("Origin rate")}
                        occurredAt={occurredAtDate}
                        onOpenChange={(open) => setOpenRate(open ? "origin" : null)}
                        onValueChange={(value) => setQuoteFor("origin", value)}
                        open={openRate === "origin"}
                        overridden={quoteOverridden.origin}
                        quote={originQuote}
                        value={quoteValue.origin}
                    />
                    <RateChip
                        currency={destinationCurrency}
                        error={errors.destination_quote}
                        id="transfer-destination-quote"
                        invalid={invalid("destination_quote")}
                        label={t("Destination rate")}
                        occurredAt={occurredAtDate}
                        onOpenChange={(open) => setOpenRate(open ? "destination" : null)}
                        onValueChange={(value) => setQuoteFor("destination", value)}
                        open={openRate === "destination"}
                        overridden={quoteOverridden.destination}
                        quote={destinationQuote}
                        value={quoteValue.destination}
                    />
                </div>
            </div>
            {invalid("occurred_at") && <FieldError>{errors.occurred_at}</FieldError>}
            {invalid("description") && <FieldError>{errors.description}</FieldError>}
            {invalid("tag_ids") && <FieldError>{errors.tag_ids}</FieldError>}
            {invalid("origin_quote") && <FieldError>{errors.origin_quote}</FieldError>}
            {invalid("destination_quote") && <FieldError>{errors.destination_quote}</FieldError>}
        </FieldGroup>
    );
};

type SideFieldsProps = {
    account: Account | null;
    accountName: "destination_account_id" | "origin_account_id";
    accounts: Array<Account>;
    amount: string;
    amountName: "amount_received" | "amount_sent";
    currency: Currency | null;
    currencyName: "destination_currency_id" | "origin_currency_id";
    defaultFee?: string;
    errors: FormComponentSlotProps<TransferFormValues>["errors"];
    feeName: "destination_fee" | "origin_fee";
    fields: React.RefObject<HTMLDivElement | null>;
    invalid: FormComponentSlotProps<TransferFormValues>["invalid"];
    label: string;
    onAccountChange: (account: Account | null) => void;
    onAmountChange: (amount: string) => void;
    onCurrencyChange: (currency: Currency | null) => void;
};

/**
 * The account, currency, amount, and fee of one side of the transfer.
 */
const SideFields = ({
    account,
    accountName,
    accounts,
    amount,
    amountName,
    currency,
    currencyName,
    defaultFee,
    errors,
    feeName,
    fields,
    invalid,
    label,
    onAccountChange,
    onAmountChange,
    onCurrencyChange,
}: SideFieldsProps) => {
    /**
     * Translate the provided string
     * to the current locale.
     */
    const t = useLaravelReactI18n().t;

    return (
        <div className="flex flex-col gap-3">
            <Field data-invalid={invalid(accountName) || undefined}>
                <FieldLabel>{label}</FieldLabel>
                <Combobox<Account>
                    items={accounts}
                    itemToStringLabel={(item) => item.name}
                    itemToStringValue={(item) => item.id}
                    name={accountName}
                    onValueChange={(value) => {
                        onAccountChange(value);
                        markFormDirty(fields.current);
                    }}
                    value={account}
                >
                    <ComboboxInput aria-invalid={invalid(accountName)} placeholder={t("Select an account")}>
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
                {invalid(accountName) && <FieldError>{errors[accountName]}</FieldError>}
            </Field>

            <Field data-invalid={invalid(amountName) || invalid(currencyName) || undefined}>
                <FieldLabel htmlFor={`transfer-${amountName}`}>
                    {amountName === "amount_sent" ? t("Amount sent") : t("Amount received")}
                </FieldLabel>
                <Combobox<Currency>
                    key={account?.id ?? "none"}
                    disabled={!account}
                    items={account?.currencies ?? []}
                    itemToStringLabel={(item) => item.code}
                    itemToStringValue={(item) => item.id}
                    name={currencyName}
                    onValueChange={(value) => {
                        onCurrencyChange(value);
                        markFormDirty(fields.current);
                    }}
                    value={currency}
                >
                    <InputGroup className="h-auto min-h-14 items-center">
                        <InputGroupAddon>
                            <InputGroupButton
                                aria-invalid={invalid(currencyName)}
                                disabled={!account}
                                render={<ComboboxTrigger />}
                                size="xs"
                                variant="ghost"
                            >
                                <ComboboxValue placeholder={t("Currency")} />
                            </InputGroupButton>
                        </InputGroupAddon>
                        <InputGroupInput
                            aria-invalid={invalid(amountName)}
                            autoComplete="off"
                            className="h-auto py-2 text-right text-3xl font-semibold tabular-nums md:text-3xl"
                            disabled={!account}
                            id={`transfer-${amountName}`}
                            inputMode="decimal"
                            name={amountName}
                            onChange={(event) => onAmountChange(event.target.value)}
                            placeholder="0"
                            required
                            spellCheck={false}
                            type="text"
                            value={amount}
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
                {invalid(amountName) && <FieldError>{errors[amountName]}</FieldError>}
                {invalid(currencyName) && <FieldError>{errors[currencyName]}</FieldError>}
            </Field>

            <Field data-invalid={invalid(feeName) || undefined}>
                <FieldLabel htmlFor={`transfer-${feeName}`}>
                    {feeName === "origin_fee" ? t("Origin fee") : t("Destination fee")}
                </FieldLabel>
                <InputGroup>
                    <InputGroupAddon>
                        <InputGroupText>{currency?.code ?? t("Fee")}</InputGroupText>
                    </InputGroupAddon>
                    <InputGroupInput
                        aria-invalid={invalid(feeName)}
                        autoComplete="off"
                        className="text-right tabular-nums"
                        defaultValue={defaultFee ? trimTrailingZeros(defaultFee) : undefined}
                        disabled={!account}
                        id={`transfer-${feeName}`}
                        inputMode="decimal"
                        name={feeName}
                        placeholder={t("Optional")}
                        spellCheck={false}
                        type="text"
                    />
                </InputGroup>
                <FieldDescription>{t("What the bank or network charged on this side.")}</FieldDescription>
                {invalid(feeName) && <FieldError>{errors[feeName]}</FieldError>}
            </Field>
        </div>
    );
};

type RateChipProps = {
    currency: Currency | null;
    error?: string;
    id: string;
    invalid: boolean;
    label: string;
    occurredAt: Date;
    onOpenChange: (open: boolean) => void;
    onValueChange: (value: string) => void;
    open: boolean;
    overridden: boolean;
    quote: ReturnType<typeof quoteAsOf>;
    value: string;
};

/**
 * The rate chip of one side of the transfer, prefilled with the catalog
 * rate as of the transfer date and always overridable.
 */
const RateChip = ({
    currency,
    error,
    id,
    invalid,
    label,
    occurredAt,
    onOpenChange,
    onValueChange,
    open,
    overridden,
    quote,
    value,
}: RateChipProps) => {
    /**
     * Translate the provided string
     * to the current locale.
     */
    const { currentLocale, t } = useLaravelReactI18n();

    if (!currency) {
        return null;
    }

    /**
     * Whether no observation exists for that instant.
     */
    const missing = quote === null;

    /**
     * The date the shown rate was observed on.
     */
    const dateLabel = new Intl.DateTimeFormat(currentLocale(), { dateStyle: "medium" }).format(
        quote ? new Date(quote.observed_at) : occurredAt,
    );

    return (
        <Popover
            onOpenChange={(next) => {
                // Prefill the editor with the catalog rate when the user
                // has not typed one yet.
                if (next && quote && value.trim() === "") {
                    onValueChange(trimTrailingZeros(quote.rate));
                }

                onOpenChange(next);
            }}
            open={open}
        >
            <PopoverTrigger
                render={
                    <Button
                        aria-invalid={invalid || (missing && value.trim() === "")}
                        className="max-w-full min-w-0 justify-start"
                        size="sm"
                        type="button"
                        variant="outline"
                    />
                }
            >
                <DollarSignIcon data-icon="inline-start" />
                <span className="min-w-0 truncate">{label}</span>
                <ChipSetCheck show={overridden} />
            </PopoverTrigger>
            <PopoverContent align="start" className="w-80">
                <Field data-invalid={invalid || undefined}>
                    <FieldLabel htmlFor={id}>{t("1 US dollar = :code", { code: currency.code })}</FieldLabel>
                    <InputGroup>
                        <InputGroupAddon>
                            <InputGroupText>{currency.code}</InputGroupText>
                        </InputGroupAddon>
                        <InputGroupInput
                            aria-invalid={invalid}
                            autoComplete="off"
                            className="text-right tabular-nums"
                            id={id}
                            inputMode="decimal"
                            onChange={(event) => onValueChange(event.target.value)}
                            placeholder="0"
                            spellCheck={false}
                            type="text"
                            value={value}
                        />
                    </InputGroup>
                    <FieldDescription>
                        {missing
                            ? t("No rate for :date. We need it to save.", { date: dateLabel })
                            : t("Rate of :date", { date: dateLabel })}
                    </FieldDescription>
                    {invalid && <FieldError>{error}</FieldError>}
                </Field>
            </PopoverContent>
        </Popover>
    );
};
