import type React from "react";

import { MarketingLayout } from "@/layouts/marketing/layout";
import type { Alternates } from "@/types/app/modules/marketing/alternates";
import type { Plans } from "@/types/app/modules/marketing/plans";

import { Balances } from "./_components/balances";
import { Faq } from "./_components/faq";
import { Features } from "./_components/features";
import { Hero } from "./_components/hero";
import { Pricing } from "./_components/pricing";
import { SeoHead } from "./_components/seo-head";
import { StageFloor } from "./_components/stage-floor";

interface HomeProps {
    dashboardUrl: string | null;
    alternates: Alternates;
    plans: Plans;
}

const Home = ({ dashboardUrl, alternates, plans }: HomeProps) => {
    return (
        <>
            <SeoHead alternates={alternates} plans={plans} />

            {/*
             * The opening act shares one stage: the floor grid runs behind
             * both sections and is cut off by this wrapper's bottom edge,
             * so whatever section follows can start in its own colour.
             * overflow-x-clip (not hidden) keeps the oversized floor from
             * widening the page without trapping the sections vertically.
             */}
            <div className="bg-background relative overflow-x-clip">
                <StageFloor />
                <Hero dashboardUrl={dashboardUrl} />
                <Balances />
            </div>

            <Features />

            <Pricing plans={plans} dashboardUrl={dashboardUrl} />

            <Faq dashboardUrl={dashboardUrl} />
        </>
    );
};

Home.layout = (page: React.ReactNode) => <MarketingLayout>{page}</MarketingLayout>;

export default Home;
