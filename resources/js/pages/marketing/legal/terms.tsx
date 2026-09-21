import { usePage } from "@inertiajs/react";
import type React from "react";

import { MarketingLayout } from "@/layouts/marketing/layout";
import type { Alternates } from "@/types/app/modules/marketing/alternates";
import type { Legal, LegalSection } from "@/types/app/modules/marketing/legal";

import { LegalDocument } from "./_components/legal-document";

type PageProps = {
    alternates: Alternates;
    legal: Legal;
};

/**
 * What a member agrees to by using Contamos: what the service is and
 * is not, what a shared team means for what others can see, how the
 * plans are paid for, and how either side ends the agreement.
 */
const SECTIONS: Array<LegalSection> = [
    {
        heading: "The agreement",
        paragraphs: [
            "These terms govern your use of Contamos, which is operated by :entity. Creating an account means you accept them. If you do not accept them, do not use the service.",
        ],
    },
    {
        heading: "What Contamos is",
        paragraphs: [
            "Contamos is a shared multi-currency ledger. It records what its members enter and nothing else: it is not accounting software, it is not financial, legal or tax advice, and it does not connect to your bank.",
            "What the ledger shows is what the people in your team typed into it. Check it before you rely on it.",
        ],
    },
    {
        heading: "Your account",
        paragraphs: [
            "You need an account to use Contamos and you are responsible for what is done with it. You must be old enough to enter into a contract where you live.",
            "Keep your sign-in secure, and write to :email if you think somebody else is using your account.",
        ],
    },
    {
        heading: "Teams and shared data",
        paragraphs: [
            "Everything recorded in a team is visible to every member of that team, carrying the name of whoever recorded it. Invite only people you mean to share your ledger with.",
            "A team owner can change roles, remove members, and delete the team together with everything recorded in it. That deletion cannot be undone.",
        ],
    },
    {
        heading: "Acceptable use",
        paragraphs: [
            "Do not use Contamos to break the law, to reach data that is not yours, to disrupt or overload the service, or to resell it as your own. We may suspend or close an account that does.",
        ],
    },
    {
        heading: "Plans and payment",
        paragraphs: [
            "The Free plan costs nothing and limits how many accounts, members and teams you can have and how much history is shown. Nothing is ever deleted because of those limits.",
            "Pro removes the limits and is billed monthly or yearly through Paddle.com Market Ltd, our merchant of record, until you cancel it. The price is shown before you pay. Refunds are covered by our refund policy.",
        ],
    },
    {
        heading: "Availability and changes",
        paragraphs: [
            "Contamos is in active development. Features are added, changed and sometimes removed, and what a plan includes is what the app says it includes today.",
            "We aim to keep the service available but we do not promise that it never goes down. We may change these terms, and the date at the top of this page says when we last did.",
        ],
    },
    {
        heading: "What you record stays yours",
        paragraphs: [
            "You keep every right to what you record. You give us only the permission we need to store it, show it to the members of your teams and back it up so the service works.",
        ],
    },
    {
        heading: "Liability",
        paragraphs: [
            "Contamos is provided as it is. So far as the law allows, :entity is not liable for indirect or consequential loss, for lost profit, or for decisions taken on the basis of what the ledger shows.",
            "Nothing in these terms limits liability that cannot lawfully be limited.",
        ],
    },
    {
        heading: "Ending the agreement",
        paragraphs: [
            "You can stop whenever you like: cancel Pro from the billing portal, or delete your account from the account screen. We may end the agreement if these terms are broken.",
        ],
    },
    {
        heading: "Governing law and contact",
        paragraphs: [
            "These terms are governed by the law of :jurisdiction, whose courts settle any dispute about them. Write to :email with any question about this page.",
        ],
    },
];

const Terms = ({ alternates, legal }: PageProps) => {
    /**
     * The address a user writes to, shared by the server.
     */
    const { support } = usePage().props;

    return (
        <LegalDocument
            alternates={alternates}
            description="The terms you agree to when you use Contamos: what the service is, what sharing a team means, how the plans are paid for, and how to stop."
            legal={legal}
            sections={SECTIONS}
            support={support.email}
            title="Terms of service"
        />
    );
};

Terms.layout = (page: React.ReactNode) => <MarketingLayout>{page}</MarketingLayout>;

export default Terms;
