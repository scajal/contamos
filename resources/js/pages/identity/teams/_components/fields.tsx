import { type FormComponentSlotProps } from "@inertiajs/core";
import { useLaravelReactI18n } from "laravel-react-i18n";
import { LinkIcon, TypeIcon } from "lucide-react";

import { Field, FieldError, FieldGroup, FieldLabel } from "@/components/ui/field";
import { InputGroup, InputGroupAddon, InputGroupInput } from "@/components/ui/input-group";
import { Team } from "@/types/app/modules/core/identity/models/team";

type FormProps = Partial<Pick<Team, "name" | "slug">>;

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
                <FieldLabel htmlFor="name">{t("Name")}</FieldLabel>
                <InputGroup>
                    <InputGroupAddon>
                        <TypeIcon />
                    </InputGroupAddon>
                    <InputGroupInput
                        autoFocus
                        aria-invalid={invalid("name")}
                        defaultValue={defaultValues?.name}
                        id="name"
                        name="name"
                        placeholder={t("Enter a name")}
                    />
                </InputGroup>
                {invalid("name") && <FieldError>{errors.name}</FieldError>}
            </Field>

            {/** Handle */}
            <Field data-invalid={invalid("slug") || undefined}>
                <FieldLabel htmlFor="handle">{t("Handle")}</FieldLabel>
                <InputGroup>
                    <InputGroupAddon>
                        <LinkIcon />
                    </InputGroupAddon>
                    <InputGroupInput
                        defaultValue={defaultValues?.slug}
                        id="handle"
                        name="slug"
                        placeholder={t("Enter a slug")}
                    />
                </InputGroup>
                {invalid("slug") && <FieldError>{errors.slug}</FieldError>}
            </Field>
        </FieldGroup>
    );
};
