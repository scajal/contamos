import { type FormComponentSlotProps } from "@inertiajs/core";
import { format, parse, startOfMonth } from "date-fns";
import { useLaravelReactI18n } from "laravel-react-i18n";
import { BellIcon, CalendarIcon, CoinsIcon, FolderTreeIcon } from "lucide-react";
import { useState } from "react";

import { CategoryIcon } from "@/components/category-icon";
import { Button } from "@/components/ui/button";
import { Calendar } from "@/components/ui/calendar";
import { Field, FieldDescription, FieldError, FieldGroup, FieldLabel } from "@/components/ui/field";
import { InputGroup, InputGroupAddon, InputGroupInput, InputGroupText } from "@/components/ui/input-group";
import { Popover, PopoverContent, PopoverTrigger } from "@/components/ui/popover";
import { Select, SelectContent, SelectGroup, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import { ToggleGroup, ToggleGroupItem } from "@/components/ui/toggle-group";
import { type CategoryOption } from "@/lib/categories";
import { formatMonth, markFormDirty } from "@/lib/utils";
import { Currency } from "@/types/app/modules/core/catalog/models/currency";
import { BudgetTargetKind } from "@/types/app/modules/core/finance/enums/budget-period/budget-target-kind";
import { Category } from "@/types/app/modules/core/finance/enums/transaction/category";
import { BudgetParentOption } from "@/types/app/modules/core/finance/models/team/budget-period";
import { Tag } from "@/types/app/modules/core/finance/models/team/tag";

/**
 * The month format the form submits and the server expects.
 */
const MONTH_FORMAT = "yyyy-MM";

/**
 * Read a yyyy-MM string as the first day of that month.
 */
const toMonth = (month: string): Date => startOfMonth(parse(month, MONTH_FORMAT, new Date()));

type FormProps = Partial<{
    amount: string;
    category: Category | null;
    currency_id: string;
    from_month: string;
    parent_id: string | null;
    tag_id: string | null;
    target_kind: BudgetTargetKind;
    until_month: string | null;
    warning_threshold: string;
}>;

type Props = FormComponentSlotProps<FormProps> & {
    /** The categories a budget may cap, absent on an edit. */
    categories?: Array<CategoryOption>;
    currencies: Array<Currency>;
    defaultValues?: FormProps;
    /** The category budgets a tag budget may sit under. */
    parents?: Array<BudgetParentOption>;
    /** A period keeps the target it was opened on, so an edit hides the picker. */
    tags?: Array<Tag>;
};

export const Fields = ({ categories, currencies, defaultValues, errors, invalid, parents, tags }: Props) => {
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
     * The category budget the tag budget sits under, kept in
     * state so the hidden input submits it and the form reads
     * as dirty.
     */
    const [parentId, setParentId] = useState<string | null>(defaultValues?.parent_id ?? null);

    /**
     * The currency the budget is expressed in.
     */
    const [currencyId, setCurrencyId] = useState<string | null>(
        defaultValues?.currency_id ?? currencies[0]?.id ?? null,
    );

    /**
     * The category budgets the tag budget may sit under: only
     * the ones in the currency picked count the same money.
     */
    const parentOptions = (parents ?? []).filter((one) => one.currency_id === currencyId);

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
                        // A parent in another currency counts other
                        // money, so a currency switch drops it.
                        setParentId(null);
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

            {/** Parent */}
            {parents && targetKind === "tag" ? (
                <Field data-invalid={invalid("parent_id") || undefined}>
                    <FieldLabel htmlFor="budget-parent">{t("Part of")}</FieldLabel>
                    <input name="parent_id" type="hidden" value={parentId ?? ""} />
                    <Select
                        onValueChange={(value: string | null) => {
                            setParentId(value);
                            markFormDirty();
                        }}
                        value={parentId}
                    >
                        <SelectTrigger className="w-full" id="budget-parent">
                            <SelectValue>
                                {(value: string | null) => {
                                    const parent = parentOptions.find((one) => one.id === value);

                                    return parent ? <ParentLabel parent={parent} /> : t("On its own");
                                }}
                            </SelectValue>
                        </SelectTrigger>
                        <SelectContent>
                            <SelectGroup>
                                <SelectItem value={null}>{t("On its own")}</SelectItem>
                                {parentOptions.map((one) => (
                                    <SelectItem key={one.id} value={one.id}>
                                        <ParentLabel parent={one} />
                                    </SelectItem>
                                ))}
                            </SelectGroup>
                        </SelectContent>
                    </Select>
                    <FieldDescription>
                        {t(
                            "Nest this tag budget under a category budget to read it as a share of it. It then only counts the tag within that category, and the category budget is not capped by its parts.",
                        )}
                    </FieldDescription>
                    {invalid("parent_id") && <FieldError>{errors.parent_id}</FieldError>}
                </Field>
            ) : null}

            {/** First month */}
            <Field data-invalid={invalid("from_month") || undefined}>
                <FieldLabel htmlFor="budget-from-month">{t("First month")}</FieldLabel>
                <MonthField
                    defaultValue={defaultValues?.from_month ?? null}
                    id="budget-from-month"
                    invalid={invalid("from_month")}
                    name="from_month"
                />
                {invalid("from_month") && <FieldError>{errors.from_month}</FieldError>}
            </Field>

            {/** Last month */}
            <Field data-invalid={invalid("until_month") || undefined}>
                <FieldLabel htmlFor="budget-until-month">{t("Last month")}</FieldLabel>
                <MonthField
                    clearable
                    defaultValue={defaultValues?.until_month ?? null}
                    id="budget-until-month"
                    invalid={invalid("until_month")}
                    name="until_month"
                />
                <FieldDescription>
                    {t("Leave it empty to keep the budget running. Set it to the first month for a one-off goal.")}
                </FieldDescription>
                {invalid("until_month") && <FieldError>{errors.until_month}</FieldError>}
            </Field>

            {/** Warning threshold */}
            <Field data-invalid={invalid("warning_threshold") || undefined}>
                <FieldLabel htmlFor="budget-warning-threshold">{t("Warn me at")}</FieldLabel>
                <InputGroup>
                    <InputGroupAddon>
                        <BellIcon />
                    </InputGroupAddon>
                    <InputGroupInput
                        aria-invalid={invalid("warning_threshold")}
                        className="text-right tabular-nums"
                        defaultValue={defaultValues?.warning_threshold}
                        id="budget-warning-threshold"
                        inputMode="numeric"
                        name="warning_threshold"
                    />
                    <InputGroupAddon align="inline-end">
                        <InputGroupText>%</InputGroupText>
                    </InputGroupAddon>
                </InputGroup>
                <FieldDescription>{t("Leave it empty for no warning.")}</FieldDescription>
                {invalid("warning_threshold") && <FieldError>{errors.warning_threshold}</FieldError>}
            </Field>
        </FieldGroup>
    );
};

