import { type FormComponentSlotProps } from "@inertiajs/core";
import { useLaravelReactI18n } from "laravel-react-i18n";
import { ListIcon, TagIcon, TypeIcon } from "lucide-react";
import { useRef, useState } from "react";

import { CategoryChip } from "@/components/movement-chips";
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
import { InputGroup, InputGroupAddon, InputGroupButton, InputGroupInput } from "@/components/ui/input-group";
import {
    Select,
    SelectContent,
    SelectGroup,
    SelectItem,
    SelectTriggerButton,
    SelectValue,
} from "@/components/ui/select";
import { ToggleGroup, ToggleGroupItem } from "@/components/ui/toggle-group";
import { CategoryOptions } from "@/lib/categories";
import { frequencyUnitKey, TRANSACTION_AMOUNT_COLOR, trimTrailingZeros } from "@/lib/transactions";
import { cn, markFormDirty } from "@/lib/utils";
import { Currency } from "@/types/app/modules/core/catalog/models/currency";
import { Frequency } from "@/types/app/modules/core/finance/enums/recurring-rule/frequency";
import { Category } from "@/types/app/modules/core/finance/enums/transaction/category";
import { TransactionType } from "@/types/app/modules/core/finance/enums/transaction/transaction-type";
import { Account } from "@/types/app/modules/core/finance/models/team/account";
import { RecurringRule } from "@/types/app/modules/core/finance/models/team/recurring-rule";
import { Tag } from "@/types/app/modules/core/finance/models/team/tag";

/**
 * The frequencies a rule may repeat on, in the order the form offers them.
 */
const FREQUENCIES: Array<Frequency> = ["daily", "weekly", "monthly", "yearly"];

type FormProps = Partial<
    Omit<RecurringRule, "account" | "currency" | "tags" | "team" | "user"> & { tag_ids: Array<string> }
>;

type Props = FormComponentSlotProps<FormProps> & {
    accounts: Array<Account>;
    categories: CategoryOptions;
    defaultValues?: FormProps;
    tags: Array<Tag>;
};

