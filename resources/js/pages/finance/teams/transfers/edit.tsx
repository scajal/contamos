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
import { transferLegs } from "@/lib/transfers";
import transfers from "@/routes/finance/teams/transfers";
import { Account } from "@/types/app/modules/core/finance/models/team/account";
import { Tag } from "@/types/app/modules/core/finance/models/team/tag";
import { Transfer } from "@/types/app/modules/core/finance/models/team/transfer";

import { Fields } from "./_components/fields";

type PageProps = {
    accounts: Array<Account>;
    tags: Array<Tag>;
    transfer: Transfer;
};

export default function Edit({ accounts, tags, transfer }: PageProps) {
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
     * The legs and fees the transfer owns.
     */
    const { destinationFee, destinationLeg, originFee, originLeg } = transferLegs(transfer);

    return (
        <Modal>
            {({ rootProps }) => (
                <Dialog {...rootProps}>
                    <DialogContent>
                        <Form
                            {...transfers.update.form({
                                team: user?.current_team?.slug ?? "",
                                transfer: transfer.id,
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
                                        <DialogTitle>{t("Edit :name", { name: t("transfer") })}</DialogTitle>
                                        <DialogDescription>{t("Edit the transfer.")}</DialogDescription>
                                    </DialogHeader>
                                    <div className="flex-1">
                                        <Fields
                                            accounts={accounts}
                                            defaultValues={{
                                                amount_received: destinationLeg?.amount,
                                                amount_sent: originLeg?.amount,
                                                description: transfer.description ?? undefined,
                                                destination_account_id: destinationLeg?.account_id,
                                                destination_currency_id: destinationLeg?.currency_id,
                                                destination_fee: destinationFee?.amount,
                                                destination_quote: destinationLeg?.quote,
                                                occurred_at: transfer.occurred_at,
                                                origin_account_id: originLeg?.account_id,
                                                origin_currency_id: originLeg?.currency_id,
                                                origin_fee: originFee?.amount,
                                                origin_quote: originLeg?.quote,
                                                tag_ids: (transfer.tags ?? []).map((tag) => tag.id),
                                            }}
                                            tags={tags}
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
