import { Form, router, usePage } from "@inertiajs/react";
import { useLaravelReactI18n } from "laravel-react-i18n";
import { Fragment } from "react/jsx-runtime";

import { Button } from "@/components/ui/button";
import { Modal } from "@/components/ui/modal";
import { Sheet, SheetContent, SheetDescription, SheetFooter, SheetHeader, SheetTitle } from "@/components/ui/sheet";
import { Spinner } from "@/components/ui/spinner";
import transfers from "@/routes/finance/teams/transfers";
import { Account } from "@/types/app/modules/core/finance/models/team/account";
import { Tag } from "@/types/app/modules/core/finance/models/team/tag";

import { Fields } from "./_components/fields";

type PageProps = {
    accounts: Array<Account>;
    tags: Array<Tag>;
};

export default function Create({ accounts, tags }: PageProps) {
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
                            {...transfers.store.form({ team: user?.current_team?.slug ?? "" })}
                            className="contents"
                            disableWhileProcessing
                            onSuccess={() => {
                                rootProps.onOpenChange(false);
                                router.reload({ reset: ["transactions"] });
                            }}
                            options={{ only: ["errors"] }}
                            transform={(data) => ({
                                ...data,
                                timezone: Intl.DateTimeFormat().resolvedOptions().timeZone,
                            })}
                        >
                            {(formProps) => (
                                <Fragment>
                                    <SheetHeader>
                                        <SheetTitle>{t("Register a :name", { name: t("transfer") })}</SheetTitle>
                                        <SheetDescription>
                                            {t("Move money between two of this team's accounts.")}
                                        </SheetDescription>
                                    </SheetHeader>
                                    <div className="min-h-0 flex-1 overflow-y-auto overscroll-contain px-6">
                                        <Fields accounts={accounts} tags={tags} {...formProps} />
                                    </div>
                                    <SheetFooter>
                                        <Button disabled={formProps.processing || !formProps.isDirty} type="submit">
                                            {formProps.processing && <Spinner data-icon="inline-start" />}
                                            {t("Register this :name", { name: t("transfer") })}
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
