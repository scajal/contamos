import { Form } from "@inertiajs/react";
import { useLaravelReactI18n } from "laravel-react-i18n";
import { Fragment } from "react/jsx-runtime";

import { Button } from "@/components/ui/button";
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from "@/components/ui/dialog";
import { Modal } from "@/components/ui/modal";
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
                <Dialog {...rootProps}>
                    <DialogContent>
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
                                    <DialogHeader>
                                        <DialogTitle>{t("Edit :name", { name: t("team") })}</DialogTitle>
                                        <DialogDescription>{t("Edit the team.")}</DialogDescription>
                                    </DialogHeader>
                                    <div className="flex-1">
                                        <Fields defaultValues={team} {...formProps} />
                                    </div>
                                    <DialogFooter>
                                        <Button disabled={formProps.processing || !formProps.isDirty} type="submit">
                                            {formProps.processing && <Spinner data-icon="inline-start" />}
                                            {t("Save")}
                                        </Button>
                                    </DialogFooter>
                                </Fragment>
                            )}
                        </Form>
                    </DialogContent>
                </Dialog>
            )}
        </Modal>
    );
}
