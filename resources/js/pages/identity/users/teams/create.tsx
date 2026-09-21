import { Form } from "@inertiajs/react";
import { useLaravelReactI18n } from "laravel-react-i18n";
import { ArrowRightIcon, CirclePlusIcon, TypeIcon } from "lucide-react";
import { Fragment } from "react/jsx-runtime";

import { Button } from "@/components/ui/button";
import { Field, FieldDescription, FieldError, FieldLabel } from "@/components/ui/field";
import { InputGroup, InputGroupAddon, InputGroupInput } from "@/components/ui/input-group";
import { Spinner } from "@/components/ui/spinner";
import { WizardLayout } from "@/layouts/wizard/layout";
import teams from "@/routes/identity/teams";

const Create = () => {
    /**
     * Translate the provided string
     * to the current locale.
     */
    const t = useLaravelReactI18n().t;

    return (
        <div className="flex flex-col gap-8">
            {/** Header */}
            <header className="flex flex-col items-center gap-2 text-center">
                <CirclePlusIcon />
                <h1 className="font-heading mt-4 text-[1.75rem] leading-tight font-semibold tracking-tight">
                    {t("Create :name", { name: t("team") })}
                </h1>
                <p className="text-muted-foreground max-w-xs text-sm">
                    {t(
                        "A team is where your accounts and balances live. Start with your own, or share it with family later.",
                    )}
                </p>
            </header>

            {/** Content */}
            <div className="mx-auto flex w-full max-w-xl flex-col gap-4">
                <Form {...teams.store.form()} className="space-y-6" disableWhileProcessing>
                    {({ errors, invalid, isDirty, processing }) => (
                        <Fragment>
                            {/** Name */}
                            <Field>
                                <FieldLabel>{t("Name")}</FieldLabel>
                                <InputGroup>
                                    <InputGroupAddon>
                                        <TypeIcon />
                                    </InputGroupAddon>
                                    <InputGroupInput
                                        autoFocus
                                        aria-invalid={invalid("code")}
                                        name="name"
                                        placeholder={t("Enter a name")}
                                    />
                                </InputGroup>
                                <FieldDescription>
                                    {t("We will automatically generate a slug for your team.")}
                                </FieldDescription>
                                {invalid("code") && <FieldError>{errors.code}</FieldError>}
                            </Field>

                            {/** Submit */}
                            <div className="flex justify-center">
                                <Button disabled={processing || !isDirty} type="submit">
                                    {processing && <Spinner />}
                                    {t("Create")}
                                    <ArrowRightIcon />
                                </Button>
                            </div>
                        </Fragment>
                    )}
                </Form>
            </div>
        </div>
    );
};

Create.layout = (page: React.ReactNode) => <WizardLayout>{page}</WizardLayout>;

export default Create;
