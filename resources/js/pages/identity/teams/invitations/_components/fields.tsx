import { type FormComponentSlotProps } from "@inertiajs/core";
import { usePage } from "@inertiajs/react";
import { ModalLink } from "@inertiaui/modal-react";
import { useLaravelReactI18n } from "laravel-react-i18n";
import { MailIcon, SparklesIcon } from "lucide-react";

import { Field, FieldDescription, FieldError, FieldGroup, FieldLabel } from "@/components/ui/field";
import { InputGroup, InputGroupAddon, InputGroupInput } from "@/components/ui/input-group";
import { Select, SelectContent, SelectGroup, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import subscriptions from "@/routes/billing/teams/subscriptions";
import { TeamInvitation } from "@/types/app/modules/core/identity/models/team/team-invitation";
import { RoleOption } from "@/types/app/modules/core/identity/role-option";

type FormProps = Partial<Pick<TeamInvitation, "email" | "role">>;

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

    /**
     * The page's props.
     */
    const { user } = usePage().props;

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

            {/** Role */}
            {roles.length > 0 ? (
                <Field data-invalid={invalid("role") || undefined}>
                    <FieldLabel htmlFor="invitation-role">{t("Role")}</FieldLabel>
                    <Select defaultValue={defaultValues?.role ?? "member"} id="invitation-role" name="role">
                        <SelectTrigger aria-invalid={invalid("role")}>
                            <SelectValue />
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
            ) : (
                <Field>
                    <input name="role" type="hidden" value="admin" />
                    <FieldDescription>
                        <ModalLink
                            className="inline-flex items-center gap-1 underline underline-offset-4"
                            href={subscriptions.upgrade.url(
                                { team: user?.current_team?.slug ?? "" },
                                { query: { feature: "members:roles" } },
                            )}
                        >
                            <SparklesIcon className="size-3" />
                            {t("Everyone joins as an admin. Inviting members is a Pro feature.")}
                        </ModalLink>
                    </FieldDescription>
                </Field>
            )}
        </FieldGroup>
    );
};
