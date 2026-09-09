import { type FormComponentSlotProps } from "@inertiajs/core";
import { useLaravelReactI18n } from "laravel-react-i18n";
import { TypeIcon } from "lucide-react";

import { Field, FieldDescription, FieldError, FieldGroup, FieldLabel } from "@/components/ui/field";
import { InputGroup, InputGroupAddon, InputGroupInput } from "@/components/ui/input-group";
import { Team } from "@/types/app/modules/core/identity/models/team";

type FormProps = Partial<Pick<Team, "name">>;

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
            {/** Name */}
            <Field data-invalid={invalid("name") || undefined}>
                <FieldLabel htmlFor="team-name">{t("Name")}</FieldLabel>
                <InputGroup>
                    <InputGroupAddon>
                        <TypeIcon />
                    </InputGroupAddon>
                    <InputGroupInput
                        autoFocus
                        aria-invalid={invalid("name")}
                        defaultValue={defaultValues?.name}
                        id="team-name"
                        name="name"
                        placeholder={t("Enter a name")}
                    />
                </InputGroup>
                <FieldDescription>
                    {t("Changing the name also changes the slug, so the team's links will change.")}
                </FieldDescription>
                {invalid("name") && <FieldError>{errors.name}</FieldError>}
            </Field>
        </FieldGroup>
    );
};
