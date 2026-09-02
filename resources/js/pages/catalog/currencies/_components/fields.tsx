import { type FormComponentSlotProps } from "@inertiajs/core";
import { useLaravelReactI18n } from "laravel-react-i18n";
import { debounce } from "lodash";
import { CheckIcon, DecimalsArrowRightIcon, DollarSignIcon, TypeIcon, XIcon } from "lucide-react";
import { useCallback, useMemo, useRef } from "react";

import { Field, FieldError, FieldLabel } from "@/components/ui/field";
import { InputGroup, InputGroupAddon, InputGroupInput } from "@/components/ui/input-group";
import { Select, SelectContent, SelectGroup, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import { CurrencyType } from "@/types/app/modules/core/catalog/enums/currency/currency-type";
import { ExchangeRateSource } from "@/types/app/modules/core/catalog/enums/currency/exchange-rate/exchange-rate-source";
import { Currency } from "@/types/app/modules/core/catalog/models/currency";

type FormProps = Partial<Currency>;

type Props = FormComponentSlotProps<FormProps> & {
    defaultValues?: FormProps;
    exchangeRateSources: Array<ExchangeRateSource>;
    types: Array<CurrencyType>;
    locked?: ReadonlyArray<keyof FormProps>;
};

export const Fields = ({
    defaultValues,
    errors,
    exchangeRateSources,
    invalid,
    locked,
    touched,
    types,
    valid,
    validate: realTimeValidate,
    validating,
}: Props) => {
    /**
     * Translate the provided string
     * to the current locale.
     */
    const t = useLaravelReactI18n().t;

    /**
     * Handle the debounced validation action.
     */
    const validate = useMemo(
        () => debounce((field: keyof FormProps) => realTimeValidate(field), 500),
        [realTimeValidate],
    );

    /**
     * Check if the field is locked.
     */
    const isLocked = useCallback((field: keyof FormProps): boolean => locked?.includes(field) ?? false, [locked]);

    /**
     * The ref. for the type field.
     */
    const typeInputRef = useRef<HTMLInputElement>(null);

    /**
     * The ref. for the exchange rate source field.
     */
    const exchangeRateSourceInputRef = useRef<HTMLInputElement>(null);

    return (
        <div className="space-y-6">
            {/** Type */}
            <Field>
                <FieldLabel>{t("Type")}</FieldLabel>
                <Select
                    defaultValue={defaultValues?.type}
                    disabled={isLocked("type")}
                    inputRef={typeInputRef}
                    name="type"
                    onValueChange={() =>
                        requestAnimationFrame(() =>
                            typeInputRef.current?.dispatchEvent(new Event("input", { bubbles: true })),
                        )
                    }
                >
                    <SelectTrigger aria-invalid={invalid("type")}>
                        <SelectValue>
                            {(value: CurrencyType | null) => (value ? t(`enums.${value}`) : t("Select a type"))}
                        </SelectValue>
                    </SelectTrigger>
                    <SelectContent>
                        <SelectGroup>
                            {types.map((type) => (
                                <SelectItem key={type} value={type}>
                                    {t(`enums.${type}`)}
                                </SelectItem>
                            ))}
                        </SelectGroup>
                    </SelectContent>
                </Select>
                {invalid("type") && <FieldError>{errors.type}</FieldError>}
            </Field>

            <div className="grid gap-6 sm:grid-cols-2">
                {/** Code */}
                <Field>
                    <FieldLabel>{t("Code")}</FieldLabel>
                    <InputGroup>
                        <InputGroupAddon>
                            <TypeIcon />
                        </InputGroupAddon>
                        <InputGroupInput
                            autoFocus
                            aria-invalid={invalid("code")}
                            defaultValue={defaultValues?.code}
                            disabled={isLocked("code")}
                            name="code"
                            onChange={() => validate("code")}
                            placeholder={t("Enter a code")}
                        />
                        <InputGroupAddon align="inline-end">
                            {!validating && valid("code") && touched("code") && <CheckIcon className="text-lime-500" />}
                            {!validating && invalid("code") && touched("code") && <XIcon className="text-red-500" />}
                        </InputGroupAddon>
                    </InputGroup>
                    {invalid("code") && <FieldError>{errors.code}</FieldError>}
                </Field>

                {/** Symbol */}
                <Field>
                    <FieldLabel>{t("Symbol")}</FieldLabel>
                    <InputGroup>
                        <InputGroupAddon>
                            <DollarSignIcon />
                        </InputGroupAddon>
                        <InputGroupInput
                            aria-invalid={invalid("symbol")}
                            defaultValue={defaultValues?.symbol}
                            disabled={isLocked("symbol")}
                            name="symbol"
                            placeholder={t("Enter a symbol")}
                        />
                    </InputGroup>
                    {invalid("symbol") && <FieldError>{errors.symbol}</FieldError>}
                </Field>
            </div>

            {/** Name */}
            <Field>
                <FieldLabel>{t("Name")}</FieldLabel>
                <InputGroup>
                    <InputGroupAddon>
                        <TypeIcon />
                    </InputGroupAddon>
                    <InputGroupInput
                        aria-invalid={invalid("name")}
                        defaultValue={defaultValues?.name}
                        disabled={isLocked("name")}
                        name="name"
                        placeholder={t("Enter a name")}
                    />
                </InputGroup>
                {invalid("name") && <FieldError>{errors.name}</FieldError>}
            </Field>

            {/** Decimal Places */}
            <Field>
                <FieldLabel>{t("Decimal places")}</FieldLabel>
                <InputGroup>
                    <InputGroupAddon>
                        <DecimalsArrowRightIcon />
                    </InputGroupAddon>
                    <InputGroupInput
                        aria-invalid={invalid("decimal_places")}
                        defaultValue={defaultValues?.decimal_places}
                        disabled={isLocked("decimal_places")}
                        name="decimal_places"
                        placeholder={t("Enter the number of decimal places")}
                        step={1}
                        type="number"
                    />
                </InputGroup>
                {invalid("decimal_places") && <FieldError>{errors.decimal_places}</FieldError>}
            </Field>

            {/** Exchange rate source */}
            <Field>
                <FieldLabel>{t("Exchange rate source")}</FieldLabel>
                <Select
                    defaultValue={defaultValues?.exchange_rate_source}
                    disabled={isLocked("exchange_rate_source")}
                    inputRef={exchangeRateSourceInputRef}
                    name="exchange_rate_source"
                    onValueChange={() =>
                        requestAnimationFrame(() =>
                            exchangeRateSourceInputRef.current?.dispatchEvent(new Event("input", { bubbles: true })),
                        )
                    }
                >
                    <SelectTrigger aria-invalid={invalid("exchange_rate_source")}>
                        <SelectValue>
                            {(value: ExchangeRateSource | null) =>
                                value ? t(`enums.${value}`) : t("Select a exchange rate source")
                            }
                        </SelectValue>
                    </SelectTrigger>
                    <SelectContent>
                        <SelectGroup>
                            {exchangeRateSources.map((source) => (
                                <SelectItem key={source} value={source}>
                                    {t(`enums.${source}`)}
                                </SelectItem>
                            ))}
                        </SelectGroup>
                    </SelectContent>
                </Select>
                {invalid("exchange_rate_source") && <FieldError>{errors.exchange_rate_source}</FieldError>}
            </Field>
        </div>
    );
};
