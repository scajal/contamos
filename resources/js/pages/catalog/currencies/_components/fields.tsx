import { type FormComponentSlotProps } from "@inertiajs/core";
import { useLaravelReactI18n } from "laravel-react-i18n";
import { debounce } from "lodash";
import { CheckIcon, DecimalsArrowRightIcon, DollarSignIcon, TypeIcon, XIcon } from "lucide-react";
import { useMemo } from "react";

import { Field, FieldError, FieldLabel } from "@/components/ui/field";
import { InputGroup, InputGroupAddon, InputGroupInput } from "@/components/ui/input-group";
import { Currency } from "@/types/app/modules/core/catalog/models/currency";

type FormProps = Partial<Currency>;

type Props = FormComponentSlotProps<FormProps> & {
    defaultValues?: FormProps;
};

export const Fields = ({
    defaultValues,
    errors,
    invalid,
    touched,
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

    return (
        <div className="space-y-6">
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
                        name="decimal_places"
                        placeholder={t("Enter the number of decimal places")}
                        step={1}
                        type="number"
                    />
                </InputGroup>
                {invalid("decimal_places") && <FieldError>{errors.decimal_places}</FieldError>}
            </Field>
        </div>
    );
};