export const Fields = ({ accounts, categories, defaultValues, errors, invalid, tags }: Props) => {
    /**
     * Translate the provided string
     * to the current locale.
     */
    const t = useLaravelReactI18n().t;

    /**
     * The fields ref, used to mark the form dirty from controls that
     * write their value in React rather than through a native event.
     */
    const fields = useRef<HTMLDivElement>(null);

    /**
     * Income or expense.
     */
    const [type, setType] = useState<TransactionType>(defaultValues?.type ?? "expense");

    /**
     * The account the occurrences are written to.
     */
    const [account, setAccount] = useState<Account | null>(
        () =>
            accounts.find((candidate) => candidate.id === defaultValues?.account_id) ??
            (accounts.length === 1 ? accounts[0] : null),
    );

    /**
     * The currency the amount is held in.
     */
    const [currency, setCurrency] = useState<Currency | null>(() => {
        const currencies =
            accounts.find((candidate) => candidate.id === defaultValues?.account_id)?.currencies ??
            (accounts.length === 1 ? (accounts[0]?.currencies ?? []) : []);

        return (
            currencies.find((candidate) => candidate.id === defaultValues?.currency_id) ??
            (currencies.length === 1 ? currencies[0] : null)
        );
    });

    /**
     * How often the rule repeats, kept in state so the unit beside the
     * interval reads as a noun ("2 months", not "Monthly").
     */
    /**
     * The category every transaction the rule generates
     * is filed under.
     */
    const [category, setCategory] = useState<Category | null>(defaultValues?.category ?? null);

    const [frequency, setFrequency] = useState<Frequency>(defaultValues?.frequency ?? "monthly");

    /**
     * How many units of the frequency sit between two occurrences.
     */
    const [interval, setInterval] = useState(String(defaultValues?.interval ?? 1));

    /**
     * The tags every occurrence carries.
     */
    const [selectedTags, setSelectedTags] = useState<Array<Tag>>(() =>
        tags.filter((tag) => defaultValues?.tag_ids?.includes(tag.id)),
    );

    /**
     * Anchor the tags dropdown to the full chips field.
     */
    const tagsAnchor = useComboboxAnchor();

    return (
        <FieldGroup ref={fields}>
            {/** Timezone: the schedule is read in the member's own zone. */}
            <input
                name="timezone"
                type="hidden"
                value={defaultValues?.timezone ?? Intl.DateTimeFormat().resolvedOptions().timeZone}
            />

            {/** Type */}
            <input name="type" type="hidden" value={type} />
            <Field>
                <FieldLabel className="sr-only" id="rule-type-label">
                    {t("Type")}
                </FieldLabel>
                <ToggleGroup
                    aria-labelledby="rule-type-label"
                    className="w-full"
                    onValueChange={(value) => {
                        const next = value[0];

                        if (next === "income" || next === "expense") {
                            setType(next);

                            // The two lists do not overlap, so a
                            // category never survives a switch.
                            setCategory(null);
                            markFormDirty(fields.current);
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

            {/** Amount & Currency */}
            <Field data-invalid={invalid("amount") || invalid("currency_id") || undefined}>
                <FieldLabel htmlFor="rule-amount">{t("Amount")}</FieldLabel>
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
                            className={cn(
                                "h-auto py-2 text-right text-3xl font-semibold tabular-nums md:text-3xl",
                                TRANSACTION_AMOUNT_COLOR[type],
                            )}
                            defaultValue={defaultValues?.amount ? trimTrailingZeros(defaultValues.amount) : undefined}
                            disabled={!account}
                            id="rule-amount"
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

            {/** Description */}
            <Field data-invalid={invalid("description") || undefined}>
                <FieldLabel htmlFor="rule-description">{t("Description")}</FieldLabel>
                <InputGroup>
                    <InputGroupAddon>
                        <TypeIcon />
                    </InputGroupAddon>
                    <InputGroupInput
                        aria-invalid={invalid("description")}
                        defaultValue={defaultValues?.description ?? ""}
                        id="rule-description"
                        name="description"
                        placeholder={t("Enter a description")}
                    />
                </InputGroup>
                {invalid("description") && <FieldError>{errors.description}</FieldError>}
            </Field>

            {/** Frequency & interval */}
            <input name="frequency" type="hidden" value={frequency} />
            <Field data-invalid={invalid("interval") || invalid("frequency") || undefined}>
                <FieldLabel htmlFor="rule-interval">{t("Every")}</FieldLabel>
                <InputGroup>
                    <InputGroupInput
                        aria-invalid={invalid("interval")}
                        id="rule-interval"
                        max={99}
                        min={1}
                        name="interval"
                        onChange={(event) => setInterval(event.target.value)}
                        type="number"
                        value={interval}
                    />
                    <InputGroupAddon align="inline-end">
                        <Select
                            onValueChange={(value) => {
                                if (value) {
                                    setFrequency(value as Frequency);
                                    markFormDirty(fields.current);
                                }
                            }}
                            value={frequency}
                        >
                            <InputGroupButton
                                aria-invalid={invalid("frequency")}
                                id="rule-frequency"
                                render={<SelectTriggerButton />}
                                size="xs"
                                variant="ghost"
                            >
                                <SelectValue>
                                    {(value: string | null) =>
                                        value ? t(frequencyUnitKey(value as Frequency, Number(interval) || 1)) : ""
                                    }
                                </SelectValue>
                            </InputGroupButton>
                            <SelectContent>
                                <SelectGroup>
                                    {FREQUENCIES.map((value) => (
                                        <SelectItem key={value} value={value}>
                                            {t(frequencyUnitKey(value, Number(interval) || 1))}
                                        </SelectItem>
                                    ))}
                                </SelectGroup>
                            </SelectContent>
                        </Select>
                    </InputGroupAddon>
                </InputGroup>
                {(invalid("interval") || invalid("frequency")) && (
                    <FieldError>{errors.interval ?? errors.frequency}</FieldError>
                )}
            </Field>

            {/** Starts on & time of day */}
            <Field data-invalid={invalid("starts_on") || undefined}>
                <FieldLabel htmlFor="rule-starts-on">{t("Starts")}</FieldLabel>
                <div className="grid gap-2 sm:grid-cols-2">
                    <Input
                        aria-invalid={invalid("starts_on")}
                        defaultValue={defaultValues?.starts_on?.slice(0, 10)}
                        id="rule-starts-on"
                        name="starts_on"
                        required
                        type="date"
                    />
                    <Input
                        aria-invalid={invalid("time_of_day")}
                        defaultValue={(defaultValues?.time_of_day ?? "09:00:00").slice(0, 5)}
                        id="rule-time-of-day"
                        name="time_of_day"
                        required
                        type="time"
                    />
                </div>
                <FieldDescription>
                    {t("The start date fixes the day the rule falls on, and the time is read in your timezone.")}
                </FieldDescription>
                {invalid("starts_on") && <FieldError>{errors.starts_on}</FieldError>}
                {invalid("time_of_day") && <FieldError>{errors.time_of_day}</FieldError>}
            </Field>

            {/** Ends on */}
            <Field data-invalid={invalid("ends_on") || undefined}>
                <FieldLabel htmlFor="rule-ends-on">{t("Ends")}</FieldLabel>
                <Input
                    aria-invalid={invalid("ends_on")}
                    defaultValue={defaultValues?.ends_on?.slice(0, 10) ?? ""}
                    id="rule-ends-on"
                    name="ends_on"
                    type="date"
                />
                <FieldDescription>{t("Leave it empty to repeat forever.")}</FieldDescription>
                {invalid("ends_on") && <FieldError>{errors.ends_on}</FieldError>}
            </Field>

            {/** Category */}
            <input name="category" type="hidden" value={category ?? ""} />
            <Field data-invalid={invalid("category") || undefined}>
                <FieldLabel htmlFor="rule-category">{t("Category")}</FieldLabel>
                <CategoryChip
                    categories={categories[type] ?? []}
                    category={category}
                    error={errors.category}
                    fields={fields}
                    idPrefix="rule"
                    invalid={invalid("category")}
                    onCategoryChange={setCategory}
                />
                {invalid("category") && <FieldError>{errors.category}</FieldError>}
            </Field>

            {/** Tags */}
            {selectedTags.map((tag) => (
                <input key={tag.id} name="tag_ids[]" type="hidden" value={tag.id} />
            ))}
            <Field data-invalid={invalid("tag_ids") || undefined}>
                <FieldLabel htmlFor="rule-tags">{t("Tags")}</FieldLabel>
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
                    <ComboboxChips aria-invalid={invalid("tag_ids")} className="w-full" ref={tagsAnchor}>
                        <InputGroupAddon>
                            <TagIcon />
                        </InputGroupAddon>
                        <ComboboxValue>
                            {selectedTags.map((tag) => (
                                <ComboboxChip key={tag.id}>{tag.name}</ComboboxChip>
                            ))}
                        </ComboboxValue>
                        <ComboboxChipsInput
                            id="rule-tags"
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
        </FieldGroup>
    );
};
