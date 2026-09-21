import { type FormComponentSlotProps } from "@inertiajs/core";
import { Link, usePage } from "@inertiajs/react";
import { useLaravelReactI18n } from "laravel-react-i18n";
import { CoinsIcon, TypeIcon } from "lucide-react";

import { Field, FieldDescription, FieldError, FieldGroup, FieldLabel } from "@/components/ui/field";
import { InputGroup, InputGroupAddon, InputGroupInput, InputGroupText } from "@/components/ui/input-group";
import { trimTrailingZeros } from "@/lib/transactions";
import budgets from "@/routes/finance/teams/budgets";
import { Currency } from "@/types/app/modules/core/catalog/models/currency";
import { Tag } from "@/types/app/modules/core/finance/models/team/tag";

type FormProps = Partial<
    Omit<Tag, "team"> & {
        budgets: Record<string, string>;
    }
>;

type Props = FormComponentSlotProps<FormProps> & {
    /** The currencies a budget may be set in, empty on a plan without them. */
    currencies?: Array<Currency>;
    defaultValues?: FormProps;
};

export const Fields = ({ currencies, defaultValues, errors, invalid }: Props) => {
    /**
     * Translate the provided string
     * to the current locale.
     */
    const t = useLaravelReactI18n().t;

    /**
     * The page's props.
     */
    const { user } = usePage().props;

    return (
        <FieldGroup>
            {/** Name */}
            <Field data-invalid={invalid("name") || undefined}>
                <FieldLabel htmlFor="tag-name">{t("Name")}</FieldLabel>
                <InputGroup>
                    <InputGroupAddon>
                        <TypeIcon />
                    </InputGroupAddon>
                    <InputGroupInput
                        autoFocus
                        aria-invalid={invalid("name")}
                        defaultValue={defaultValues?.name}
                        id="tag-name"
                        name="name"
                        placeholder={t("Enter a name")}
                    />
                </InputGroup>
                {invalid("name") && <FieldError>{errors.name}</FieldError>}
            </Field>

            {/** Monthly budget, one amount per currency the team holds.
             * A plan without budgets receives no currencies at all. */}
            {currencies?.length ? (
                <Field>
                    <FieldLabel>{t("Monthly budget")}</FieldLabel>
                    <div className="flex flex-col gap-2">
                        {currencies.map((currency) => (
                            <InputGroup key={currency.id}>
                                <InputGroupAddon>
                                    <CoinsIcon />
                                </InputGroupAddon>
                                <InputGroupInput
                                    aria-invalid={invalid(`budgets.${currency.id}` as keyof FormProps)}
                                    aria-label={t("Monthly budget in :code", { code: currency.code })}
                                    className="text-right tabular-nums"
                                    defaultValue={
                                        defaultValues?.budgets?.[currency.id]
                                            ? trimTrailingZeros(defaultValues.budgets[currency.id])
                                            : ""
                                    }
                                    inputMode="decimal"
                                    name={`budgets[${currency.id}]`}
                                    placeholder={t("No budget")}
                                />
                                <InputGroupAddon align="inline-end">
                                    <InputGroupText>{currency.code}</InputGroupText>
                                </InputGroupAddon>
                            </InputGroup>
                        ))}
                    </div>
                    <FieldDescription>
                        {t("Applies from this month on. Past months keep the amount they had.")}{" "}
                        <Link
                            className="underline underline-offset-4"
                            href={budgets.index.url({ team: user?.current_team?.slug ?? "" })}
                        >
                            {t("Manage budget periods")}
                        </Link>
                    </FieldDescription>
                    {currencies.map((currency) =>
                        invalid(`budgets.${currency.id}` as keyof FormProps) ? (
                            <FieldError key={currency.id}>
                                {errors[`budgets.${currency.id}` as keyof FormProps]}
                            </FieldError>
                        ) : null,
                    )}
                </Field>
            ) : null}
        </FieldGroup>
    );
};
