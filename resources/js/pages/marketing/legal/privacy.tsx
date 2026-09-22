import type React from "react";

import { MarketingLayout } from "@/layouts/marketing/layout";
import type { Alternates } from "@/types/app/modules/marketing/alternates";
import type { LegalSection } from "@/types/app/modules/marketing/legal";

import { LegalDocument } from "./_components/legal-document";

type PageProps = {
    alternates: Alternates;
};

/**
 * What Contamos holds about a person, why it is allowed to, who else
 * sees it, where it goes, how long it stays and how to get it back or
 * have it removed, in the order someone worried about their data asks
 * those questions.
 */
const SECTIONS: Array<LegalSection> = [
    {
        heading: "Who we are and what this policy covers",
        paragraphs: [
            "Qube SAS operates Contamos and is the controller of the personal data described here: we decide why it is collected and what is done with it. Qube SAS is established in Uruguay.",
            "This policy explains what we hold about you, why we are allowed to hold it, who else can see it, where it goes, how long it stays and what you can ask us to do with it. It covers the Contamos website and application. Sites we link to run under their own policies.",
            "Write to :email with any question about this policy, to exercise any of the rights it describes, or to ask for our postal address.",
        ],
    },
    {
        heading: "The data we collect",
        paragraphs: [
            "Account data. When you sign in, our authentication provider passes us your name, your email address and, where your provider offers one, your profile picture. We also keep the language, the theme and the team you last chose.",
            "Ledger data. The teams, accounts, transactions, transfers, transfer rates, recurring rules, budgets, categories and tags you record, each with the date it was recorded and the member who recorded it.",
            "Activity data. Every team keeps a log of the changes made in it — what changed, when, and by whom — so its members can see how the ledger reached the state it is in.",
            "Billing data. If your team subscribes to Pro we keep the status, plan, price and renewal date of the subscription, and the identifiers our payment provider gives us for it. We never receive or store your card number.",
            "Support data. What you write to us and what we write back.",
            "Technical data. What your browser sends with every request — IP address, user agent, language and the page asked for — recorded in our server logs, together with the cookies described further down.",
            "We do not ask you for special categories of data such as health, biometric or political data, and you should not record any. Contamos does not connect to your bank, so we never receive your banking credentials or your statements.",
        ],
    },
    {
        heading: "Why we use it, and on what legal basis",
        paragraphs: [
            "To provide the service: creating your account, running your teams, storing and showing your ledger, and sending the service messages the product depends on. This is how we perform the contract you enter into by accepting our terms.",
            "To take payment for Pro, which rests on that same contract, and to keep the invoices and accounting records that follow from it, which rests on a legal obligation.",
            "To keep the service secure, available and free of abuse, to answer your support requests, and to understand which features are used so that we can improve them. These rest on our legitimate interest in running a secure service worth paying for, which is why they use the least data that will do the job and never to build a profile of you.",
            "Where the law requires your consent for something, we ask for it before we do it and you can withdraw it at any time, without affecting what was already done under it.",
        ],
    },
    {
        heading: "What we never do with it",
        paragraphs: [
            "We do not sell your personal data, we do not share it with advertisers or data brokers, and we do not use it for advertising or for any purpose unrelated to running Contamos.",
            "We do not profile you, and no decision with a legal or similarly significant effect on you is taken about you by automated means.",
            "We do not use what you record to train machine learning models.",
        ],
    },
    {
        heading: "What the rest of your team sees",
        paragraphs: [
            "Contamos is a shared ledger. Every member of a team sees everything recorded in that team, the name of whoever recorded each entry, and the team's activity log. That is the purpose of the product rather than a side effect of it, so invite only the people you mean to share a ledger with.",
            "Your name and your email address are visible to the members of the teams you belong to and to the people you invite.",
            "A team owner can invite and remove members, change their roles, and delete the team along with everything recorded in it. Removing a member ends their access; it does not erase the entries they made, which the members who stay still need in order to read their own ledger.",
        ],
    },
    {
        heading: "The providers who handle data for us",
        paragraphs: [
            "A small number of providers run parts of Contamos on our behalf. Each of them acts on our instructions under a written data processing agreement, and each receives only the data its part requires.",
            "WorkOS, Inc. runs sign-in and holds the identity behind your account. Paddle.com Market Ltd is the merchant of record for Pro: it collects your payment details directly, decides for itself what it does with them under its own privacy notice, and never passes us your card number. Our hosting provider stores the application's database, files and logs, and our email provider delivers the messages the service sends you.",
            "Beyond them we disclose personal data only where the law obliges us to, where it is needed to establish or defend a legal claim, or to the buyer of the business if Contamos is ever sold, in which case this policy keeps applying until you are told otherwise and given the chance to object.",
        ],
    },
    {
        heading: "Data that leaves Uruguay",
        paragraphs: [
            "Our providers are established outside Uruguay, so running Contamos means transferring your data abroad. We make those transfers only to countries recognised as offering an adequate level of protection, or under the standard contractual clauses approved for the purpose, so that the protection described here travels with the data. Write to :email for a copy of the safeguards that apply.",
        ],
    },
    {
        heading: "How long we keep it",
        paragraphs: [
            "Your account data, your ledger and your teams' activity logs are kept for as long as your account exists.",
            "Server logs are kept for up to ninety days, backups for up to thirty days after the data in them is deleted, and support correspondence for up to two years after the request is closed.",
            "Invoices and the accounting records attached to them are kept for as long as the tax law of Uruguay requires them to be, and are used for nothing else in the meantime.",
        ],
    },
    {
        heading: "How we protect it",
        paragraphs: [
            "Contamos is served only over encrypted connections, and the data behind it sits on managed infrastructure with encryption at rest, restricted administrative access and regular backups. Only the people who need production data in order to operate the service can reach it.",
            "No service can promise perfect security. If a breach happens that is likely to put your rights at risk, we will notify the competent authority and, where the law requires it, you, without undue delay.",
        ],
    },
    {
        heading: "Deleting your account",
        paragraphs: [
            "You can delete your account whenever you like, from the account screen. Doing so removes your profile, ends every session on every device, deletes the identity our authentication provider holds for you, and deletes outright every team of which you were the only member, with everything recorded in it.",
            "Teams you share with other people survive, because they are their ledgers too. Your membership goes, and the entries you made stop being attributed to you, but their amounts, dates and descriptions remain so that the ledger still balances for the members who stay.",
            "Deletion takes effect immediately and cannot be undone. Backups taken before it are overwritten on the cycle described above.",
        ],
    },
    {
        heading: "Your rights",
        paragraphs: [
            "On the conditions set by the data protection law that applies to you, you can ask us for a copy of the personal data we hold about you, ask us to correct it if it is wrong, ask us to delete it, ask us to restrict or stop a particular use of it, object to any use that rests on our legitimate interests, and receive what you gave us in a portable, machine-readable form.",
            "Much of this you can do yourself and at once: your profile is editable from the account screen, your transactions export to CSV from the app at any time with the filters you have on, and your account deletes from that same screen.",
            "For anything else, write to :email. We answer within thirty days and tell you if we need longer. We may ask you to confirm your identity first, so that nobody can exercise your rights in your name. Exercising them costs nothing.",
            "If our answer does not satisfy you, you can complain to the data protection authority of Uruguay or of the country you live in.",
        ],
    },
    {
        heading: "Children",
        paragraphs: [
            "Contamos is for people aged 13 and over, and we do not knowingly collect personal data from anyone younger. Between 13 and the age of majority where you live, a parent or guardian has to agree to our terms with you, and they can exercise the rights described above on your behalf.",
            "If you believe a child under 13 has given us personal data, write to :email and we will delete it.",
        ],
    },
    {
        heading: "Cookies",
        paragraphs: [
            "Contamos sets only the cookies it cannot work without: one that keeps you signed in, one that protects forms against cross-site request forgery, and two that remember how you left the interface, namely the light or dark theme and whether the sidebar is open. All of them are either strictly necessary or set because you asked for them, which is why you are not asked to consent to them.",
            "There are no advertising cookies, no third-party analytics and no cross-site tracking. Contamos does not follow you onto other websites.",
        ],
    },
    {
        heading: "Changes to this policy",
        paragraphs: [
            "We update this policy when the service or the law behind it changes, and the date at the top of the page always says when we last did. If a change materially affects your rights, we will tell you by email or in the app before it takes effect, so that you can object or close your account first.",
        ],
    },
];

const Privacy = ({ alternates }: PageProps) => {
    return (
        <LegalDocument
            alternates={alternates}
            description="What Contamos collects about you, why it is allowed to, who else sees it, how long it stays, and how to get a copy of it or have it removed."
            sections={SECTIONS}
            title="Privacy policy"
            updatedAt="2026-09-22"
        />
    );
};

Privacy.layout = (page: React.ReactNode) => <MarketingLayout>{page}</MarketingLayout>;

export default Privacy;
