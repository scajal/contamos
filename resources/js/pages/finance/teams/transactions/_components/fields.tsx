import { type FormComponentSlotProps } from "@inertiajs/core";
import { useLaravelReactI18n } from "laravel-react-i18n";
import { CalendarIcon, DollarSignIcon, ListIcon } from "lucide-react";
import { useState } from "react";

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
import { InputGroup, InputGroupAddon, InputGroupButton, InputGroupInput } from "@/components/ui/input-group";
import { Textarea } from "@/components/ui/textarea";
import { Currency } from "@/types/app/modules/core/catalog/models/currency";
import { Account } from "@/types/app/modules/core/finance/models/team/account";
import { Transaction } from "@/types/app/modules/core/finance/models/team/transaction";

type FormProps = Partial<Omit<Transaction, "account" | "currency" | "team" | "user">>;

type Props = FormComponentSlotProps<FormProps> & {
    accounts: Array<Account>;
    defaultValues?: FormProps;
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

export const Fields = ({ accounts, defaultValues, errors, invalid }: Props) => {
    /**
     * Translate the provided string
     * to the current locale.
     */
    const t = useLaravelReactI18n().t;

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

    return (
        <FieldGroup>
            {/** Type */}
            <input defaultValue={defaultValues?.type} name="type" type="hidden" />

            {/** Account */}
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
                    }}
                    value={account}
                >
                    <ComboboxInput aria-invalid={invalid("account_id")} autoFocus placeholder={t("Select an account")}>
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
                    onValueChange={setCurrency}
                    value={currency}
                >
                    <InputGroup>
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
                            defaultValue={defaultValues?.amount}
                            disabled={!account}
                            id="transaction-amount"
                            inputMode="decimal"
                            name="amount"
                            placeholder={t("Enter an amount")}
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

            {/** Date */}
            <Field data-invalid={invalid("occurred_at") || undefined}>
                <FieldLabel htmlFor="transaction-occurred-at">{t("Date")}</FieldLabel>
                <InputGroup>
                    <InputGroupAddon>
                        <CalendarIcon />
                    </InputGroupAddon>
                    <InputGroupInput
                        aria-invalid={invalid("occurred_at")}
                        defaultValue={toDateTimeLocalValue(defaultValues?.occurred_at)}
                        id="transaction-occurred-at"
                        name="occurred_at"
                        type="datetime-local"
                    />
                </InputGroup>
                {invalid("occurred_at") && <FieldError>{errors.occurred_at}</FieldError>}
            </Field>

            {/** Description */}
            <Field data-invalid={invalid("description") || undefined}>
                <FieldLabel htmlFor="transaction-description">{t("Description")}</FieldLabel>
                <Textarea
                    aria-invalid={invalid("description")}
                    defaultValue={defaultValues?.description ?? undefined}
                    id="transaction-description"
                    name="description"
                    placeholder={t("Enter a description")}
                />
                {invalid("description") && <FieldError>{errors.description}</FieldError>}
            </Field>

            {/** Exchange rate */}
            <Field data-invalid={invalid("quote") || undefined}>
                <FieldLabel htmlFor="transaction-quote">{t("Quote")}</FieldLabel>
                <InputGroup>
                    <InputGroupAddon>
                        <DollarSignIcon />
                    </InputGroupAddon>
                    <InputGroupInput
                        aria-invalid={invalid("quote")}
                        defaultValue={defaultValues?.quote}
                        id="transaction-quote"
                        inputMode="decimal"
                        name="quote"
                        placeholder={t("Enter a quote")}
                        type="text"
                    />
                </InputGroup>
                <FieldDescription>
                    {t(
                        "The quote of the currency at the time of the transaction. Leave blank to use the current rate (if any).",
                    )}
                </FieldDescription>
                {invalid("quote") && <FieldError>{errors.quote}</FieldError>}
            </Field>
        </FieldGroup>
    );
};
