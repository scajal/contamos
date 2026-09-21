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
import transactions from "@/routes/finance/teams/transactions";
import { Account } from "@/types/app/modules/core/finance/models/team/account";
import { Tag } from "@/types/app/modules/core/finance/models/team/tag";
import { Transaction } from "@/types/app/modules/core/finance/models/team/transaction";

import { Fields } from "./_components/fields";

type PageProps = {
    accounts: Array<Account>;
    tags: Array<Tag>;
    transaction: Transaction;
};

export default function Edit({ accounts, tags, transaction }: PageProps) {
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
    const [type, setType] = useState(transaction.type);

    return (
        <Modal>
            {({ rootProps }) => (
                <Dialog {...rootProps}>
                    <DialogContent>
                        <Form
                            {...transactions.update.form({
                                team: user?.current_team?.slug ?? "",
                                transaction: transaction.id,
                            })}
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
                                        <DialogTitle>{t("Edit :name", { name: t("transaction") })}</DialogTitle>
                                        <DialogDescription>{t("Edit the transaction.")}</DialogDescription>
                                    </DialogHeader>
                                    <div className="flex-1">
                                        <Fields
                                            accounts={accounts}
                                            defaultValues={{
                                                account_id: transaction.account_id,
                                                amount: transaction.amount,
                                                currency_id: transaction.currency_id,
                                                description: transaction.description ?? undefined,
                                                occurred_at: transaction.occurred_at,
                                                quote: transaction.quote,
                                                tag_ids: (transaction.tags ?? []).map((tag) => tag.id),
                                            }}
                                            onTypeChange={setType}
                                            tags={tags}
                                            trackTypeChanges
                                            type={type}
                                            {...formProps}
                                        />
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
