import { type FormComponentSlotProps } from "@inertiajs/core";
import { useLaravelReactI18n } from "laravel-react-i18n";
import {
    BanknoteIcon,
    BitcoinIcon,
    CheckIcon,
    CoinsIcon,
    DollarSignIcon,
    LandmarkIcon,
    type LucideIcon,
} from "lucide-react";
import { useCallback, useEffect, useRef, useState } from "react";

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
import { ToggleGroup, ToggleGroupItem } from "@/components/ui/toggle-group";
import { markFormDirty } from "@/lib/utils";
import { Currency } from "@/types/app/modules/core/catalog/models/currency";
import { Quote } from "@/types/app/modules/core/catalog/models/currency/quote";
import { AccountType } from "@/types/app/modules/core/finance/enums/account/account-type";
import { Account } from "@/types/app/modules/core/finance/models/team/account";

type FormProps = Partial<
    Omit<Account, "team"> & {
        currency_ids: Array<string>;
        occurred_at: string;
        opening_balances: Record<string, string>;
        quotes: Record<string, string>;
    }
>;

type Props = FormComponentSlotProps<FormProps> & {
    attachedCurrencyIds?: ReadonlyArray<string>;
    currencies: Array<Currency>;
    defaultValues?: FormProps;
    locked?: ReadonlyArray<keyof FormProps>;
    types: Array<AccountType>;
};

/**
 * Format a Date as a datetime-local input value (YYYY-MM-DDTHH:mm).
 */