/**
 * A category budget named the way the parent picker lists it:
 * its category and the month it runs from.
 */
const ParentLabel = ({ parent }: { parent: BudgetParentOption }) => {
    /**
     * Translate the provided string
     * to the current locale.
     */
    const { currentLocale, t } = useLaravelReactI18n();

    return (
        <span className="flex min-w-0 items-center gap-2">
            <CategoryIcon category={parent.category.value} className="size-4 shrink-0" />
            <span className="truncate">{parent.category.label}</span>
            <span className="text-muted-foreground truncate text-xs">
                {t("From :month", {
                    month: formatMonth(toMonth(parent.from_month), currentLocale(), {
                        month: "short",
                        year: "numeric",
                    }),
                })}
            </span>
        </span>
    );
};

type MonthFieldProps = {
    /** Whether the month may be left empty, shown as ongoing. */
    clearable?: boolean;
    defaultValue: string | null;
    id: string;
    invalid: boolean;
    name: string;
};

/**
 * A month picked from the calendar, submitted as yyyy-MM through
 * a hidden input. Any day of a month selects that month.
 */
const MonthField = ({ clearable = false, defaultValue, id, invalid, name }: MonthFieldProps) => {
    /**
     * Translate the provided string
     * to the current locale.
     */
    const { currentLocale, t } = useLaravelReactI18n();

    /**
     * Whether the month picker is open.
     */
    const [isOpen, setIsOpen] = useState(false);

    /**
     * The month picked, kept in state so the hidden input
     * submits it and the form reads as dirty.
     */
    const [month, setMonth] = useState<string | null>(defaultValue);

    const selected = month ? toMonth(month) : undefined;

    /**
     * Keep the month picked and close the picker.
     */
    const pick = (value: string | null) => {
        setMonth(value);
        setIsOpen(false);
        markFormDirty();
    };

    return (
        <>
            <input name={name} type="hidden" value={month ?? ""} />
            <Popover onOpenChange={setIsOpen} open={isOpen}>
                <PopoverTrigger
                    render={
                        <Button
                            aria-invalid={invalid}
                            className="w-full justify-start font-normal"
                            id={id}
                            type="button"
                            variant="outline"
                        />
                    }
                >
                    <CalendarIcon data-icon="inline-start" />
                    {selected ? (
                        formatMonth(selected, currentLocale())
                    ) : (
                        <span className="text-muted-foreground">{t("Ongoing")}</span>
                    )}
                </PopoverTrigger>
                <PopoverContent align="start" className="w-auto p-0">
                    <Calendar
                        captionLayout="dropdown"
                        defaultMonth={selected}
                        mode="single"
                        onSelect={(day) => day && pick(format(startOfMonth(day), MONTH_FORMAT))}
                        selected={selected}
                    />
                    {clearable && month && (
                        <div className="border-t p-2">
                            <Button
                                className="w-full"
                                onClick={() => pick(null)}
                                size="sm"
                                type="button"
                                variant="ghost"
                            >
                                {t("Ongoing")}
                            </Button>
                        </div>
                    )}
                </PopoverContent>
            </Popover>
        </>
    );
};
