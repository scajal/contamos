import { Form, router, usePage } from "@inertiajs/react";
import { useLaravelReactI18n } from "laravel-react-i18n";
import { Fragment } from "react/jsx-runtime";

import { Button } from "@/components/ui/button";
import { Modal } from "@/components/ui/modal";
import { Sheet, SheetContent, SheetDescription, SheetFooter, SheetHeader, SheetTitle } from "@/components/ui/sheet";
import { Spinner } from "@/components/ui/spinner";
import transactions from "@/routes/finance/teams/transactions";
import { TransactionType } from "@/types/app/modules/core/finance/enums/transaction/transaction-type";
import { Account } from "@/types/app/modules/core/finance/models/team/account";

import { Fields } from "./_components/fields";

type PageProps = {
    accounts: Array<Account>;
    type: TransactionType;
};

export default function Create({ accounts, type }: PageProps) {
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
                            {...transactions.store.form({ team: user?.current_team_id ?? "" })}
                            className="contents"
                            disableWhileProcessing
                            onSuccess={() => {
                                rootProps.onOpenChange(false);
                                router.reload({ only: ["transactions"] });
                            }}
                            options={{ only: ["errors"] }}
                        >
                            {(formProps) => (
                                <Fragment>
                                    <SheetHeader>
                                        <SheetTitle>{t("Register an :name", { name: type.toString() })}</SheetTitle>
                                        <SheetDescription>{t("Add a new transaction.")}</SheetDescription>
                                    </SheetHeader>
                                    <div className="min-h-0 flex-1 overflow-y-auto px-4">
                                        <Fields accounts={accounts} defaultValues={{ type }} {...formProps} />
                                    </div>
                                    <SheetFooter>
                                        <Button disabled={formProps.processing || !formProps.isDirty} type="submit">
                                            {formProps.processing && <Spinner />}
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
