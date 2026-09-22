import type React from "react";

import { MarketingLayout } from "@/layouts/marketing/layout";
import type { Alternates } from "@/types/app/modules/marketing/alternates";
import type { LegalSection } from "@/types/app/modules/marketing/legal";

import { LegalDocument } from "./_components/legal-document";

type PageProps = {
    alternates: Alternates;
};

/**
 * What happens to money already paid for Pro: how to stop being
 * charged, when a payment comes back and when it does not, how long
 * it takes, and who actually processes it.
 */
const SECTIONS: Array<LegalSection> = [
    {
        heading: "What this policy covers",
        paragraphs: [
            "This policy covers payments for the Contamos Pro plan, sold by Qube SAS through Paddle.com Market Ltd. The Free plan costs nothing, so there is nothing to refund on it.",
            "It forms part of our terms of service. Nothing in it takes away a right that consumer law where you live gives you and does not allow us to exclude.",
        ],
    },
    {
        heading: "Before you pay",
        paragraphs: [
            "Every plan, limit and price is listed on the pricing page and shown again at checkout, with the billing cycle and the total you will be charged.",
            "The Free plan lets you use Contamos for as long as you like without a card, so you can see what the product does before paying for it. We recommend trying it there first.",
        ],
    },
    {
        heading: "Cancelling",
        paragraphs: [
            "Cancel Pro whenever you like from the billing portal. The cancellation stops the next renewal; your team keeps Pro until the end of the period it has already paid for, and is not charged again.",
            "Going back to Free deletes nothing. The caps of the Free plan apply again and older history is hidden until the team is on Pro, but everything recorded is still there and comes back in full when it is.",
        ],
    },
    {
        heading: "The fourteen-day refund",
        paragraphs: [
            "Write to us within fourteen days of a payment and we refund it in full, without asking why. This applies to a first payment and to a renewal alike.",
            "Where consumer law gives you a right of withdrawal, this refund runs alongside it and never instead of it.",
        ],
    },
    {
        heading: "After fourteen days",
        paragraphs: [
            "We refund a payment that was plainly a mistake, such as a renewal you meant to cancel and did not use, a charge taken twice, or a charge taken after a cancellation.",
            "We also refund the unused part of a period if we withdraw the service or close your account for a reason that is not a breach of our terms on your side.",
            "Outside those cases we do not refund a period you have used, because a subscription is billed in advance for access rather than for usage. We look at every request on its facts even so, and we would rather settle a genuine problem than win an argument about it.",
        ],
    },
    {
        heading: "How to ask",
        paragraphs: [
            "Write to :email from the address on the account, naming the team and the payment concerned, and tell us briefly what happened. You can also request a refund through the link on the receipt Paddle emailed you.",
            "We answer within a few business days, and within fourteen days at the outside. If we decline, we will tell you why.",
        ],
    },
    {
        heading: "How the money comes back",
        paragraphs: [
            "A refund goes back to the method you paid with. We cannot send it anywhere else.",
            "Once approved, Paddle processes the refund within about five business days, and your bank or card issuer can take a few more to show it. Taxes charged on the payment are refunded with it.",
            "A refunded subscription ends immediately, and the team returns to the Free plan with everything it recorded intact.",
        ],
    },
    {
        heading: "Who sells Pro, and who refunds it",
        paragraphs: [
            "Paddle.com Market Ltd is the merchant of record for Contamos Pro. It sells the subscription, takes the payment, issues your invoice and handles the tax, so every refund is processed by Paddle even when we are the ones who approve it.",
            "That also means your purchase is subject to Paddle's own buyer terms, which sit alongside this policy rather than replace it.",
        ],
    },
    {
        heading: "Changes and contact",
        paragraphs: [
            "We may update this policy, and the date at the top of the page says when we last did. The policy that applies to a payment is the one published when the payment was taken.",
            "Write to :email with any question about this page. Qube SAS is established in Uruguay, whose law governs this policy.",
        ],
    },
];

const Refunds = ({ alternates }: PageProps) => {
    return (
        <LegalDocument
            alternates={alternates}
            description="How to cancel Contamos Pro, when a payment is refunded and when it is not, how long the money takes to come back, and how to ask."
            sections={SECTIONS}
            title="Refund policy"
            updatedAt="2026-09-22"
        />
    );
};

Refunds.layout = (page: React.ReactNode) => <MarketingLayout>{page}</MarketingLayout>;

export default Refunds;
