import { Form, Link, usePage } from "@inertiajs/react";
import { format } from "date-fns";
import { useLaravelReactI18n } from "laravel-react-i18n";
import { ArrowRightIcon, CheckCircle2Icon, ClockIcon, MailOpenIcon, UserRoundXIcon } from "lucide-react";
import { Fragment } from "react/jsx-runtime";

import { Button } from "@/components/ui/button";
import { Spinner } from "@/components/ui/spinner";
import { WizardLayout } from "@/layouts/wizard/layout";
import { invitationStatus } from "@/lib/utils";
import { logout } from "@/routes";
import invitations from "@/routes/identity/invitations";
import { TeamInvitation } from "@/types/app/modules/core/identity/models/team/team-invitation";

type PageProps = {
    invitation: TeamInvitation;
    isForCurrentUser: boolean;
};

const Show = () => {
    /**
     * Translate the provided string
     * to the current locale.
     */
    const t = useLaravelReactI18n().t;

    /**
     * The page's props.
     */
    const { invitation, isForCurrentUser, user } = usePage<PageProps>().props;

    /**
     * The status of the invitation, derived from
     * its accepted and expiration dates.
     */
    const status = invitationStatus(invitation);

    /**
     * The name of the team and the inviter.
     */
    const team = invitation.team?.name ?? "";
    const inviter = invitation.inviter?.name ?? "";

    return (
        <div className="flex flex-col gap-8">
            {/** Header */}
            <header className="flex flex-col items-center gap-2 text-center">
                <MailOpenIcon />
                <h1 className="mt-4 text-2xl font-medium">{t("You have been invited to join :team", { team })}</h1>
                <p className="text-muted-foreground max-w-sm text-sm">
                    {t(":inviter has invited you to join the team :team.", { inviter, team })}
                </p>
            </header>

            {/** Content */}
            <div className="mx-auto flex w-full max-w-xl flex-col items-center gap-4 text-center">
                {status === "accepted" ? (
                    <Notice icon={<CheckCircle2Icon />} title={t("This invitation has already been accepted.")}>
                        <Button nativeButton={false} render={<Link href="/" />}>
                            {t("Go to the dashboard")}
                            <ArrowRightIcon />
                        </Button>
                    </Notice>
                ) : status === "expired" ? (
                    <Notice
                        icon={<ClockIcon />}
                        title={t("This invitation has expired.")}
                        description={t("Ask :inviter to send you a new one.", { inviter })}
                    />
                ) : !isForCurrentUser ? (
                    <Notice
                        icon={<UserRoundXIcon />}
                        title={t("This invitation was sent to :email.", { email: invitation.email })}
                        description={t("You are signed in as :current. Sign out and sign in with the invited email.", {
                            current: user?.email ?? "",
                        })}
                    >
                        <Button nativeButton={false} render={<a href={logout.url()} />} variant="outline">
                            {t("Logout")}
                        </Button>
                    </Notice>
                ) : (
                    <Fragment>
                        {invitation.expires_at ? (
                            <p className="text-muted-foreground text-sm">
                                {t("This invitation expires on :date.", {
                                    date: format(new Date(invitation.expires_at), "MMMM d, yyyy"),
                                })}
                            </p>
                        ) : null}
                        <Form {...invitations.accept.form({ invitation: invitation.code })} disableWhileProcessing>
                            {({ processing }) => (
                                <Button disabled={processing} type="submit">
                                    {processing && <Spinner />}
                                    {t("Accept invitation")}
                                    <ArrowRightIcon />
                                </Button>
                            )}
                        </Form>
                    </Fragment>
                )}
            </div>
        </div>
    );
};

const Notice = ({
    children,
    description,
    icon,
    title,
}: {
    children?: React.ReactNode;
    description?: string;
    icon: React.ReactNode;
    title: string;
}) => {
    return (
        <div className="flex w-full flex-col items-center gap-3 rounded-sm border p-6">
            <div className="text-muted-foreground [&_svg]:size-6">{icon}</div>
            <p className="font-medium">{title}</p>
            {description ? <p className="text-muted-foreground text-sm">{description}</p> : null}
            {children ? <div className="mt-2">{children}</div> : null}
        </div>
    );
};

Show.layout = (page: React.ReactNode) => <WizardLayout>{page}</WizardLayout>;

export default Show;
