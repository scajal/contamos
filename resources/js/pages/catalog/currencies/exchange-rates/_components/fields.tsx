import { type FormComponentSlotProps } from "@inertiajs/core";
import { useLaravelReactI18n } from "laravel-react-i18n";
import { debounce } from "lodash";
import { CalendarIcon } from "lucide-react";
import { useCallback, useMemo } from "react";

import { FieldDescription } from "@/components/ui/field";
import { Field, FieldError, FieldLabel } from "@/components/ui/field";
import { InputGroup, InputGroupAddon, InputGroupInput } from "@/components/ui/input-group";
import { ExchangeRate } from "@/types/app/modules/core/catalog/models/currency/exchange-rate";

type FormProps = Partial<Omit<ExchangeRate, "currency" | "recorder">>;

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

export const Fields = ({
    defaultValues,
    errors,
    invalid,
    locked,
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

    /**
     * Check if the field is locked.
     */
    const isLocked = useCallback((field: keyof FormProps): boolean => locked?.includes(field) ?? false, [locked]);

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
        </div>
    );
};
