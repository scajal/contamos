import { type FormComponentSlotProps } from "@inertiajs/core";
import { format } from "date-fns";
import { useLaravelReactI18n } from "laravel-react-i18n";
import { CalendarIcon, ListIcon } from "lucide-react";
import { useState } from "react";

import { Button } from "@/components/ui/button";
import { Calendar } from "@/components/ui/calendar";
import {
    Combobox,
    ComboboxContent,
    ComboboxInput,
    ComboboxItem,
    ComboboxList,
    ComboboxTrigger,
    ComboboxValue,
} from "@/components/ui/combobox";
import { Field, FieldError, FieldLabel } from "@/components/ui/field";
import { InputGroup, InputGroupAddon, InputGroupButton, InputGroupInput } from "@/components/ui/input-group";
import { Popover, PopoverContent, PopoverTrigger } from "@/components/ui/popover";
import { Textarea } from "@/components/ui/textarea";
import { Currency } from "@/types/app/modules/core/catalog/models/currency";
import { Account } from "@/types/app/modules/core/finance/models/team/account";
import { Transaction } from "@/types/app/modules/core/finance/models/team/transaction";

type FormProps = Partial<Omit<Transaction, "account" | "currency" | "team" | "user">>;

type Props = FormComponentSlotProps<FormProps> & {
    accounts: Array<Account>;
    defaultValues?: FormProps;
};

function parseOccurredAt(value?: string): Date {
    if (value === undefined) {
        return new Date();
    }

    const match = /^(\d{4})-(\d{2})-(\d{2})/.exec(value);

    if (match !== null) {
        return new Date(Number(match[1]), Number(match[2]) - 1, Number(match[3]));
    }

    const parsed = new Date(value);

    return Number.isNaN(parsed.getTime()) ? new Date() : parsed;
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
        () => accounts.find((candidate) => candidate.id === defaultValues?.account_id) ?? null,
    );

    /**
     * The currently selected currency, used to set the
     * amount input step and show its code in the trigger.
     */
    const [currency, setCurrency] = useState<Currency | null>(
        () =>
            accounts
                .find((candidate) => candidate.id === defaultValues?.account_id)
                ?.currencies?.find((candidate) => candidate.id === defaultValues?.currency_id) ?? null,
    );

    /**
     * The selected date, submitted as YYYY-MM-DD
     * through the hidden occurred_at input.
     */
    const [occurredAt, setOccurredAt] = useState<Date>(() => parseOccurredAt(defaultValues?.occurred_at));

    /**
     * Whether the date picker popover is open.
     */
    const [dateOpen, setDateOpen] = useState(false);

    /**
     * The step for the amount input, based on the selected currency.
     */
    const amountStep = currency === null ? "any" : 10 ** -currency.decimal_places;

    return (
        <div className="space-y-6">
            {/** Account */}
            <Field>
                <FieldLabel>{t("Account")}</FieldLabel>
                <Combobox<Account>
                    items={accounts}
                    itemToStringLabel={(item) => item.name}
                    itemToStringValue={(item) => item.id}
                    name="account_id"
                    onValueChange={(value) => {
                        setAccount(value);
                        setCurrency(null);
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
            <Field>
                <FieldLabel>{t("Amount")}</FieldLabel>
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
                            defaultValue={defaultValues?.amount}
                            name="amount"
                            placeholder={t("Enter an amount")}
                            step={amountStep}
                            type="number"
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
            <Field>
                <FieldLabel>{t("Date")}</FieldLabel>
                <Popover
                    open={dateOpen}
                    onOpenChange={(open) => {
                        setDateOpen(open);
                    }}
                >
                    <PopoverTrigger
                        render={
                            <Button
                                aria-invalid={invalid("occurred_at")}
                                className="w-full justify-start font-normal"
                                type="button"
                                variant="outline"
                            />
                        }
                    >
                        <CalendarIcon />
                        {format(occurredAt, "PPP")}
                    </PopoverTrigger>
                    <input name="occurred_at" type="hidden" value={format(occurredAt, "yyyy-MM-dd")} />
                    <PopoverContent align="start" className="w-auto p-0">
                        <Calendar
                            mode="single"
                            required
                            selected={occurredAt}
                            onSelect={(next) => {
                                setOccurredAt(next);
                                setDateOpen(false);
                            }}
                        />
                    </PopoverContent>
                </Popover>
                {invalid("occurred_at") && <FieldError>{errors.occurred_at}</FieldError>}
            </Field>

            {/** Description */}
            <Field>
                <FieldLabel>{t("Description")}</FieldLabel>
                <Textarea
                    aria-invalid={invalid("description")}
                    defaultValue={defaultValues?.description ?? undefined}
                    name="description"
                    placeholder={t("Enter a description")}
                />
                {invalid("description") && <FieldError>{errors.description}</FieldError>}
            </Field>
        </div>
    );
};
