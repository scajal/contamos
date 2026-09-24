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
import { trimTrailingZeros } from "@/lib/transactions";
import budgets from "@/routes/finance/teams/budgets";
import { Currency } from "@/types/app/modules/core/catalog/models/currency";
import { BudgetRow } from "@/types/app/modules/core/finance/models/team/budget-period";

import { Fields } from "./_components/fields";

type PageProps = {
    budget: BudgetRow;
    currencies: Array<Currency>;
};

export default function Edit({ budget, currencies }: PageProps) {
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
                            {...budgets.update.form({ team: user?.current_team?.slug ?? "", budget: budget.id })}
                            className="contents"
                            disableWhileProcessing
                            onSuccess={() => {
                                rootProps.onOpenChange(false);
                                router.reload({ only: ["budgets"] });
                            }}
                            options={{ only: ["errors"] }}
                        >
                            {(formProps) => (
                                <Fragment>
                                    <DialogHeader>
                                        <DialogTitle>{t("Edit :name", { name: t("budget") })}</DialogTitle>
                                        <DialogDescription>
                                            {t("Change the amount or the months this budget runs for.")}
                                        </DialogDescription>
                                    </DialogHeader>
                                    <div className="flex-1">
                                        <Fields
                                            currencies={currencies ?? []}
                                            defaultValues={{
                                                amount: trimTrailingZeros(budget.amount),
                                                currency_id: budget.currency.id,
                                                from_month: budget.from_month,
                                                until_month: budget.until_month,
                                                warning_threshold: budget.warning_threshold?.toString() ?? "",
                                            }}
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
