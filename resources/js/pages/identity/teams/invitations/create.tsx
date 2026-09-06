import { Form, router, usePage } from "@inertiajs/react";
import { useLaravelReactI18n } from "laravel-react-i18n";
import { Fragment } from "react/jsx-runtime";

import { Button } from "@/components/ui/button";
import { Modal } from "@/components/ui/modal";
import { Sheet, SheetContent, SheetDescription, SheetFooter, SheetHeader, SheetTitle } from "@/components/ui/sheet";
import { Spinner } from "@/components/ui/spinner";
import invitations from "@/routes/identity/teams/invitations";

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
                <Sheet {...rootProps}>
                    <SheetContent>
                        <Form
                            {...invitations.store.form({ team: user?.current_team?.slug ?? "" })}
                            className="contents"
                            disableWhileProcessing
                            onSuccess={() => {
                                rootProps.onOpenChange(false);
                                router.reload({ only: ["invitations"] });
                            }}
                            options={{ only: ["errors"] }}
                        >
                            {(formProps) => (
                                <Fragment>
                                    <SheetHeader>
                                        <SheetTitle>{t("Invite member")}</SheetTitle>
                                        <SheetDescription>
                                            {t("Invite someone to share this team's accounts and transactions.")}
                                        </SheetDescription>
                                    </SheetHeader>
                                    <div className="min-h-0 flex-1 overflow-y-auto px-4">
                                        <Fields {...formProps} />
                                    </div>
                                    <SheetFooter>
                                        <Button disabled={formProps.processing || !formProps.isDirty} type="submit">
                                            {formProps.processing && <Spinner data-icon="inline-start" />}
                                            {t("Send invitation")}
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
