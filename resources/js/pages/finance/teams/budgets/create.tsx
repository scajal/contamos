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
import { type CategoryOption } from "@/lib/categories";
import budgets from "@/routes/finance/teams/budgets";
import { Currency } from "@/types/app/modules/core/catalog/models/currency";
import { BudgetParentOption } from "@/types/app/modules/core/finance/models/team/budget-period";
import { Tag } from "@/types/app/modules/core/finance/models/team/tag";

import { Fields } from "./_components/fields";

type PageProps = {
    categories: Array<CategoryOption>;
    currencies: Array<Currency>;
    month: string;
    parents: Array<BudgetParentOption>;
    tags: Array<Tag>;
};

export default function Create({ categories, currencies, month, parents, tags }: PageProps) {
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
                            {...budgets.store.form({ team: user?.current_team?.slug ?? "" })}
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
                                        <DialogTitle>{t("Create :name", { name: t("budget") })}</DialogTitle>
                                        <DialogDescription>
                                            {t("Set how much may be spent on it each month.")}
                                        </DialogDescription>
                                    </DialogHeader>
                                    <div className="flex-1">
                                        <Fields
                                            categories={categories ?? []}
                                            currencies={currencies ?? []}
                                            defaultValues={{ from_month: month, target_kind: "category" }}
                                            parents={parents ?? []}
                                            tags={tags ?? []}
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
