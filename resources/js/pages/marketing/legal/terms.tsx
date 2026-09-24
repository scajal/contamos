import type React from "react";

import { MarketingLayout } from "@/layouts/marketing/layout";
import type { Alternates } from "@/types/app/modules/marketing/alternates";
import type { Document } from "@/types/app/modules/marketing/document";

import { LegalDocument } from "./_components/legal-document";

type PageProps = {
    alternates: Alternates;
    document: Document;
};

const Terms = ({ alternates, document }: PageProps) => {
    return <LegalDocument alternates={alternates} document={document} />;
};

Terms.layout = (page: React.ReactNode) => <MarketingLayout>{page}</MarketingLayout>;

export default Terms;
