import { Link, usePage } from "@inertiajs/react";
import { ModalLink } from "@inertiaui/modal-react";
import { useLaravelReactI18n } from "laravel-react-i18n";
import { ArrowDownIcon, PencilIcon } from "lucide-react";

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
import { transferLegs } from "@/lib/transfers";
import { formatAmount } from "@/lib/utils";
import transfers from "@/routes/finance/teams/transfers";
import { Transaction } from "@/types/app/modules/core/finance/models/team/transaction";
import { Transfer } from "@/types/app/modules/core/finance/models/team/transfer";

type PageProps = {
    transfer: Transfer;
};

export default function Show({ transfer }: PageProps) {
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
     * The legs and fees the transfer owns.
     */
    const { destinationFee, destinationLeg, originFee, originLeg } = transferLegs(transfer);

    /**
     * Description, if the recorder wrote one.
     */
    const description = transfer.description?.trim() || null;

    /**
     * Tags attached to the transfer.
     */
    const tags = transfer.tags ?? [];

    /**
     * Who recorded the transfer, if known.
     */
    const recorder = transfer.user?.name?.trim() || null;

    /**
     * When the transfer occurred, in the current locale.
     */
    const occurredAt = new Intl.DateTimeFormat(currentLocale(), {
        dateStyle: "long",
        timeStyle: "short",
    }).format(new Date(transfer.occurred_at));

    return (
        <Modal>
            {({ rootProps }) => (
                <Dialog {...rootProps}>
                    <DialogContent>
                        <DialogHeader>
                            <DialogTitle>{t("Transfer details")}</DialogTitle>
                            <DialogDescription suppressHydrationWarning>{occurredAt}</DialogDescription>
                        </DialogHeader>
                        <div className="flex-1">
                            <FieldGroup>
                                <SideSummary
                                    fee={originFee}
                                    feeLabel={t("Origin fee")}
                                    label={t("Origin")}
                                    leg={originLeg}
                                    locale={currentLocale()}
                                    sign="-"
                                />

                                <div className="text-muted-foreground flex items-center justify-center" aria-hidden>
                                    <ArrowDownIcon className="size-4" />
                                </div>

                                <SideSummary
                                    fee={destinationFee}
                                    feeLabel={t("Destination fee")}
                                    label={t("Destination")}
                                    leg={destinationLeg}
                                    locale={currentLocale()}
                                    sign="+"
                                />

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

                                {recorder ? (
                                    <Field>
                                        <FieldLabel>{t("Recorded by")}</FieldLabel>
                                        <p className="text-sm">{recorder}</p>
                                    </Field>
                                ) : null}
                            </FieldGroup>
                        </div>
                        <DialogFooter className="flex-row justify-between sm:justify-between">
                            <ConfirmAction
                                title={t("Are you sure you want to delete the transfer?")}
                                description={t("This action cannot be undone.")}
                                confirmLabel={t("Delete")}
                                action={
                                    <Link
                                        href={transfers.destroy.url({
                                            team: user?.current_team?.slug ?? "",
                                            transfer: transfer.id,
                                        })}
                                        method="delete"
                                        onSuccess={() => rootProps.onOpenChange?.(false)}
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
                            <Button
                                nativeButton={false}
                                render={
                                    <ModalLink
                                        href={transfers.edit.url({
                                            team: user?.current_team?.slug ?? "",
                                            transfer: transfer.id,
                                        })}
                                    >
                                        <PencilIcon data-icon="inline-start" />
                                        {t("Edit")}
                                    </ModalLink>
                                }
                                variant="outline"
                            />
                        </DialogFooter>
                    </DialogContent>
                </Dialog>
            )}
        </Modal>
    );
}

/**
 * The account, amount, and fee of one side of the transfer.
 */
const SideSummary = ({
    fee,
    feeLabel,
    label,
    leg,
    locale,
    sign,
}: {
    fee: Transaction | null;
    feeLabel: string;
    label: string;
    leg: Transaction | null;
    locale: string;
    sign: string;
}) => {
    /**
     * Mask the amounts while balances are hidden.
     */
    const mask = useBalances().mask;

    if (!leg) {
        return null;
    }

    return (
        <Field>
            <FieldLabel>{label}</FieldLabel>
            <div className="flex items-baseline justify-between gap-3">
                <p className="min-w-0 truncate text-sm">{leg.account?.name}</p>
                <p className="shrink-0 text-lg font-semibold tabular-nums">
                    {mask(`${sign} ${formatAmount(leg.amount, locale, leg.currency)}`)}
                    {leg.currency?.code ? (
                        <span className="text-muted-foreground ml-1.5 text-sm font-medium">{leg.currency.code}</span>
                    ) : null}
                </p>
            </div>
            {fee ? (
                <div className="text-muted-foreground flex items-baseline justify-between gap-3 text-sm">
                    <span className="min-w-0 truncate">{feeLabel}</span>
                    <span className="text-destructive shrink-0 tabular-nums">
                        {mask(`- ${formatAmount(fee.amount, locale, fee.currency)}`)}
                    </span>
                </div>
            ) : null}
        </Field>
    );
};
