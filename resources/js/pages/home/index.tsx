import type React from "react";

import { MarketingLayout } from "@/layouts/marketing/layout";
import type { PlanSummary } from "@/types/app/modules/billing/plan-summary";
import type { Alternates } from "@/types/app/modules/marketing/alternates";

import { Faq } from "./_components/faq";
import { FinalCta } from "./_components/final-cta";
import { Hero } from "./_components/hero";
import { HowItWorks } from "./_components/how-it-works";
import { Pillars } from "./_components/pillars";
import { Plans } from "./_components/plans";
import { Problem } from "./_components/problem";
import { SeoHead } from "./_components/seo-head";

interface HomeProps {
    plans: Array<PlanSummary>;
    dashboardUrl: string | null;
    alternates: Alternates;
}

const Home = ({ plans, dashboardUrl, alternates }: HomeProps) => {
    return (
        <>
            <SeoHead alternates={alternates} />
            <Hero dashboardUrl={dashboardUrl} />
            <Problem />
            <Pillars />
            <HowItWorks />
            <Plans plans={plans} dashboardUrl={dashboardUrl} />
            <Faq />
            <FinalCta dashboardUrl={dashboardUrl} />
        </>
    );
};

Home.layout = (page: React.ReactNode) => <MarketingLayout>{page}</MarketingLayout>;

export default Home;
