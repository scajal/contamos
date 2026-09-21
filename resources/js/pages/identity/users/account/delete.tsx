import { Form } from "@inertiajs/react";
import { useLaravelReactI18n } from "laravel-react-i18n";
import { AlertTriangleIcon, TrashIcon, UsersIcon } from "lucide-react";
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
import { Field, FieldError, FieldGroup, FieldLabel } from "@/components/ui/field";
import { Input } from "@/components/ui/input";
import { Item, ItemContent, ItemGroup, ItemMedia, ItemTitle } from "@/components/ui/item";
import { Modal } from "@/components/ui/modal";
import { Spinner } from "@/components/ui/spinner";
import account from "@/routes/identity/users/account";

/**
 * A team the user is the only member of, which the deletion
 * takes with the account.
 */
type SoloTeam = {
    name: string;
    slug: string;
    subscribed: boolean;
};

type PageProps = {
    email: string;
    teams: Array<SoloTeam>;
};

export default function Delete({ email, teams }: PageProps) {
    /**
     * Translate the provided string
     * to the current locale.
     */
    const t = useLaravelReactI18n().t;

    /**
     * What the user typed so far. The account can only be
     * deleted once it matches the email address.
     */
    const [typed, setTyped] = useState("");

    /**
     * Whether any of the teams going away is on Pro, which
     * means a subscription is cancelled with it.
     */
    const subscribed = teams.some((team) => team.subscribed);

    /**
     * The confirmation prompt around the email, so the address
     * itself can stand out from the sentence.
     */
    const [before, after] = t("Type :email to confirm.", { email }).split(email);

    return (
        <Modal>
            {({ rootProps }) => (
                <Dialog {...rootProps}>
                    <DialogContent>
                        {/**
                         * The account is gone after deleting, so the server logs
                         * the user out and redirects to the home page. The full
                         * visit replaces the base page and closes the modal.
                         */}
                        <Form {...account.destroy.form()} className="contents" disableWhileProcessing>
                            {({ errors, invalid, processing }) => (
                                <Fragment>
                                    <DialogHeader>
                                        <DialogTitle>{t("Delete account")}</DialogTitle>
                                        <DialogDescription>
                                            {t(
                                                "This cannot be undone. There is no recovery period and no way to get the account back.",
                                            )}
                                        </DialogDescription>
                                    </DialogHeader>
                                    <div className="flex flex-1 flex-col gap-6">
                                        {teams.length > 0 && (
                                            <div className="flex flex-col gap-2">
                                                <p className="text-sm">
                                                    {t(
                                                        "These teams are deleted with your account, because you are their only member:",
                                                    )}
                                                </p>
                                                <div className="overflow-hidden rounded-xl border">
                                                    <ItemGroup className="gap-0 has-data-[size=sm]:gap-0">
                                                        {teams.map((team) => (
                                                            <Item
                                                                className="rounded-none border-0 border-b last:border-b-0"
                                                                key={team.slug}
                                                                size="sm"
                                                                variant="outline"
                                                            >
                                                                <ItemMedia variant="icon">
                                                                    <UsersIcon />
                                                                </ItemMedia>
                                                                <ItemContent>
                                                                    <ItemTitle>{team.name}</ItemTitle>
                                                                </ItemContent>
                                                            </Item>
                                                        ))}
                                                    </ItemGroup>
                                                </div>
                                            </div>
                                        )}
                                        {subscribed && (
                                            <div className="border-destructive/40 bg-destructive/5 flex gap-3 rounded-xl border p-3 text-sm">
                                                <AlertTriangleIcon className="text-destructive mt-0.5 size-4 shrink-0" />
                                                <p>
                                                    {t(
                                                        "One of those teams is on Pro. Its subscription is cancelled immediately on Paddle, and the remaining period is not refunded.",
                                                    )}
                                                </p>
                                            </div>
                                        )}
                                        <p className="text-muted-foreground text-sm">
                                            {t(
                                                "Teams you share with other people are kept, with their accounts and transactions. Only your membership is removed.",
                                            )}
                                        </p>
                                        <FieldGroup>
                                            <Field data-invalid={invalid("email") || undefined}>
                                                <FieldLabel htmlFor="account-email">
                                                    {before}
                                                    <span className="text-destructive">{email}</span>
                                                    {after}
                                                </FieldLabel>
                                                <Input
                                                    autoComplete="off"
                                                    autoFocus
                                                    aria-invalid={invalid("email")}
                                                    id="account-email"
                                                    name="email"
                                                    onChange={(event) => setTyped(event.target.value)}
                                                    placeholder={email}
                                                    value={typed}
                                                />
                                                {invalid("email") && <FieldError>{errors.email}</FieldError>}
                                            </Field>
                                        </FieldGroup>
                                    </div>
                                    <DialogFooter>
                                        <Button
                                            disabled={processing || typed !== email}
                                            type="submit"
                                            variant="destructive"
                                        >
                                            {processing ? (
                                                <Spinner data-icon="inline-start" />
                                            ) : (
                                                <TrashIcon data-icon="inline-start" />
                                            )}
                                            {t("Delete account")}
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
