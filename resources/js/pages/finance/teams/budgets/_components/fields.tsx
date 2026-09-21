import { type FormComponentSlotProps } from "@inertiajs/core";
import { useLaravelReactI18n } from "laravel-react-i18n";
import { CoinsIcon } from "lucide-react";
import { useState } from "react";

import { Field, FieldDescription, FieldError, FieldGroup, FieldLabel } from "@/components/ui/field";
import { Input } from "@/components/ui/input";
import { InputGroup, InputGroupAddon, InputGroupInput, InputGroupText } from "@/components/ui/input-group";
import { Select, SelectContent, SelectGroup, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import { markFormDirty } from "@/lib/utils";
import { Currency } from "@/types/app/modules/core/catalog/models/currency";
import { Tag } from "@/types/app/modules/core/finance/models/team/tag";

type FormProps = Partial<{
    amount: string;
    currency_id: string;
    from_month: string;
    tag_id: string;
    until_month: string | null;
}>;

type Props = FormComponentSlotProps<FormProps> & {
    currencies: Array<Currency>;
    defaultValues?: FormProps;
    /** A period keeps the tag it was opened on, so an edit hides the picker. */
    tags?: Array<Tag>;
};

export const Fields = ({ currencies, defaultValues, errors, invalid, tags }: Props) => {
    /**
     * Translate the provided string
     * to the current locale.
     */
    const t = useLaravelReactI18n().t;

    /**
     * The tag the budget is set on, kept in state so the hidden
     * input submits it and the form reads as dirty.
     */
    const [tagId, setTagId] = useState<string | null>(defaultValues?.tag_id ?? null);

    /**
     * The currency the budget is expressed in.
     */
    const [currencyId, setCurrencyId] = useState<string | null>(
        defaultValues?.currency_id ?? currencies[0]?.id ?? null,
    );

    /**
     * The currency the amount is entered in, so the field can
     * show its code next to the figure.
     */
    const currency = currencies.find((one) => one.id === currencyId);

    return (
        <FieldGroup>
            {/** Tag */}
            {tags ? (
                <Field data-invalid={invalid("tag_id") || undefined}>
                    <FieldLabel htmlFor="budget-tag">{t("Tag")}</FieldLabel>
                    <input name="tag_id" type="hidden" value={tagId ?? ""} />
                    <Select
                        onValueChange={(value: string | null) => {
                            setTagId(value);
                            markFormDirty();
                        }}
                        value={tagId}
                    >
                        <SelectTrigger className="w-full" id="budget-tag">
                            <SelectValue>
                                {(value: string | null) =>
                                    tags.find((tag) => tag.id === value)?.name ?? t("Select a tag")
                                }
                            </SelectValue>
                        </SelectTrigger>
                        <SelectContent>
                            <SelectGroup>
                                {tags.map((tag) => (
                                    <SelectItem key={tag.id} value={tag.id}>
                                        {tag.is_active ? tag.name : `${tag.name} · ${t("Inactive")}`}
                                    </SelectItem>
                                ))}
                            </SelectGroup>
                        </SelectContent>
                    </Select>
                    {invalid("tag_id") && <FieldError>{errors.tag_id}</FieldError>}
                </Field>
            ) : null}

            {/** Currency */}
            <Field data-invalid={invalid("currency_id") || undefined}>
                <FieldLabel htmlFor="budget-currency">{t("Currency")}</FieldLabel>
                <input name="currency_id" type="hidden" value={currencyId ?? ""} />
                <Select
                    onValueChange={(value: string | null) => {
                        setCurrencyId(value);
                        markFormDirty();
                    }}
                    value={currencyId}
                >
                    <SelectTrigger className="w-full" id="budget-currency">
                        <SelectValue>
                            {(value: string | null) =>
                                currencies.find((one) => one.id === value)?.code ?? t("Select a currency")
                            }
                        </SelectValue>
                    </SelectTrigger>
                    <SelectContent>
                        <SelectGroup>
                            {currencies.map((one) => (
                                <SelectItem key={one.id} value={one.id}>
                                    {one.code}
                                </SelectItem>
                            ))}
                        </SelectGroup>
                    </SelectContent>
                </Select>
                <FieldDescription>
                    {t("Only the currencies your team holds on an active account can be budgeted.")}
                </FieldDescription>
                {invalid("currency_id") && <FieldError>{errors.currency_id}</FieldError>}
            </Field>

            {/** Amount */}
            <Field data-invalid={invalid("amount") || undefined}>
                <FieldLabel htmlFor="budget-amount">{t("Monthly amount")}</FieldLabel>
                <InputGroup>
                    <InputGroupAddon>
                        <CoinsIcon />
                    </InputGroupAddon>
                    <InputGroupInput
                        aria-invalid={invalid("amount")}
                        className="text-right tabular-nums"
                        defaultValue={defaultValues?.amount}
                        id="budget-amount"
                        inputMode="decimal"
                        name="amount"
                        placeholder="0"
                    />
                    {currency ? (
                        <InputGroupAddon align="inline-end">
                            <InputGroupText>{currency.code}</InputGroupText>
                        </InputGroupAddon>
                    ) : null}
                </InputGroup>
                {invalid("amount") && <FieldError>{errors.amount}</FieldError>}
            </Field>

            {/** First month */}
            <Field data-invalid={invalid("from_month") || undefined}>
                <FieldLabel htmlFor="budget-from-month">{t("First month")}</FieldLabel>
                <Input
                    aria-invalid={invalid("from_month")}
                    defaultValue={defaultValues?.from_month}
                    id="budget-from-month"
                    name="from_month"
                    type="month"
                />
                {invalid("from_month") && <FieldError>{errors.from_month}</FieldError>}
            </Field>

            {/** Last month */}
            <Field data-invalid={invalid("until_month") || undefined}>
                <FieldLabel htmlFor="budget-until-month">{t("Last month")}</FieldLabel>
                <Input
                    aria-invalid={invalid("until_month")}
                    defaultValue={defaultValues?.until_month ?? ""}
                    id="budget-until-month"
                    name="until_month"
                    type="month"
                />
                <FieldDescription>
                    {t("Leave it empty to keep the budget running. Set it to the first month for a one-off goal.")}
                </FieldDescription>
                {invalid("until_month") && <FieldError>{errors.until_month}</FieldError>}
            </Field>
        </FieldGroup>
    );
};
