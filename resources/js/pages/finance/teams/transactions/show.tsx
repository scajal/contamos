import { Link } from "@inertiajs/react";
import { format } from "date-fns";
import { useLaravelReactI18n } from "laravel-react-i18n";

import { ConfirmAction } from "@/components/confirm-action";
import { Button } from "@/components/ui/button";
import { Field, FieldDescription, FieldLabel } from "@/components/ui/field";
import { Item, ItemHeader, ItemTitle } from "@/components/ui/item";
import { Modal } from "@/components/ui/modal";
import { Sheet, SheetContent, SheetDescription, SheetFooter, SheetHeader, SheetTitle } from "@/components/ui/sheet";
import { cn, formatAmount } from "@/lib/utils";
import transactions from "@/routes/finance/teams/transactions";
import { Transaction } from "@/types/app/modules/core/finance/models/team/transaction";

type PageProps = {
    transaction: Transaction;
};

export default function Show({ transaction }: PageProps) {
    /**
     * Translate the provided string
     * to the current locale.
     */
    const { currentLocale, t } = useLaravelReactI18n();

    return (
        <Modal>
            {({ rootProps }) => (
                <Sheet {...rootProps}>
                    <SheetContent>
                        <SheetHeader>
                            <SheetTitle>{t("Transaction details")}</SheetTitle>
                            <SheetDescription>
                                {format(new Date(transaction.occurred_at), "MMMM d, yyyy HH:mm")}
                            </SheetDescription>
                        </SheetHeader>
                        <div className="min-h-0 flex-1 space-y-6 overflow-y-auto px-4">
                            {/** Amount */}
                            <Item className="min-h-24" variant="muted">
                                <ItemHeader className="justify-center">
                                    <ItemTitle
                                        className={cn(
                                            transaction.type === "income" ? "text-green-500" : "text-red-500",
                                            "text-4xl",
                                        )}
                                    >
                                        {`${transaction.type === "income" ? "+" : "-"} ${formatAmount(transaction.amount, currentLocale(), transaction.currency)}`}
                                    </ItemTitle>
                                </ItemHeader>
                            </Item>
                            {/** Description */}
                            <Field>
                                <FieldLabel>{t("Description")}</FieldLabel>
                                <FieldDescription>{transaction.description ?? "-"}</FieldDescription>
                            </Field>
                            {/** Account */}
                            <Field>
                                <FieldLabel>{t("Account")}</FieldLabel>
                                <FieldDescription>{transaction.account?.name ?? "-"}</FieldDescription>
                            </Field>
                            {/** ID */}
                            <Field>
                                <FieldLabel>{t("Transaction ID")}</FieldLabel>
                                <FieldDescription>{transaction.id}</FieldDescription>
                            </Field>
                        </div>
                        <SheetFooter>
                            <ConfirmAction
                                title={t("Are you sure you want to delete the transaction?")}
                                description={t("This action cannot be undone.")}
                                confirmLabel={t("Delete")}
                                action={
                                    <Link
                                        href={transactions.destroy.url({
                                            team: transaction.team_id,
                                            transaction: transaction.id,
                                        })}
                                        method="delete"
                                        onSuccess={() => rootProps.onOpenChange?.(false)}
                                    />
                                }
                            >
                                <Button variant="destructive">{t("Delete")}</Button>
                            </ConfirmAction>
                        </SheetFooter>
                    </SheetContent>
                </Sheet>
            )}
        </Modal>
    );
}
