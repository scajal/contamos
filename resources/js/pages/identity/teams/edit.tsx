import { Form } from "@inertiajs/react";
import { useLaravelReactI18n } from "laravel-react-i18n";
import { Fragment } from "react/jsx-runtime";

import { Button } from "@/components/ui/button";
import { Modal } from "@/components/ui/modal";
import { Sheet, SheetContent, SheetDescription, SheetFooter, SheetHeader, SheetTitle } from "@/components/ui/sheet";
import { Spinner } from "@/components/ui/spinner";
import settings from "@/routes/identity/teams/settings";
import { Team } from "@/types/app/modules/core/identity/models/team";

import { Fields } from "./_components/fields";

type PageProps = {
    team: Team;
};

export default function Edit({ team }: PageProps) {
    /**
     * Translate the provided string
     * to the current locale.
     */
    const t = useLaravelReactI18n().t;

    return (
        <Modal>
            {({ rootProps }) => (
                <Sheet {...rootProps}>
                    <SheetContent>
                        {/**
                         * Renaming regenerates the slug, so the server redirects
                         * to the new settings URL instead of coming back here.
                         * The full visit replaces the base page and closes the modal.
                         */}
                        <Form
                            {...settings.update.form({ team: team.slug })}
                            className="contents"
                            disableWhileProcessing
                        >
                            {(formProps) => (
                                <Fragment>
                                    <SheetHeader>
                                        <SheetTitle>{t("Edit :name", { name: t("team") })}</SheetTitle>
                                        <SheetDescription>{t("Edit the team.")}</SheetDescription>
                                    </SheetHeader>
                                    <div className="min-h-0 flex-1 overflow-y-auto px-6">
                                        <Fields defaultValues={team} {...formProps} />
                                    </div>
                                    <SheetFooter>
                                        <Button disabled={formProps.processing || !formProps.isDirty} type="submit">
                                            {formProps.processing && <Spinner data-icon="inline-start" />}
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
