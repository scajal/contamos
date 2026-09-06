import { Link } from "@inertiajs/react";
import { format } from "date-fns";
import { useLaravelReactI18n } from "laravel-react-i18n";

import { Badge } from "@/components/ui/badge";
import { ConfirmAction } from "@/components/confirm-action";
import { Button } from "@/components/ui/button";
import { Field, FieldGroup, FieldLabel } from "@/components/ui/field";
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

    /**
     * Whether this transaction is an expense.
     */
    const isExpense = transaction.type === "expense";

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
                        <div className="min-h-0 flex-1 overflow-y-auto px-4">
                            <FieldGroup>
                                {/** Amount */}
                                <div className="flex flex-col gap-1">
                                    <span className="text-muted-foreground text-sm">
                                        {t(`enums.${transaction.type}`)}
                                    </span>
                                    <span
                                        className={cn(
                                            "text-2xl font-semibold tabular-nums",
                                            isExpense ? "text-destructive" : "text-success",
                                        )}
                                    >
                                        {`${isExpense ? "-" : "+"} ${formatAmount(transaction.amount, currentLocale(), transaction.currency)}`}
                                    </span>
                                </div>

                                {/** Description */}
                                <Field>
                                    <FieldLabel>{t("Description")}</FieldLabel>
                                    <p className="text-sm">{transaction.description ?? "-"}</p>
                                </Field>

                                {/** Account */}
                                <Field>
                                    <FieldLabel>{t("Account")}</FieldLabel>
                                    <p className="text-sm">{transaction.account?.name ?? "-"}</p>
                                </Field>

                                {/** Tags */}
                                <Field>
                                    <FieldLabel>{t("Tags")}</FieldLabel>
                                    {(transaction.tags ?? []).length > 0 ? (
                                        <div className="flex flex-wrap gap-1">
                                            {(transaction.tags ?? []).map((tag) => (
                                                <Badge key={tag.id} variant="outline">
                                                    {tag.name}
                                                </Badge>
                                            ))}
                                        </div>
                                    ) : (
                                        <p className="text-sm">-</p>
                                    )}
                                </Field>

                                {/** ID */}
                                <Field>
                                    <FieldLabel>{t("Transaction ID")}</FieldLabel>
                                    <p className="text-sm">{transaction.id}</p>
                                </Field>

                                {/** Exchange rate */}
                                <Field>
                                    <FieldLabel>{t("Quote")}</FieldLabel>
                                    <p className="text-sm tabular-nums">
                                        {formatAmount(transaction.quote, currentLocale(), transaction.currency)}
                                    </p>
                                </Field>
                            </FieldGroup>
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
