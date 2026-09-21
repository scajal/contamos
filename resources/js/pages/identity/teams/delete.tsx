import { Form } from "@inertiajs/react";
import { useLaravelReactI18n } from "laravel-react-i18n";
import { AlertTriangleIcon, TrashIcon } from "lucide-react";
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
import { Modal } from "@/components/ui/modal";
import { Spinner } from "@/components/ui/spinner";
import settings from "@/routes/identity/teams/settings";
import { Team } from "@/types/app/modules/core/identity/models/team";

type PageProps = {
    subscribed: boolean;
    team: Team;
};

export default function Delete({ subscribed, team }: PageProps) {
    /**
     * Translate the provided string
     * to the current locale.
     */
    const t = useLaravelReactI18n().t;

    /**
     * What the user typed so far. The team can only be deleted
     * once it matches the handle.
     */
    const [typed, setTyped] = useState("");

    /**
     * The confirmation prompt around the handle, so the handle
     * itself can stand out from the sentence.
     */
    const [before, after] = t("Type :handle to confirm.", { handle: team.slug }).split(team.slug);

    return (
        <Modal>
            {({ rootProps }) => (
                <Dialog {...rootProps}>
                    <DialogContent>
                        {/**
                         * The team is gone after deleting, so the server redirects
                         * to another team or to the team creation page. The full
                         * visit replaces the base page and closes the modal.
                         */}
                        <Form
                            {...settings.destroy.form({ team: team.slug })}
                            className="contents"
                            disableWhileProcessing
                        >
                            {({ errors, invalid, processing }) => (
                                <Fragment>
                                    <DialogHeader>
                                        <DialogTitle>{t("Delete team")}</DialogTitle>
                                        <DialogDescription>
                                            {t(
                                                "This cannot be undone. Every member loses access to the team's accounts and transactions.",
                                            )}
                                        </DialogDescription>
                                    </DialogHeader>
                                    <div className="flex flex-1 flex-col gap-6">
                                        {subscribed && (
                                            <div className="border-destructive/40 bg-destructive/5 flex gap-3 rounded-xl border p-3 text-sm">
                                                <AlertTriangleIcon className="text-destructive mt-0.5 size-4 shrink-0" />
                                                <p>
                                                    {t(
                                                        "This team is on Pro. Its subscription is cancelled immediately on Paddle, and the remaining period is not refunded.",
                                                    )}
                                                </p>
                                            </div>
                                        )}
                                        <FieldGroup>
                                            <Field data-invalid={invalid("slug") || undefined}>
                                                <FieldLabel htmlFor="team-slug">
                                                    {before}
                                                    <span className="text-destructive">{team.slug}</span>
                                                    {after}
                                                </FieldLabel>
                                                <Input
                                                    autoComplete="off"
                                                    autoFocus
                                                    aria-invalid={invalid("slug")}
                                                    id="team-slug"
                                                    name="slug"
                                                    onChange={(event) => setTyped(event.target.value)}
                                                    placeholder={team.slug}
                                                    value={typed}
                                                />
                                                {invalid("slug") && <FieldError>{errors.slug}</FieldError>}
                                            </Field>
                                        </FieldGroup>
                                    </div>
                                    <DialogFooter>
                                        <Button
                                            disabled={processing || typed !== team.slug}
                                            type="submit"
                                            variant="destructive"
                                        >
                                            {processing ? (
                                                <Spinner data-icon="inline-start" />
                                            ) : (
                                                <TrashIcon data-icon="inline-start" />
                                            )}
                                            {t("Delete team")}
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
