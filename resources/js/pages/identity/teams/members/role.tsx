import { Form, router, usePage } from "@inertiajs/react";
import { useLaravelReactI18n } from "laravel-react-i18n";
import { Fragment } from "react/jsx-runtime";

import { Button } from "@/components/ui/button";
import { Field, FieldDescription, FieldError, FieldGroup, FieldLabel } from "@/components/ui/field";
import { Modal } from "@/components/ui/modal";
import { Select, SelectContent, SelectGroup, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import { Sheet, SheetContent, SheetDescription, SheetFooter, SheetHeader, SheetTitle } from "@/components/ui/sheet";
import { Spinner } from "@/components/ui/spinner";
import members from "@/routes/identity/teams/members";
import { Role } from "@/types/app/modules/core/identity/enums/team/role";
import { User } from "@/types/app/modules/core/identity/models/user";

type PageProps = {
    member: User;
    roles: Array<{ label: string; value: Role }>;
};

export default function EditRole({ member, roles }: PageProps) {
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
        <Modal>
            {({ rootProps }) => (
                <Sheet {...rootProps}>
                    <SheetContent>
                        <Form
                            {...members.role.update.form({
                                team: user?.current_team?.slug ?? "",
                                user: member.id,
                            })}
                            className="contents"
                            disableWhileProcessing
                            onSuccess={() => {
                                rootProps.onOpenChange(false);
                                router.reload({ only: ["members"] });
                            }}
                            options={{ only: ["errors"] }}
                        >
                            {({ errors, invalid, isDirty, processing }) => (
                                <Fragment>
                                    <SheetHeader>
                                        <SheetTitle>{t("Change role")}</SheetTitle>
                                        <SheetDescription>
                                            {t("Choose what :name may do on this team.", { name: member.name })}
                                        </SheetDescription>
                                    </SheetHeader>
                                    <div className="min-h-0 flex-1 overflow-y-auto px-6">
                                        <FieldGroup>
                                            <Field data-invalid={invalid("role") || undefined}>
                                                <FieldLabel htmlFor="member-role">{t("Role")}</FieldLabel>
                                                <Select
                                                    defaultValue={member.membership?.role ?? null}
                                                    id="member-role"
                                                    name="role"
                                                >
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
                                                    {t(
                                                        "Admins manage the team and its people. Members invite people and leave.",
                                                    )}
                                                </FieldDescription>
                                                {invalid("role") && <FieldError>{errors.role}</FieldError>}
                                            </Field>
                                        </FieldGroup>
                                    </div>
                                    <SheetFooter>
                                        <Button disabled={processing || !isDirty} type="submit">
                                            {processing && <Spinner data-icon="inline-start" />}
                                            {t("Save")}
                                        </Button>
                                    </SheetFooter>
                                </Fragment>
                            )}
                        </Form>
                    </SheetContent>
                </Sheet>
            )}
        </Modal>
    );
}
