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
 * What Contamos holds about a person, why, who else sees it and how
 * to get it back or have it removed, in the order someone worried
 * about their data asks those questions.
 */
const SECTIONS: Array<LegalSection> = [
    {
        heading: "Who we are",
        paragraphs: [
            ":entity runs Contamos and decides what happens to the personal data described on this page. Write to :email about anything here.",
        ],
    },
    {
        heading: "What we collect",
        paragraphs: [
            "Your account: the name, email address and profile picture your sign-in provider gives us when you log in.",
            "Your ledger: the teams, accounts, transactions, transfers, tags and exchange rates you record, each with the name of the member who recorded it.",
            "Your use of the service: the technical data a browser sends, such as your IP address, your language and your device, kept in our logs so we can keep the service running and secure.",
        ],
    },
    {
        heading: "Why we use it",
        paragraphs: [
            "To run your ledger and show it to the people you share a team with, to charge you if your team is on Pro, to answer you when you write to support, and to keep the service secure and working.",
        ],
    },
    {
        heading: "Who else sees it",
        paragraphs: [
            "Everyone in a team sees everything recorded in that team, including who recorded it. That is the point of a shared ledger, so invite only people you mean to share it with.",
            "Outside your teams we share data only with the providers that make Contamos work: our sign-in provider, our payment provider and our hosting provider. We do not sell your data and we do not use it for advertising.",
        ],
    },
    {
        heading: "Payments",
        paragraphs: [
            "Paddle.com Market Ltd is the merchant of record for the Pro plan. Paddle takes your payment details directly and we never see your card number. Paddle's own privacy notice covers what it does with them.",
        ],
    },
    {
        heading: "How long we keep it",
        paragraphs: [
            "Your ledger stays for as long as your account does. Delete your account from the account screen and we remove your personal data along with the teams nobody else owns.",
            "Records we are required to keep for tax or accounting reasons are kept for as long as the law of :jurisdiction requires, and for nothing else.",
        ],
    },
    {
        heading: "Your rights",
        paragraphs: [
            "You can ask for a copy of your data, ask us to correct it, ask us to delete it, or object to how we use it. Your transactions can also be exported to CSV from the app at any time, with the filters you have on.",
            "Write to :email and we will answer. If you are not satisfied you can complain to the data protection authority of :jurisdiction.",
        ],
    },
    {
        heading: "Cookies",
        paragraphs: [
            "Contamos sets only the cookies it needs: one to keep you signed in and one to remember whether you asked for the light or the dark theme. There is no advertising and no third-party tracking.",
        ],
    },
    {
        heading: "Changes and contact",
        paragraphs: [
            "If this policy changes, the date at the top of this page changes with it. Write to :email with any question about it.",
        ],
    },
];

const Privacy = ({ alternates, legal }: PageProps) => {
    /**
     * The address a user writes to, shared by the server.
     */
    const { support } = usePage().props;

    return (
        <LegalDocument
            alternates={alternates}
            description="What Contamos collects about you, why, who else sees it, and how to get a copy of it or have it removed."
            legal={legal}
            sections={SECTIONS}
            support={support.email}
            title="Privacy policy"
        />
    );
};

Privacy.layout = (page: React.ReactNode) => <MarketingLayout>{page}</MarketingLayout>;

export default Privacy;
