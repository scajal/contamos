import { type FormComponentSlotProps } from "@inertiajs/core";
import { useLaravelReactI18n } from "laravel-react-i18n";
import { CoinsIcon } from "lucide-react";
import { useState } from "react";

import { CategoryIcon } from "@/components/category-icon";
import { Field, FieldDescription, FieldError, FieldGroup, FieldLabel } from "@/components/ui/field";
import { Input } from "@/components/ui/input";
import { InputGroup, InputGroupAddon, InputGroupInput, InputGroupText } from "@/components/ui/input-group";
import { Select, SelectContent, SelectGroup, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import { ToggleGroup, ToggleGroupItem } from "@/components/ui/toggle-group";
import { type CategoryOption } from "@/lib/categories";
import { markFormDirty } from "@/lib/utils";
import { Currency } from "@/types/app/modules/core/catalog/models/currency";
import { BudgetTargetKind } from "@/types/app/modules/core/finance/enums/budget-period/budget-target-kind";
import { Category } from "@/types/app/modules/core/finance/enums/transaction/category";
import { Tag } from "@/types/app/modules/core/finance/models/team/tag";

type FormProps = Partial<{
    amount: string;
    category: Category | null;
    currency_id: string;
    from_month: string;
    tag_id: string | null;
    target_kind: BudgetTargetKind;
    until_month: string | null;
}>;

type Props = FormComponentSlotProps<FormProps> & {
    /** The categories a budget may cap, absent on an edit. */
    categories?: Array<CategoryOption>;
    currencies: Array<Currency>;
    defaultValues?: FormProps;
    /** A period keeps the target it was opened on, so an edit hides the picker. */
    tags?: Array<Tag>;
};

export const Fields = ({ categories, currencies, defaultValues, errors, invalid, tags }: Props) => {
    /**
     * Translate the provided string
     * to the current locale.
     */
    const t = useLaravelReactI18n().t;

    /**
     * Whether the budget caps a category or follows a tag, which
     * is what decides the picker below it.
     */
    const [targetKind, setTargetKind] = useState<BudgetTargetKind>(defaultValues?.target_kind ?? "category");

    /**
     * The category the budget caps, kept in state so the hidden
     * input submits it and the form reads as dirty.
     */
    const [category, setCategory] = useState<Category | null>(defaultValues?.category ?? null);

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
            {/** Target */}
            {categories || tags ? (
                <Field data-invalid={invalid("target_kind") || undefined}>
                    <FieldLabel id="budget-target-kind-label">{t("What does this budget follow?")}</FieldLabel>
                    <input name="target_kind" type="hidden" value={targetKind} />
                    <ToggleGroup
                        aria-labelledby="budget-target-kind-label"
                        className="w-full"
                        onValueChange={(value) => {
                            const kind = value[0];

                            // A budget follows one thing, so the other
                            // picker is emptied on a switch.
                            if (kind === "category" || kind === "tag") {
                                setTargetKind(kind);
                                setCategory(null);
                                setTagId(null);
                                markFormDirty();
                            }
                        }}
                        spacing={0}
                        value={[targetKind]}
                        variant="outline"
                    >
                        <ToggleGroupItem className="flex-1" value="category">
                            {t("Category")}
                        </ToggleGroupItem>
                        <ToggleGroupItem className="flex-1" value="tag">
                            {t("Tag")}
                        </ToggleGroupItem>
                    </ToggleGroup>
                    <FieldDescription>
                        {t(
                            "A category budget caps an area of spending. A tag budget follows a marker across categories.",
                        )}
                    </FieldDescription>
                    {invalid("target_kind") && <FieldError>{errors.target_kind}</FieldError>}
                </Field>
            ) : null}

            {/** Category */}
            {categories && targetKind === "category" ? (
                <Field data-invalid={invalid("category") || undefined}>
                    <FieldLabel htmlFor="budget-category">{t("Category")}</FieldLabel>
                    <input name="category" type="hidden" value={category ?? ""} />
                    <Select
                        onValueChange={(value: string | null) => {
                            setCategory((value as Category | null) ?? null);
                            markFormDirty();
                        }}
                        value={category}
                    >
                        <SelectTrigger className="w-full" id="budget-category">
                            <SelectValue>
                                {(value: string | null) =>
                                    categories.find((one) => one.value === value)?.label ?? t("Select a category")
                                }
                            </SelectValue>
                        </SelectTrigger>
                        <SelectContent>
                            <SelectGroup>
                                {categories.map((one) => (
                                    <SelectItem key={one.value} value={one.value}>
                                        <CategoryIcon category={one.value} className="size-4" />
                                        {one.label}
                                    </SelectItem>
                                ))}
                            </SelectGroup>
                        </SelectContent>
                    </Select>
                    {invalid("category") && <FieldError>{errors.category}</FieldError>}
                </Field>
            ) : null}

            {/** Tag */}
            {tags && targetKind === "tag" ? (
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
