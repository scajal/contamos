import { type FormComponentSlotProps } from "@inertiajs/core";
import { useLaravelReactI18n } from "laravel-react-i18n";
import { BanknoteIcon, BitcoinIcon, CircleDashedIcon, LandmarkIcon, type LucideIcon } from "lucide-react";
import { useCallback, useState } from "react";

import { Checkbox } from "@/components/ui/checkbox";
import { Empty, EmptyHeader, EmptyMedia, EmptyTitle } from "@/components/ui/empty";
import {
    Field,
    FieldDescription,
    FieldError,
    FieldGroup,
    FieldLabel,
    FieldLegend,
    FieldSet,
} from "@/components/ui/field";
import { Input } from "@/components/ui/input";
import { Item, ItemContent, ItemDescription, ItemGroup, ItemMedia, ItemTitle } from "@/components/ui/item";
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
                            setType(value[0] as AccountType | undefined);
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
            <FieldSet data-invalid={invalid("currency_ids") || undefined}>
                <FieldLegend variant="label">{t("Currencies")}</FieldLegend>
                <FieldDescription>{t("Choose the currencies this account can hold.")}</FieldDescription>
                <div className="overflow-hidden rounded-sm border">
                    {!type ? (
                        <Empty>
                            <EmptyHeader>
                                <EmptyMedia variant="icon">
                                    <CircleDashedIcon />
                                </EmptyMedia>
                                <EmptyTitle>{t("Select a type to see available currencies.")}</EmptyTitle>
                            </EmptyHeader>
                        </Empty>
                    ) : null}
                    {type && !currencies.length ? (
                        <Empty>
                            <EmptyHeader>
                                <EmptyTitle>{t("No currencies found")}</EmptyTitle>
                            </EmptyHeader>
                        </Empty>
                    ) : null}
                    {currencies.length > 0 ? (
                        <ItemGroup className="gap-0">
                            {currencies.map((currency) => (
                                <Item
                                    className="rounded-none border-0 border-b last:border-b-0"
                                    key={currency.id}
                                    size="sm"
                                    variant="outline"
                                >
                                    <ItemMedia>
                                        <Checkbox
                                            aria-invalid={invalid("currency_ids")}
                                            defaultChecked={defaultValues?.currency_ids?.includes(currency.id)}
                                            disabled={isLocked("currency_ids")}
                                            id={`currency-${currency.id}`}
                                            name="currency_ids[]"
                                            value={currency.id}
                                        />
                                    </ItemMedia>
                                    <ItemContent>
                                        <ItemTitle>
                                            <label htmlFor={`currency-${currency.id}`}>{currency.name}</label>
                                        </ItemTitle>
                                        <ItemDescription>{currency.code}</ItemDescription>
                                    </ItemContent>
                                </Item>
                            ))}
                        </ItemGroup>
                    ) : null}
                </div>
                {invalid("currency_ids") && <FieldError>{errors.currency_ids}</FieldError>}
            </FieldSet>
        </FieldGroup>
    );
};
