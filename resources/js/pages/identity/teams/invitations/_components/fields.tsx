import { type FormComponentSlotProps } from "@inertiajs/core";
import { useLaravelReactI18n } from "laravel-react-i18n";
import { MailIcon } from "lucide-react";

import { Field, FieldDescription, FieldError, FieldGroup, FieldLabel } from "@/components/ui/field";
import { InputGroup, InputGroupAddon, InputGroupInput } from "@/components/ui/input-group";
import { TeamInvitation } from "@/types/app/modules/core/identity/models/team/team-invitation";

type FormProps = Partial<Pick<TeamInvitation, "email">>;

type Props = FormComponentSlotProps<FormProps> & {
    defaultValues?: FormProps;
};

export const Fields = ({ defaultValues, errors, invalid }: Props) => {
    /**
     * Translate the provided string
     * to the current locale.
     */
    const t = useLaravelReactI18n().t;

    return (
        <FieldGroup>
            {/** Email */}
            <Field data-invalid={invalid("email") || undefined}>
                <FieldLabel htmlFor="invitation-email">{t("Email")}</FieldLabel>
                <InputGroup>
                    <InputGroupAddon>
                        <MailIcon />
                    </InputGroupAddon>
                    <InputGroupInput
                        autoFocus
                        aria-invalid={invalid("email")}
                        defaultValue={defaultValues?.email}
                        id="invitation-email"
                        name="email"
                        placeholder={t("Enter an email")}
                        type="email"
                    />
                </InputGroup>
                <FieldDescription>{t("We will email an invitation link to this address.")}</FieldDescription>
                {invalid("email") && <FieldError>{errors.email}</FieldError>}
            </Field>
        </FieldGroup>
    );
};