function toDateTimeLocalValue(value?: string): string {
    const date = value === undefined ? new Date() : new Date(value);
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
 * Get the quote for the currency at the provided date.
 */
function quoteAsOf(currency: Currency, at: Date): Quote | null {
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

const TYPE_ICONS: Record<AccountType, LucideIcon> = {
    bank: LandmarkIcon,
    cash: BanknoteIcon,
    crypto: BitcoinIcon,
};

export const Fields = ({
    attachedCurrencyIds,
    currencies: listOfCurrencies,
    defaultValues,
    errors,
    invalid,
    locked,
    types,
}: Props) => {
    /**
     * Translate the provided string
     * to the current locale.
     */
    const t = useLaravelReactI18n().t;

    /**
     * The type of the account.
     */
    const [type, setType] = useState<AccountType | undefined>(defaultValues?.type);

    /**
     * The currencies currently selected for the account.
     */
    const [selectedCurrencies, setSelectedCurrencies] = useState<Array<Currency>>(() =>
        listOfCurrencies.filter((currency) => defaultValues?.currency_ids?.includes(currency.id)),
    );

    /**
     * The selected currencies not already attached to the account,
     * in the order they were selected. On create, every selected
     * currency is newly attached.
     */
    const newlyAttachedCurrencies = selectedCurrencies.filter(
        (currency) => !attachedCurrencyIds?.includes(currency.id),
    );

    /**
     * The list of selectable currencies based on the
     * type of the account.
     */
    const currencies = listOfCurrencies.filter((currency) =>
        type === "crypto"
            ? currency.type === "cryptocurrency"
            : type === undefined
              ? false
              : currency.type !== "cryptocurrency",
    );

    /**
     * The balance-as-of wall-clock value, shared by every
     * currency's rate lookup.
     */
    const [occurredAt, setOccurredAt] = useState(() => toDateTimeLocalValue(defaultValues?.occurred_at));
    const occurredAtDate = parseDateTimeLocal(occurredAt);

    /**
     * Anchor the currencies dropdown to the full chips field.
     */
    const currenciesAnchor = useComboboxAnchor();
    const fields = useRef<HTMLDivElement>(null);

    /**
     * Check if the field is locked.
     */
    const isLocked = useCallback((field: keyof FormProps): boolean => locked?.includes(field) ?? false, [locked]);

    return (
        <FieldGroup ref={fields}>
            {/** Name */}
            <Field data-invalid={invalid("name") || undefined}>
                <FieldLabel htmlFor="account-name">{t("Name")}</FieldLabel>
                <Input
                    autoFocus
                    aria-invalid={invalid("name")}
                    defaultValue={defaultValues?.name}
                    disabled={isLocked("name")}
                    id="account-name"
                    name="name"
                    placeholder={t("Enter a name")}
                />
                {invalid("name") && <FieldError>{errors.name}</FieldError>}
            </Field>

            {/** Type */}
            <Field data-invalid={invalid("type") || undefined}>
                <FieldLabel id="account-type-label">{t("Type")}</FieldLabel>
                <input name="type" type="hidden" value={type ?? ""} />
                <ToggleGroup
                    aria-labelledby="account-type-label"
                    aria-invalid={invalid("type")}
                    className="w-full"
                    disabled={isLocked("type")}
                    onValueChange={(value) => {
                        if (!isLocked("type")) {
                            const nextType = value[0] as AccountType | undefined;

                            setType(nextType);
                            setSelectedCurrencies((current) =>
                                current.filter((currency) =>
                                    nextType === "crypto"
                                        ? currency.type === "cryptocurrency"
                                        : nextType === undefined
                                          ? false
                                          : currency.type !== "cryptocurrency",
                                ),
                            );
                            markFormDirty(fields.current);
                        }
                    }}
                    spacing={0}
                    value={type ? [type] : []}
                    variant="outline"
                >
                    {types.map((accountType) => {
                        const Icon = TYPE_ICONS[accountType];

                        return (
                            <ToggleGroupItem className="flex-1" key={accountType} value={accountType}>
                                <Icon data-icon="inline-start" />
                                {t(`enums.${accountType}`)}
                            </ToggleGroupItem>
                        );
                    })}
                </ToggleGroup>
                {invalid("type") && <FieldError>{errors.type}</FieldError>}
            </Field>

            {/** Currencies */}
            <Field data-invalid={invalid("currency_ids") || undefined}>
                <FieldLabel htmlFor="account-currencies">{t("Currencies")}</FieldLabel>
                <FieldDescription>{t("Choose the currencies this account can hold.")}</FieldDescription>
                <Combobox<Currency, true>
                    disabled={!type || isLocked("currency_ids")}
                    isItemEqualToValue={(left, right) => left.id === right.id}
                    items={currencies}
                    itemToStringLabel={(item) => `${item.code} ${item.name}`}
                    itemToStringValue={(item) => item.id}
                    multiple
                    name="currency_ids[]"
                    onValueChange={(value) => {
                        setSelectedCurrencies(value);
                        markFormDirty(fields.current);
                    }}
                    value={selectedCurrencies}
                >
                    <ComboboxChips aria-invalid={invalid("currency_ids")} className="w-full" ref={currenciesAnchor}>
                        <InputGroupAddon>
                            <CoinsIcon />
                        </InputGroupAddon>
                        <ComboboxValue>
                            {selectedCurrencies.map((currency) => (
                                <ComboboxChip key={currency.id}>{currency.code}</ComboboxChip>
                            ))}
                        </ComboboxValue>
                        <ComboboxChipsInput
                            disabled={!type || isLocked("currency_ids")}
                            id="account-currencies"
                            placeholder={selectedCurrencies.length > 0 ? undefined : t("Select currencies")}
                        />
                    </ComboboxChips>
                    <ComboboxContent anchor={currenciesAnchor}>
                        <ComboboxEmpty>{t("No currencies found")}</ComboboxEmpty>
                        <ComboboxList>
                            {(currency) => (
                                <ComboboxItem key={currency.id} value={currency}>
                                    {`${currency.code} · ${currency.name}`}
                                </ComboboxItem>
                            )}
                        </ComboboxList>
                    </ComboboxContent>
                </Combobox>
                {invalid("currency_ids") && <FieldError>{errors.currency_ids}</FieldError>}
            </Field>

            {/** Opening balances */}
            {newlyAttachedCurrencies.length > 0 && (
                <>
                    {newlyAttachedCurrencies.map((currency) => (
                        <OpeningBalanceField
                            currency={currency}
                            defaultAmount={defaultValues?.opening_balances?.[currency.id]}
                            defaultQuote={defaultValues?.quotes?.[currency.id]}
                            errors={errors}
                            fields={fields}
                            invalid={invalid}
                            key={currency.id}
                            locked={isLocked("opening_balances")}
                            occurredAtDate={occurredAtDate}
                        />
                    ))}

                    {/** Balance as of */}
                    <Field data-invalid={invalid("occurred_at") || undefined}>
                        <FieldLabel htmlFor="account-occurred-at">{t("Balance as of")}</FieldLabel>
                        <Input
                            aria-invalid={invalid("occurred_at")}
                            disabled={isLocked("occurred_at")}
                            id="account-occurred-at"
                            name="occurred_at"
                            onChange={(event) => setOccurredAt(event.target.value)}
                            type="datetime-local"
                            value={occurredAt}
                        />
                        {invalid("occurred_at") && <FieldError>{errors.occurred_at}</FieldError>}
                    </Field>
                </>
            )}
        </FieldGroup>
    );
};

/**
 * One currency's opening balance amount, with a rate chip that
 * lets the user override or fill in a missing catalog quote.
 */
const OpeningBalanceField = ({
    currency,
    defaultAmount,
    defaultQuote,
    errors,
    fields,
    invalid,
    locked,
    occurredAtDate,
}: {
    currency: Currency;
    defaultAmount?: string;
    defaultQuote?: string;
    errors: Props["errors"];
    fields: React.RefObject<HTMLDivElement | null>;
    invalid: Props["invalid"];
    locked: boolean;
    occurredAtDate: Date;
}) => {
    /**
     * Translate the provided string
     * to the current locale.
     */
    const { currentLocale, t } = useLaravelReactI18n();

    /**
     * The catalog quote for this currency as of the
     * shared balance-as-of instant.
     */
    const asOfQuote = quoteAsOf(currency, occurredAtDate);

    /**
     * Whether no catalog quote covers the instant.
     */
    const quoteMissing = asOfQuote === null;

    /**
     * Whether the rate popover is open.
     */
    const [quoteOpen, setQuoteOpen] = useState(false);

    /**
     * Whether the user typed a rate that must not be replaced
     * when the date changes.
     */
    const [quoteOverridden, setQuoteOverridden] = useState(() => Boolean(defaultQuote));

    /**
     * The rate the user typed, when overriding or filling a gap.
     */
    const [quoteValue, setQuoteValue] = useState(() => (defaultQuote ? trimTrailingZeros(defaultQuote) : ""));

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

    // Drop a catalog prefill when the date changes, and open the
    // rate popover when no observation exists for that instant.
    useEffect(() => {
        if (!quoteOverridden) {
            setQuoteValue("");
        }
    }, [occurredAtDate.getTime(), quoteOverridden]);

    useEffect(() => {
        if (quoteMissing) {
            setQuoteOpen(true);
        }
    }, [quoteMissing]);

    return (
        <Field data-invalid={invalid(`opening_balances.${currency.id}`) || undefined}>
            <FieldLabel htmlFor={`account-opening-balance-${currency.id}`}>{t("Opening balance")}</FieldLabel>
            <InputGroup>
                <InputGroupAddon>
                    <InputGroupText>{currency.code}</InputGroupText>
                </InputGroupAddon>
                <InputGroupInput
                    aria-invalid={invalid(`opening_balances.${currency.id}`)}
                    autoComplete="off"
                    defaultValue={defaultAmount}
                    disabled={locked}
                    id={`account-opening-balance-${currency.id}`}
                    inputMode="decimal"
                    name={`opening_balances[${currency.id}]`}
                    placeholder="0"
                    spellCheck={false}
                    type="text"
                />
                <InputGroupAddon align="inline-end">
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
                                <InputGroupButton
                                    aria-invalid={
                                        invalid(`quotes.${currency.id}`) || (quoteMissing && quoteValue.trim() === "")
                                    }
                                    size="xs"
                                    type="button"
                                    variant="ghost"
                                />
                            }
                        >
                            <DollarSignIcon />
                            {quoteOverridden && <CheckIcon aria-hidden className="text-success size-3.5" />}
                        </PopoverTrigger>
                        <PopoverContent align="end" className="w-80">
                            <Field data-invalid={invalid(`quotes.${currency.id}`) || undefined}>
                                <FieldLabel htmlFor={`account-quote-${currency.id}`}>
                                    {t("1 US dollar = :code", { code: currency.code })}
                                </FieldLabel>
                                <InputGroup>
                                    <InputGroupAddon>
                                        <InputGroupText>{currency.code}</InputGroupText>
                                    </InputGroupAddon>
                                    <InputGroupInput
                                        aria-invalid={invalid(`quotes.${currency.id}`)}
                                        autoComplete="off"
                                        className="text-right tabular-nums"
                                        id={`account-quote-${currency.id}`}
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
                                {invalid(`quotes.${currency.id}`) && (
                                    <FieldError>{errors[`quotes.${currency.id}`]}</FieldError>
                                )}
                            </Field>
                        </PopoverContent>
                    </Popover>
                </InputGroupAddon>
            </InputGroup>
            {(quoteMissing || quoteOverridden) && (
                <input name={`quotes[${currency.id}]`} type="hidden" value={quoteValue} />
            )}
            {invalid(`opening_balances.${currency.id}`) && (
                <FieldError>{errors[`opening_balances.${currency.id}`]}</FieldError>
            )}
        </Field>
    );
};
