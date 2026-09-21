import { type FormComponentSlotProps } from "@inertiajs/core";
import { useLaravelReactI18n } from "laravel-react-i18n";

import { Field, FieldDescription, FieldError, FieldGroup, FieldLabel } from "@/components/ui/field";
import { Select, SelectContent, SelectGroup, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import { Role } from "@/types/app/modules/core/identity/enums/team/role";
import { RoleOption } from "@/types/app/modules/core/identity/role-option";

type FormProps = {
    role?: Role;
};

type Props = FormComponentSlotProps<FormProps> & {
    defaultValues?: FormProps;
    roles: Array<RoleOption>;
};

export const Fields = ({ defaultValues, errors, invalid, roles }: Props) => {
    /**
     * Translate the provided string
     * to the current locale.
     */
    const t = useLaravelReactI18n().t;

    return (
        <FieldGroup>
            {/** Role */}
            <Field data-invalid={invalid("role") || undefined}>
                <FieldLabel htmlFor="member-role">{t("Role")}</FieldLabel>
                <Select defaultValue={defaultValues?.role ?? null} id="member-role" name="role">
                    <SelectTrigger aria-invalid={invalid("role")}>
                        <SelectValue>
                            {(value: Role | null) => roles.find((role) => role.value === value)?.label ?? ""}
                        </SelectValue>
                    </SelectTrigger>
                    <SelectContent>
                        <SelectGroup>
                            {roles.map((role) => (
                                <SelectItem key={role.value} value={role.value}>
                                    {role.label}
                                </SelectItem>
                            ))}
                        </SelectGroup>
                    </SelectContent>
                </Select>
                <FieldDescription>
                    {t("Admins manage the team and its people. Members invite people and leave.")}
                </FieldDescription>
                {invalid("role") && <FieldError>{errors.role}</FieldError>}
            </Field>
        </FieldGroup>
    );
};
