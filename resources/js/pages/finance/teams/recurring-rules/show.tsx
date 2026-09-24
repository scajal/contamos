import { Link, router, usePage } from "@inertiajs/react";
import { ModalLink } from "@inertiaui/modal-react";
import { useLaravelReactI18n } from "laravel-react-i18n";
import { PauseIcon, PencilIcon, PlayIcon } from "lucide-react";

import { CategoryIcon } from "@/components/category-icon";
import { ConfirmAction } from "@/components/confirm-action";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from "@/components/ui/dialog";
import { Field, FieldGroup, FieldLabel } from "@/components/ui/field";
import { Modal } from "@/components/ui/modal";
import { useBalances } from "@/hooks/use-balances";
import { scheduleLabel, TRANSACTION_AMOUNT_COLOR, TRANSACTION_AMOUNT_SIGN } from "@/lib/transactions";
import { cn, formatAmount, formatDate } from "@/lib/utils";
import recurringRules from "@/routes/finance/teams/recurring-rules";
import { RecurringRule } from "@/types/app/modules/core/finance/models/team/recurring-rule";

type PageProps = {
    canRecur: boolean;
    rule: RecurringRule;
};

export default function Show({ canRecur, rule }: PageProps) {
    /**
     * Translate the provided string
     * to the current locale.
     */
    const { currentLocale, t } = useLaravelReactI18n();

    /**
     * The page's props.
     */
    const { user } = usePage().props;

    /**
     * The team the rule belongs to.
     */
    const team = user?.current_team?.slug ?? "";

    /**
     * Mask the amounts while balances are hidden.
     */
    const mask = useBalances().mask;

    /**
     * Description, if the creator wrote one.
     */
    const description = rule.description?.trim() || null;

    /**
     * Tags each generated transaction carries.
     */
    const tags = rule.tags ?? [];

    /**
     * Who set the rule up, if known.
     */
    const creator = rule.user?.name?.trim() || null;

    /**
     * Reload the listing behind the modal after the rule changes.
     */
    const refresh = () => router.reload({ only: ["rules"] });

    return (
        <Modal>
            {({ rootProps }) => (
                <Dialog {...rootProps}>
                    <DialogContent>
                        <DialogHeader>
                            <DialogTitle>{t("Rule details")}</DialogTitle>
                            <DialogDescription>{scheduleLabel(rule, currentLocale(), t)}</DialogDescription>
                        </DialogHeader>
                        <div className="flex-1">
                            <FieldGroup>
                                <div className="flex items-baseline justify-between gap-3">
                                    <p
                                        className={cn(
                                            "text-2xl font-semibold tabular-nums",
                                            TRANSACTION_AMOUNT_COLOR[rule.type],
                                        )}
                                    >
                                        {mask(
                                            `${TRANSACTION_AMOUNT_SIGN[rule.type]} ${formatAmount(rule.amount, currentLocale(), rule.currency)}`,
                                        )}
                                        {rule.currency?.code ? (
                                            <span className="text-muted-foreground ml-1.5 text-sm font-medium">
                                                {rule.currency.code}
                                            </span>
                                        ) : null}
                                    </p>
                                    <span className="text-muted-foreground shrink-0 text-sm">
                                        {t(`enums.${rule.type}`)}
                                    </span>
                                </div>

                                <Field>
                                    <FieldLabel>{t("Status")}</FieldLabel>
                                    <div className="flex flex-wrap items-center gap-2">
                                        <Badge
                                            variant={
                                                rule.status === "failed"
                                                    ? "destructive"
                                                    : rule.status === "paused"
                                                      ? "secondary"
                                                      : "outline"
                                            }
                                        >
                                            {rule.status === "failed"
                                                ? t("Failed")
                                                : rule.status === "paused"
                                                  ? t("Paused")
                                                  : t("Active")}
                                        </Badge>
                                        {rule.status === "failed" && rule.failure_reason ? (
                                            <span className="text-muted-foreground text-sm">{rule.failure_reason}</span>
                                        ) : null}
                                    </div>
                                </Field>

                                <div className="grid grid-cols-2 gap-4">
                                    <Field>
                                        <FieldLabel>{t("Next occurrence")}</FieldLabel>
                                        <p className="text-sm">
                                            {rule.next_due_date
                                                ? formatDate(rule.next_due_date, currentLocale())
                                                : t("Ended")}
                                        </p>
                                    </Field>
                                    <Field>
                                        <FieldLabel>{t("Last generated")}</FieldLabel>
                                        <p className="text-sm">
                                            {rule.last_generated_due_date
                                                ? formatDate(rule.last_generated_due_date, currentLocale())
                                                : t("Never")}
                                        </p>
                                    </Field>
                                </div>

                                {rule.ends_on ? (
                                    <Field>
                                        <FieldLabel>{t("Ends on")}</FieldLabel>
                                        <p className="text-sm">{formatDate(rule.ends_on, currentLocale())}</p>
                                    </Field>
                                ) : null}

                                {rule.category ? (
                                    <Field>
                                        <FieldLabel>{t("Category")}</FieldLabel>
                                        <p className="flex items-center gap-1.5 text-sm">
                                            <CategoryIcon
                                                category={rule.category}
                                                className="text-muted-foreground size-4 shrink-0"
                                            />
                                            {t(`enums.${rule.category}`)}
                                        </p>
                                    </Field>
                                ) : null}

                                {rule.account?.name ? (
                                    <Field>
                                        <FieldLabel>{t("Account")}</FieldLabel>
                                        <p className="text-sm">{rule.account.name}</p>
                                    </Field>
                                ) : null}

                                {description ? (
                                    <Field>
                                        <FieldLabel>{t("Description")}</FieldLabel>
                                        <p className="text-sm text-pretty">{description}</p>
                                    </Field>
                                ) : null}

                                {tags.length > 0 ? (
                                    <Field>
                                        <FieldLabel>{t("Tags")}</FieldLabel>
                                        <div className="flex flex-wrap gap-1">
                                            {tags.map((tag) => (
                                                <Badge key={tag.id} variant="outline">
                                                    {tag.name}
                                                </Badge>
                                            ))}
                                        </div>
                                    </Field>
                                ) : null}

                                {creator ? (
                                    <Field>
                                        <FieldLabel>{t("Created by")}</FieldLabel>
                                        <p className="text-sm">{creator}</p>
                                    </Field>
                                ) : null}
                            </FieldGroup>
                        </div>
                        {/** A plan without recurring transactions can only look at the rule. */}
                        {canRecur ? (
                            <DialogFooter className="flex-row justify-between sm:justify-between">
                                <ConfirmAction
                                    title={t("Delete this rule?")}
                                    description={t(
                                        "The transactions it already created stay in your ledger. It will not create any more.",
                                    )}
                                    confirmLabel={t("Delete")}
                                    action={
                                        <Link
                                            href={recurringRules.destroy.url({ recurringRule: rule.id, team })}
                                            method="delete"
                                            onSuccess={() => {
                                                rootProps.onOpenChange?.(false);
                                                refresh();
                                            }}
                                        />
                                    }
                                >
                                    <Button
                                        className="text-destructive hover:bg-destructive/10 hover:text-destructive"
                                        variant="ghost"
                                    >
                                        {t("Delete")}
                                    </Button>
                                </ConfirmAction>
                                <div className="flex gap-2">
                                    <Button
                                        nativeButton={false}
                                        render={
                                            <Link
                                                as="button"
                                                href={
                                                    rule.is_active
                                                        ? recurringRules.pause.url({ recurringRule: rule.id, team })
                                                        : recurringRules.resume.url({ recurringRule: rule.id, team })
                                                }
                                                method="patch"
                                                onSuccess={() => {
                                                    rootProps.onOpenChange?.(false);
                                                    refresh();
                                                }}
                                            />
                                        }
                                        variant="outline"
                                    >
                                        {rule.is_active ? (
                                            <PauseIcon data-icon="inline-start" />
                                        ) : (
                                            <PlayIcon data-icon="inline-start" />
                                        )}
                                        {rule.is_active ? t("Pause") : t("Resume")}
                                    </Button>
                                    <Button
                                        nativeButton={false}
                                        render={
                                            <ModalLink href={recurringRules.edit.url({ recurringRule: rule.id, team })}>
                                                <PencilIcon data-icon="inline-start" />
                                                {t("Edit")}
                                            </ModalLink>
                                        }
                                        variant="outline"
                                    />
                                </div>
                            </DialogFooter>
                        ) : null}
                    </DialogContent>
                </Dialog>
            )}
        </Modal>
    );
}
