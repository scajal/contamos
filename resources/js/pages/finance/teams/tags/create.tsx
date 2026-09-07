import { Form, router, usePage } from "@inertiajs/react";
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
import tags from "@/routes/finance/teams/tags";

import { Fields } from "./_components/fields";

export default function Create() {
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
                <Dialog {...rootProps}>
                    <DialogContent>
                        <Form
                            {...tags.store.form({ team: user?.current_team?.slug ?? "" })}
                            className="contents"
                            disableWhileProcessing
                            onSuccess={() => {
                                rootProps.onOpenChange(false);
                                router.reload({ only: ["tags"] });
                            }}
                            options={{ only: ["errors"] }}
                        >
                            {(formProps) => (
                                <Fragment>
                                    <DialogHeader>
                                        <DialogTitle>{t("Create :name", { name: t("tag") })}</DialogTitle>
                                        <DialogDescription>{t("Name the tag.")}</DialogDescription>
                                    </DialogHeader>
                                    <div className="flex-1">
                                        <Fields {...formProps} />
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
