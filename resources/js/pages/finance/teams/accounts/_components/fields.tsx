import { type FormComponentSlotProps } from "@inertiajs/core";
import { useLaravelReactI18n } from "laravel-react-i18n";
import { TypeIcon } from "lucide-react";

import { Field, FieldError, FieldLabel } from "@/components/ui/field";
import { InputGroup, InputGroupAddon, InputGroupInput } from "@/components/ui/input-group";
import { Select, SelectContent, SelectGroup, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import { AccountType } from "@/types/app/modules/core/finance/enums/account/account-type";
import { Account } from "@/types/app/modules/core/finance/models/team/account";

type FormProps = Partial<Omit<Account, "team">>;

type Props = FormComponentSlotProps<FormProps> & {
    defaultValues?: FormProps;
    types: Array<AccountType>;
};

export const Fields = ({ defaultValues, errors, invalid, types }: Props) => {
    /**
     * Translate the provided string
     * to the current locale.
     */
    const t = useLaravelReactI18n().t;

    return (
        <div className="space-y-6">
            {/** Name */}
            <Field>
                <FieldLabel>{t("Name")}</FieldLabel>
                <InputGroup>
                    <InputGroupAddon>
                        <TypeIcon />
                    </InputGroupAddon>
                    <InputGroupInput
                        autoFocus
                        aria-invalid={invalid("name")}
                        defaultValue={defaultValues?.name}
                        name="name"
                        placeholder={t("Enter a name")}
                    />
                </InputGroup>
                {invalid("name") && <FieldError>{errors.name}</FieldError>}
            </Field>

            {/** Type */}
            <Field>
                <FieldLabel>{t("Type")}</FieldLabel>
                <Select defaultValue={defaultValues?.type} name="type">
                    <SelectTrigger aria-invalid={invalid("type")}>
                        <SelectValue>
                            {(value: AccountType | null) => (value ? t(`enums.${value}`) : t("Select a type"))}
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
        </div>
    );
};
