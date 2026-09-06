import { type FormComponentSlotProps } from "@inertiajs/core";
import { useLaravelReactI18n } from "laravel-react-i18n";
import { CalendarIcon, DollarSignIcon, ListIcon, TagIcon } from "lucide-react";
import { useRef, useState } from "react";

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
import { InputGroup, InputGroupAddon, InputGroupButton, InputGroupInput } from "@/components/ui/input-group";
import { Textarea } from "@/components/ui/textarea";
import { markFormDirty } from "@/lib/utils";
import { Currency } from "@/types/app/modules/core/catalog/models/currency";
import { Account } from "@/types/app/modules/core/finance/models/team/account";
import { Tag } from "@/types/app/modules/core/finance/models/team/tag";
import { Transaction } from "@/types/app/modules/core/finance/models/team/transaction";

type FormProps = Partial<Omit<Transaction, "account" | "currency" | "tags" | "team" | "user"> & { tag_ids: Array<string> }>;

type Props = FormComponentSlotProps<FormProps> & {
    accounts: Array<Account>;
    defaultValues?: FormProps;
    tags: Array<Tag>;
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

export const Fields = ({ accounts, defaultValues, errors, invalid, tags }: Props) => {
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

    /**
     * The tags currently selected for the transaction.
     */
    const [selectedTags, setSelectedTags] = useState<Array<Tag>>(
        () => tags.filter((tag) => defaultValues?.tag_ids?.includes(tag.id)),
    );

    /**
     * Anchor the tags dropdown to the full chips field.
     */
    const tagsAnchor = useComboboxAnchor();
    const fields = useRef<HTMLDivElement>(null);

    return (
        <FieldGroup ref={fields}>
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
                        markFormDirty(fields.current);
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
                    onValueChange={(value) => {
                        setCurrency(value);
                        markFormDirty(fields.current);
                    }}
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

            {/** Tags */}
            <Field data-invalid={invalid("tag_ids") || undefined}>
                <FieldLabel htmlFor="transaction-tags">{t("Tags")}</FieldLabel>
                <FieldDescription>{t("Choose the tags for this transaction.")}</FieldDescription>
                <Combobox<Tag, true>
                    isItemEqualToValue={(left, right) => left.id === right.id}
                    items={tags}
                    itemToStringLabel={(item) => item.name}
                    itemToStringValue={(item) => item.id}
                    multiple
                    name="tag_ids[]"
                    onValueChange={(value) => {
                        setSelectedTags(value);
                        markFormDirty(fields.current);
                    }}
                    value={selectedTags}
                >
                    <ComboboxChips
                        aria-invalid={invalid("tag_ids")}
                        className="w-full has-[[data-slot=input-group-addon]]:pl-0"
                        ref={tagsAnchor}
                    >
                        <InputGroupAddon className="self-start">
                            <TagIcon />
                        </InputGroupAddon>
                        <ComboboxValue>
                            {selectedTags.map((tag) => (
                                <ComboboxChip key={tag.id}>{tag.name}</ComboboxChip>
                            ))}
                        </ComboboxValue>
                        <ComboboxChipsInput id="transaction-tags" placeholder={t("Select tags")} />
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
