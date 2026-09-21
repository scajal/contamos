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
 * What happens to money already paid for Pro: how to stop being
 * charged, when a payment comes back, and who actually processes the
 * refund.
 */
const SECTIONS: Array<LegalSection> = [
    {
        heading: "What this covers",
        paragraphs: [
            "This policy covers payments for the Contamos Pro plan. The Free plan costs nothing, so there is nothing to refund on it.",
        ],
    },
    {
        heading: "Before you pay",
        paragraphs: [
            "Every plan, limit and price is listed on the pricing page and again in the app before checkout, and the Free plan lets you use Contamos for as long as you like without a card. Try it there first.",
        ],
    },
    {
        heading: "Cancelling",
        paragraphs: [
            "Cancel Pro whenever you like from the billing portal. Your team keeps Pro until the end of the period it already paid for and is not charged again.",
            "Going back to Free deletes nothing. Older history is hidden until the team is on Pro again, and everything you recorded is still there.",
        ],
    },
    {
        heading: "Refunds",
        paragraphs: [
            "Write to :email within 14 days of a payment and we refund it in full, without asking why.",
            "After those 14 days we refund a payment that was plainly a mistake, such as a renewal you meant to cancel or a charge taken twice.",
        ],
    },
    {
        heading: "How to ask",
        paragraphs: [
            "Write to :email from the address on the account, naming the team and the payment. We answer within a few business days.",
            "A refund goes back to the method you paid with. Paddle then takes a few more days to put it through, depending on your bank.",
        ],
    },
    {
        heading: "Merchant of record",
        paragraphs: [
            "Paddle.com Market Ltd sells Contamos Pro and issues your invoices, so every refund is processed by Paddle. You can also ask for one through the link on the receipt Paddle emailed you.",
        ],
    },
    {
        heading: "Contact",
        paragraphs: [
            "Write to :email with any question about this page. :entity is established in :jurisdiction, whose law governs this policy.",
        ],
    },
];

const Refunds = ({ alternates, legal }: PageProps) => {
    /**
     * The address a user writes to, shared by the server.
     */
    const { support } = usePage().props;

    return (
        <LegalDocument
            alternates={alternates}
            description="How to cancel Contamos Pro, when a payment is refunded, and how to ask for one."
            legal={legal}
            sections={SECTIONS}
            support={support.email}
            title="Refund policy"
        />
    );
};

Refunds.layout = (page: React.ReactNode) => <MarketingLayout>{page}</MarketingLayout>;

export default Refunds;
