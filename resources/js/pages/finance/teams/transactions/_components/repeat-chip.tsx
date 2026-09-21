import { useLaravelReactI18n } from "laravel-react-i18n";
import { RepeatIcon } from "lucide-react";

import { ChipSetCheck } from "@/components/chip-set-check";
import { Button } from "@/components/ui/button";
import { Calendar } from "@/components/ui/calendar";
import { Checkbox } from "@/components/ui/checkbox";
import { Field, FieldError, FieldLabel } from "@/components/ui/field";
import { InputGroup, InputGroupAddon, InputGroupButton, InputGroupInput } from "@/components/ui/input-group";
import { Popover, PopoverContent, PopoverTrigger } from "@/components/ui/popover";
import {
    Select,
    SelectContent,
    SelectGroup,
    SelectItem,
    SelectTriggerButton,
    SelectValue,
} from "@/components/ui/select";
import { frequencyUnitKey, parseDateTimeLocal } from "@/lib/transactions";
import { markFormDirty } from "@/lib/utils";
import { Frequency } from "@/types/app/modules/core/finance/enums/recurring-rule/frequency";

export type RepeatValue = {
    ends_on: string | null;
    frequency: Frequency;
    interval: string;
    repeat: boolean;
};

const FREQUENCIES: Array<Frequency> = ["daily", "weekly", "monthly", "yearly"];

/**
 * The recurring schedule attached to a new transaction: whether it repeats,
 * how often, and until when. The rule's start date and time follow the
 * transaction's own occurred-at date and time.
 */
export const RepeatChip = ({
    errors,
    fields,
    invalid,
    onChange,
    value,
}: {
    errors: { ends_on?: string; frequency?: string; interval?: string };
    fields: React.RefObject<HTMLDivElement | null>;
    invalid: (key: "recurring_ends_on" | "recurring_frequency" | "recurring_interval") => boolean;
    onChange: (value: RepeatValue) => void;
    value: RepeatValue;
}) => {
    /**
     * Translate the provided string
     * to the current locale.
     */
    const { currentLocale, t } = useLaravelReactI18n();

    return (
        <Popover>
            <PopoverTrigger
                className="w-full min-w-0"
                render={<Button className="w-full min-w-0 justify-start max-sm:h-10" type="button" variant="outline" />}
            >
                <RepeatIcon data-icon="inline-start" />
                <span className="min-w-0 truncate">{t("Repeat")}</span>
                <ChipSetCheck show={value.repeat} />
            </PopoverTrigger>
            <PopoverContent align="start" className="w-80">
                <div className="flex flex-col gap-4">
                    <Field orientation="horizontal">
                        <Checkbox
                            checked={value.repeat}
                            id="transaction-repeat"
                            onCheckedChange={(checked) => {
                                onChange({ ...value, repeat: checked === true });
                                markFormDirty(fields.current);
                            }}
                        />
                        <FieldLabel htmlFor="transaction-repeat">{t("Repeat this transaction")}</FieldLabel>
                    </Field>

                    {value.repeat && (
                        <>
                            <Field
                                data-invalid={
                                    invalid("recurring_interval") || invalid("recurring_frequency") || undefined
                                }
                            >
                                <FieldLabel htmlFor="transaction-recurring-interval">{t("Every")}</FieldLabel>
                                <InputGroup>
                                    <InputGroupInput
                                        aria-invalid={invalid("recurring_interval")}
                                        id="transaction-recurring-interval"
                                        max={99}
                                        min={1}
                                        onChange={(event) => {
                                            onChange({ ...value, interval: event.target.value });
                                            markFormDirty(fields.current);
                                        }}
                                        type="number"
                                        value={value.interval}
                                    />
                                    <InputGroupAddon align="inline-end">
                                        <Select
                                            onValueChange={(frequency) => {
                                                if (frequency) {
                                                    onChange({ ...value, frequency: frequency as Frequency });
                                                    markFormDirty(fields.current);
                                                }
                                            }}
                                            value={value.frequency}
                                        >
                                            <InputGroupButton
                                                aria-invalid={invalid("recurring_frequency")}
                                                id="transaction-recurring-frequency"
                                                render={<SelectTriggerButton />}
                                                size="xs"
                                                variant="ghost"
                                            >
                                                <SelectValue>
                                                    {(frequency: string | null) =>
                                                        frequency
                                                            ? t(
                                                                  frequencyUnitKey(
                                                                      frequency as Frequency,
                                                                      Number(value.interval) || 1,
                                                                  ),
                                                              )
                                                            : ""
                                                    }
                                                </SelectValue>
                                            </InputGroupButton>
                                            <SelectContent>
                                                <SelectGroup>
                                                    {FREQUENCIES.map((frequency) => (
                                                        <SelectItem key={frequency} value={frequency}>
                                                            {t(
                                                                frequencyUnitKey(
                                                                    frequency,
                                                                    Number(value.interval) || 1,
                                                                ),
                                                            )}
                                                        </SelectItem>
                                                    ))}
                                                </SelectGroup>
                                            </SelectContent>
                                        </Select>
                                    </InputGroupAddon>
                                </InputGroup>
                                {(invalid("recurring_interval") || invalid("recurring_frequency")) && (
                                    <FieldError>{errors.interval ?? errors.frequency}</FieldError>
                                )}
                            </Field>

                            <Field data-invalid={invalid("recurring_ends_on") || undefined}>
                                <FieldLabel>{t("Ends")}</FieldLabel>
                                <Popover>
                                    <PopoverTrigger
                                        render={<Button className="justify-start" type="button" variant="outline" />}
                                    >
                                        {value.ends_on
                                            ? new Intl.DateTimeFormat(currentLocale(), { dateStyle: "medium" }).format(
                                                  parseDateTimeLocal(`${value.ends_on}T00:00`),
                                              )
                                            : t("Never")}
                                    </PopoverTrigger>
                                    <PopoverContent align="start" className="w-auto">
                                        <Calendar
                                            mode="single"
                                            onSelect={(date) => {
                                                if (date) {
                                                    onChange({
                                                        ...value,
                                                        ends_on: `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, "0")}-${String(date.getDate()).padStart(2, "0")}`,
                                                    });
                                                    markFormDirty(fields.current);
                                                }
                                            }}
                                            selected={
                                                value.ends_on ? parseDateTimeLocal(`${value.ends_on}T00:00`) : undefined
                                            }
                                        />
                                        {value.ends_on && (
                                            <Button
                                                onClick={() => {
                                                    onChange({ ...value, ends_on: null });
                                                    markFormDirty(fields.current);
                                                }}
                                                size="sm"
                                                type="button"
                                                variant="ghost"
                                            >
                                                {t("Never")}
                                            </Button>
                                        )}
                                    </PopoverContent>
                                </Popover>
                                {invalid("recurring_ends_on") && <FieldError>{errors.ends_on}</FieldError>}
                            </Field>
                        </>
                    )}
                </div>
            </PopoverContent>
        </Popover>
    );
};
