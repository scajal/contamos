import type React from "react";

import { MarketingLayout } from "@/layouts/marketing/layout";
import type { Alternates } from "@/types/app/modules/marketing/alternates";
import type { LegalSection } from "@/types/app/modules/marketing/legal";

import { LegalDocument } from "./_components/legal-document";

type PageProps = {
    alternates: Alternates;
};

/**
 * The contract a member enters into by using Contamos: who it binds,
 * what the service promises and refuses to promise, what sharing a
 * team commits you to, how the plans are paid for, who answers for
 * what when something goes wrong, and how either side walks away.
 */
const SECTIONS: Array<LegalSection> = [
    {
        heading: "The agreement between us",
        paragraphs: [
            "These terms are a contract between you and Qube SAS, the company that operates Contamos. You enter into it by creating an account or by using the service, and if you do not accept it you must not use Contamos.",
            "Our privacy policy and our refund policy form part of these terms. Where one of them addresses a subject more specifically than this page does, the more specific one governs that subject.",
            "If you accept these terms for a company or another organisation, you confirm that you are authorised to bind it, and “you” means that organisation.",
        ],
    },
    {
        heading: "What Contamos is, and what it is not",
        paragraphs: [
            "Contamos is a shared multi-currency ledger. It records what its members enter and nothing else. It is not accounting software, it is not a bank or a payment service, it does not move money, and it does not connect to your bank to fetch anything.",
            "Nothing in Contamos is financial, legal, tax or investment advice. The figures it shows are the figures the people in your team typed into it, converted at the rates they chose. Check them before you rely on them, and take professional advice before any decision that needs it.",
            "Contamos is under active development. Features are added, changed and sometimes withdrawn, and what a plan includes is what the pricing page and the app say it includes today.",
        ],
    },
    {
        heading: "Your account",
        paragraphs: [
            "You need an account to use Contamos, and you sign in through our authentication provider rather than with a password we hold. Keep that sign-in secure. Everything done through your account is treated as done by you.",
            "You must be at least 13 years old to use Contamos. If you are under the age of majority where you live, you may only use it with the permission of a parent or guardian, who accepts these terms with you and is responsible for your use of the service.",
            "Give us accurate details and keep them current.",
            "Write to :email as soon as you suspect that somebody else has reached your account. We may suspend an account while we look into it.",
        ],
    },
    {
        heading: "Teams, roles and the people you invite",
        paragraphs: [
            "A team is a shared ledger. Every member of a team can see everything recorded in it, including the name of whoever recorded each entry. Invite only the people you mean to share a ledger with, and do not record anything about somebody else that you have no right to share with the rest of the team.",
            "The member who creates a team owns it. An owner can invite members, remove them, change their roles where the plan allows it, and delete the team with everything recorded in it. That deletion is immediate and cannot be undone.",
            "Removing a member ends their access but does not erase what they recorded, because the members who stay need those entries in order to read their own ledger. Leaving a team works the same way.",
            "Between the members of a team, we take no side. We do not arbitrate disputes about what a ledger says, who owes what, or who should keep access to a team.",
        ],
    },
    {
        heading: "Acceptable use",
        paragraphs: [
            "Do not use Contamos to break the law, to launder money or to finance anything unlawful, to store or distribute material you have no right to, or to harass anybody.",
            "Do not try to reach data that is not yours, probe or bypass our security, disrupt or overload the service, scrape it, or use it through automated means beyond the interface we provide.",
            "Do not copy, resell, sublicense, rent or white-label Contamos, and do not reverse engineer or decompile it except to the extent the law says you may do so regardless of this clause.",
            "We may suspend or close an account that breaches this section, and where the breach is serious or repeated we may do so without notice.",
        ],
    },
    {
        heading: "What you record stays yours",
        paragraphs: [
            "You keep every right you have in what you record. We claim no ownership of it.",
            "You grant us only the licence we need to run the service: to store what you record, to display it to the members of your teams, to back it up and to process it as our privacy policy describes. The licence is worldwide and royalty-free because our servers and backups are, and it ends when the content is deleted, save for the backup cycle described in the privacy policy.",
            "If you send us an idea or a suggestion, we may use it to improve Contamos without owing you anything for it.",
        ],
    },
    {
        heading: "Our own rights",
        paragraphs: [
            "Contamos, its software, its design and its name belong to Qube SAS or to our licensors, and nothing in these terms transfers any of that to you. You get a personal, non-exclusive, non-transferable right to use the service while this agreement lasts, and nothing more.",
        ],
    },
    {
        heading: "Plans, prices and payment",
        paragraphs: [
            "The Free plan costs nothing and caps how many teams, accounts and members you can have, how far back the history opens, and which extra features are available. Nothing you recorded is ever deleted because of those caps: over a cap the ledger stops growing, it does not shrink.",
            "Pro lifts the caps and is billed in advance, monthly or yearly, and renews automatically at the then-current price until it is cancelled. The price and the billing cycle are shown before you pay.",
            "Paddle.com Market Ltd is the merchant of record for Pro. It sells the subscription to you, takes the payment, issues the invoice and handles the tax, so the purchase is also subject to Paddle's own buyer terms. Prices are shown inclusive or exclusive of tax as Paddle determines for your country.",
            "We may change the price of a plan. A change takes effect on your next renewal and never mid-period, and we will tell you by email before it does, in time for you to cancel.",
            "If a payment fails, Paddle will retry it. A subscription that stays unpaid returns to the Free plan, with the caps that plan carries, and again without deleting anything.",
        ],
    },
    {
        heading: "Cancelling, refunds and withdrawal",
        paragraphs: [
            "Cancel Pro at any time from the billing portal. The team keeps Pro until the end of the period already paid for and is not charged again.",
            "Our refund policy says when we return a payment, and it grants a full refund on request within fourteen days of any payment. Nothing in these terms limits a right of withdrawal or any other remedy that consumer law where you live gives you and does not allow us to exclude.",
        ],
    },
    {
        heading: "Availability, support and the absence of a warranty",
        paragraphs: [
            "We work to keep Contamos available and correct, and we keep backups, but we do not promise that the service will be uninterrupted, error-free, or available at any particular level. Maintenance, provider failures and faults happen.",
            "To the fullest extent the law allows, Contamos is provided as it is and as it is available, and we exclude every implied warranty, including those of merchantability, fitness for a particular purpose and non-infringement. We do not warrant that what the ledger shows is accurate, because that depends on what your team entered.",
            "Support is offered by email at :email. We answer as promptly as we reasonably can, and we do not commit to a response time.",
        ],
    },
    {
        heading: "Who answers for what",
        paragraphs: [
            "So far as the law allows, Qube SAS is not liable for indirect, incidental, special or consequential loss, for lost profit, revenue, goodwill or anticipated savings, for loss or corruption of data beyond our duty to keep reasonable backups, or for any decision taken on the basis of what the ledger shows.",
            "So far as the law allows, our total liability for all claims arising in any twelve-month period is limited to the amount you paid us for the service in the twelve months before the event that gave rise to the claim.",
            "Nothing in these terms excludes or limits liability for fraud, for death or personal injury caused by negligence, or for anything else that cannot lawfully be excluded or limited. If you are a consumer, these limits apply only so far as consumer law where you live permits.",
            "You are responsible for what is done through your account, and you will cover our reasonable losses and legal costs if a third party brings a claim against us because of what you recorded or how you used the service in breach of these terms.",
        ],
    },
    {
        heading: "Suspending and ending the agreement",
        paragraphs: [
            "You can end this agreement whenever you like: cancel Pro from the billing portal, or delete your account from the account screen. Our privacy policy explains exactly what deletion removes and what survives in the teams you shared.",
            "We may suspend or end it if you breach these terms, if we are required to by law, or if we stop offering Contamos altogether. Unless the breach is serious we will warn you first and give you a chance to put it right, and if we withdraw the service we will give reasonable notice and refund the unused part of anything paid in advance.",
            "Export what you want to keep before you go. Your transactions export to CSV from the app at any time.",
            "The sections on your content, our rights, liability, indemnity and governing law survive the end of this agreement.",
        ],
    },
    {
        heading: "Changes to these terms",
        paragraphs: [
            "We may change these terms as the service and the law behind it change, and the date at the top of the page always says when we last did. If a change materially affects your rights or obligations, we will tell you by email or in the app before it takes effect. Continuing to use Contamos after that date means you accept the new terms; if you do not, cancel and delete your account.",
        ],
    },
    {
        heading: "Personal data",
        paragraphs: [
            "How we handle personal data is set out in our privacy policy, which explains what we collect, why we are allowed to, who else sees it, how long it stays and what you can ask us to do with it.",
        ],
    },
    {
        heading: "The fine print",
        paragraphs: [
            "These terms, with the privacy and refund policies, are the whole agreement between us about Contamos and replace anything said before.",
            "We may transfer this agreement to another company that takes over the business, provided your rights are not reduced. You may not transfer it without our written consent.",
            "If a clause turns out to be unenforceable, the rest stays in force and that clause is read down no further than necessary. If we do not enforce a right straight away, we do not lose it.",
            "Neither of us answers for a failure caused by something genuinely beyond our control. Notices to you go to the email address on your account; notices to us go to :email.",
            "These terms are published in English and in Spanish. If the two versions differ, the English one prevails.",
        ],
    },
    {
        heading: "Governing law and disputes",
        paragraphs: [
            "This agreement is governed by the law of Uruguay, and its courts have jurisdiction over any dispute arising from it.",
            "If you are a consumer, this does not deprive you of the protection of the mandatory rules of the country where you live, and you may also bring proceedings there.",
            "Write to :email before you take any dispute further. Most of them are settled that way.",
        ],
    },
];

const Terms = ({ alternates }: PageProps) => {
    return (
        <LegalDocument
            alternates={alternates}
            description="The terms you agree to when you use Contamos: what the service is and is not, what sharing a team commits you to, how the plans are billed, and how either side ends the agreement."
            sections={SECTIONS}
            title="Terms of service"
            updatedAt="2026-09-22"
        />
    );
};

Terms.layout = (page: React.ReactNode) => <MarketingLayout>{page}</MarketingLayout>;

export default Terms;
