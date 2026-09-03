import { type FormComponentSlotProps } from "@inertiajs/core";
import { useLaravelReactI18n } from "laravel-react-i18n";
import { CalendarIcon, DollarSignIcon } from "lucide-react";

import { Field, FieldDescription, FieldError, FieldLabel } from "@/components/ui/field";
import { InputGroup, InputGroupAddon, InputGroupInput } from "@/components/ui/input-group";
import { Quote } from "@/types/app/modules/core/catalog/models/currency/quote";

type FormProps = Partial<Omit<Quote, "currency" | "recorder">>;

type Props = FormComponentSlotProps<FormProps> & {
    defaultValues?: FormProps;
    locked?: ReadonlyArray<keyof FormProps>;
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

export const Fields = ({ defaultValues, errors, invalid }: Props) => {
    /**
     * Translate the provided string
     * to the current locale.
     */
    const t = useLaravelReactI18n().t;

    return (
        <div className="space-y-6">
            {/** Date */}
            <Field>
                <FieldLabel>{t("Date")}</FieldLabel>
                <InputGroup>
                    <InputGroupAddon>
                        <CalendarIcon />
                    </InputGroupAddon>
                    <InputGroupInput
                        aria-invalid={invalid("observed_at")}
                        defaultValue={toDateTimeLocalValue(defaultValues?.observed_at)}
                        name="observed_at"
                        type="datetime-local"
                    />
                </InputGroup>
                <FieldDescription>{t("The date from which the quote is available.")}</FieldDescription>
                {invalid("observed_at") && <FieldError>{errors.observed_at}</FieldError>}
            </Field>

            {/** Rate */}
            <Field>
                <FieldLabel>{t("Rate")}</FieldLabel>
                <InputGroup>
                    <InputGroupAddon>
                        <DollarSignIcon />
                    </InputGroupAddon>
                    <InputGroupInput
                        aria-invalid={invalid("rate")}
                        autoComplete="off"
                        defaultValue={defaultValues?.rate}
                        inputMode="decimal"
                        name="rate"
                        placeholder={t("Enter a rate")}
                        spellCheck={false}
                        type="text"
                    />
                </InputGroup>
                {invalid("rate") && <FieldError>{errors.rate}</FieldError>}
            </Field>
        </div>
    );
};
