import { type FormComponentSlotProps } from "@inertiajs/core";
import { useLaravelReactI18n } from "laravel-react-i18n";
import { BanknoteIcon, BitcoinIcon, CoinsIcon, LandmarkIcon, type LucideIcon } from "lucide-react";
import { useCallback, useState } from "react";

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
import { InputGroupAddon } from "@/components/ui/input-group";
import { ToggleGroup, ToggleGroupItem } from "@/components/ui/toggle-group";
import { Currency } from "@/types/app/modules/core/catalog/models/currency";
import { AccountType } from "@/types/app/modules/core/finance/enums/account/account-type";
import { Account } from "@/types/app/modules/core/finance/models/team/account";

type FormProps = Partial<Omit<Account, "team"> & { currency_ids: Array<string> }>;

type Props = FormComponentSlotProps<FormProps> & {
    currencies: Array<Currency>;
    defaultValues?: FormProps;
    locked?: ReadonlyArray<keyof FormProps>;
    types: Array<AccountType>;
};

const TYPE_ICONS: Record<AccountType, LucideIcon> = {
    bank: LandmarkIcon,
    cash: BanknoteIcon,
    crypto: BitcoinIcon,
};

export const Fields = ({ currencies: listOfCurrencies, defaultValues, errors, invalid, locked, types }: Props) => {
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
    const [selectedCurrencies, setSelectedCurrencies] = useState<Array<Currency>>(
        () => listOfCurrencies.filter((currency) => defaultValues?.currency_ids?.includes(currency.id)),
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
     * Anchor the currencies dropdown to the full chips field.
     */
    const currenciesAnchor = useComboboxAnchor();

    /**
     * Check if the field is locked.
     */
    const isLocked = useCallback((field: keyof FormProps): boolean => locked?.includes(field) ?? false, [locked]);

    return (
        <FieldGroup>
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
                        requestAnimationFrame(() => {
                            currenciesAnchor.current?.closest("form")?.dispatchEvent(new Event("input", { bubbles: true }));
                        });
                    }}
                    value={selectedCurrencies}
                >
                    <ComboboxChips
                        aria-invalid={invalid("currency_ids")}
                        className="w-full has-[[data-slot=input-group-addon]]:pl-0"
                        ref={currenciesAnchor}
                    >
                        <InputGroupAddon className="self-start">
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
                            placeholder={t("Select currencies")}
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
        </FieldGroup>
    );
};
