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
import accounts from "@/routes/finance/teams/accounts";
import { Currency } from "@/types/app/modules/core/catalog/models/currency";
import { AccountType } from "@/types/app/modules/core/finance/enums/account/account-type";
import { Account } from "@/types/app/modules/core/finance/models/team/account";

import { Fields } from "./_components/fields";

type PageProps = {
    account: Partial<Omit<Account, "team"> & { currency_ids: Array<string> }>;
    currencies: Array<Currency>;
    types: Array<AccountType>;
};

export default function Edit({ account, currencies, types }: PageProps) {
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
                            {...accounts.update.form({
                                team: user?.current_team?.slug ?? "",
                                account: account.id ?? "",
                            })}
                            className="contents"
                            disableWhileProcessing
                            onSuccess={() => {
                                rootProps.onOpenChange(false);
                                router.reload({ only: ["accounts"] });
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
                                        <DialogTitle>{t("Edit :name", { name: t("account") })}</DialogTitle>
                                        <DialogDescription>{t("Edit the account.")}</DialogDescription>
                                    </DialogHeader>
                                    <div className="flex-1">
                                        <Fields
                                            attachedCurrencyIds={
                                                account.currencies?.map((currency) => currency.id) ?? []
                                            }
                                            currencies={currencies}
                                            defaultValues={{
                                                ...account,
                                                currency_ids: account.currencies?.map((currency) => currency.id) ?? [],
                                            }}
                                            locked={["type"]}
                                            types={types}
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
