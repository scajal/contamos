import { Form, router, usePage } from "@inertiajs/react";
import { useLaravelReactI18n } from "laravel-react-i18n";
import { useState } from "react";
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
import { CategoryOptions } from "@/lib/categories";
import transactions from "@/routes/finance/teams/transactions";
import { TransactionType } from "@/types/app/modules/core/finance/enums/transaction/transaction-type";
import { Account } from "@/types/app/modules/core/finance/models/team/account";
import { Tag } from "@/types/app/modules/core/finance/models/team/tag";

import { Fields } from "./_components/fields";

type PageProps = {
    accounts: Array<Account>;
    canRecur: boolean;
    categories: CategoryOptions;
    tags: Array<Tag>;
    type: TransactionType;
};

export default function Create({ accounts, canRecur, categories, tags, type: initialType }: PageProps) {
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
     * Income or expense, toggled in the form without leaving the dialog.
     */
    const [type, setType] = useState(initialType);

    return (
        <Modal>
            {({ rootProps }) => (
                <Dialog {...rootProps}>
                    <DialogContent>
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
                                    <DialogHeader>
                                        <DialogTitle>{t("Register an :name", { name: t(type) })}</DialogTitle>
                                        <DialogDescription>
                                            {accounts.length === 1
                                                ? t("In :name", { name: accounts[0].name })
                                                : type === "income"
                                                  ? t("Record money coming into an account.")
                                                  : t("Record money leaving an account.")}
                                        </DialogDescription>
                                    </DialogHeader>
                                    <div className="flex-1">
                                        <Fields
                                            accounts={accounts}
                                            allowRepeat
                                            canRepeat={canRecur}
                                            categories={categories}
                                            defaultValues={{ type: initialType }}
                                            onTypeChange={setType}
                                            tags={tags}
                                            type={type}
                                            {...formProps}
                                        />
                                    </div>
                                    <DialogFooter>
                                        <Button disabled={formProps.processing || !formProps.isDirty} type="submit">
                                            {formProps.processing && <Spinner data-icon="inline-start" />}
                                            {t("Register this :name", { name: t(type) })}
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
