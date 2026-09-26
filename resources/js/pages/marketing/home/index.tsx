import type React from "react";
import { Fragment } from "react";

import { MarketingLayout } from "@/layouts/marketing/layout";
import type { PlanTableRow } from "@/pages/billing/teams/subscriptions/_components/plan-table";
import { BillingCycle } from "@/types/app/modules/billing/enums/billing-cycle";
import { PlanPrice } from "@/types/app/modules/billing/plan-price";
import type { Alternates } from "@/types/app/modules/marketing/alternates";
import type { Launch } from "@/types/app/modules/marketing/objects/launch";

import { Assistants } from "./_components/assistants";
import { Balances } from "./_components/balances";
import { Faq } from "./_components/faq";
import { Features } from "./_components/features";
import { Hero } from "./_components/hero";
import { HowItWorks } from "./_components/how-it-works";
import { Pricing } from "./_components/pricing";
import { SeoHead } from "./_components/seo-head";
import { StageFloor } from "./_components/stage-floor";

interface HomeProps {
    dashboardUrl: string | null;
    alternates: Alternates;
    features: Array<PlanTableRow>;
    launch: Launch | null;
    prices: Record<BillingCycle, PlanPrice>;
}

const Home = ({ dashboardUrl, alternates, features, launch, prices }: HomeProps) => {
    return (
        <Fragment>
            <SeoHead alternates={alternates} prices={prices} />

            {/*
             * The opening act shares one stage: the floor grid runs behind
             * both sections and is cut off by this wrapper's bottom edge,
             * so whatever section follows can start in its own colour.
             * overflow-x-clip (not hidden) keeps the oversized floor from
             * widening the page without trapping the sections vertically.
             */}
            <div className="bg-background relative overflow-x-clip">
                <StageFloor />
                <Hero dashboardUrl={dashboardUrl} launch={launch} />
                <Balances />
            </div>
            <Assistants dashboardUrl={dashboardUrl} />
            <Features />
            <HowItWorks />
            <Pricing dashboardUrl={dashboardUrl} features={features} prices={prices} />
            <Faq dashboardUrl={dashboardUrl} />
        </Fragment>
    );
};

Home.layout = (page: React.ReactNode) => <MarketingLayout>{page}</MarketingLayout>;

export default Home;
