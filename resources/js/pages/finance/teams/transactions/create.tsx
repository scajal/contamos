import { Form, router, usePage } from "@inertiajs/react";
import { useLaravelReactI18n } from "laravel-react-i18n";
import { useState } from "react";
import { Fragment } from "react/jsx-runtime";

import { Button } from "@/components/ui/button";
import { Modal } from "@/components/ui/modal";
import { Sheet, SheetContent, SheetDescription, SheetFooter, SheetHeader, SheetTitle } from "@/components/ui/sheet";
import { Spinner } from "@/components/ui/spinner";
import transactions from "@/routes/finance/teams/transactions";
import { TransactionType } from "@/types/app/modules/core/finance/enums/transaction/transaction-type";
import { Account } from "@/types/app/modules/core/finance/models/team/account";
import { Tag } from "@/types/app/modules/core/finance/models/team/tag";

import { Fields } from "./_components/fields";

type PageProps = {
    accounts: Array<Account>;
    tags: Array<Tag>;
    type: TransactionType;
};

export default function Create({ accounts, tags, type: initialType }: PageProps) {
    /**
     * Translate the provided string
     * to the current locale.
     */
    const t = useLaravelReactI18n().t;

    /**
     * The page's props.
     */
    const { user } = usePage().props;

    /**
     * Income or expense, toggled in the form without leaving the sheet.
     */
    const [type, setType] = useState(initialType);

    return (
        <Modal>
            {({ rootProps }) => (
                <Sheet {...rootProps}>
                    <SheetContent>
                        <Form
                            {...transactions.store.form({ team: user?.current_team?.slug ?? "" })}
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
                                        <SheetTitle>{t("Register an :name", { name: t(type) })}</SheetTitle>
                                        <SheetDescription>
                                            {accounts.length === 1
                                                ? t("In :name", { name: accounts[0].name })
                                                : type === "income"
                                                  ? t("Record money coming into an account.")
                                                  : t("Record money leaving an account.")}
                                        </SheetDescription>
                                    </SheetHeader>
                                    <div className="min-h-0 flex-1 overflow-y-auto overscroll-contain px-6">
                                        <Fields
                                            accounts={accounts}
                                            defaultValues={{ type: initialType }}
                                            onTypeChange={setType}
                                            tags={tags}
                                            type={type}
                                            {...formProps}
                                        />
                                    </div>
                                    <SheetFooter>
                                        <Button disabled={formProps.processing || !formProps.isDirty} type="submit">
                                            {formProps.processing && <Spinner data-icon="inline-start" />}
                                            {t("Register this :name", { name: t(type) })}
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
