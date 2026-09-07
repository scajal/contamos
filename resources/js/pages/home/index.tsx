import type React from "react";

import { MarketingLayout } from "@/layouts/marketing/layout";
import type { Alternates } from "@/types/app/modules/marketing/alternates";

import { Hero } from "./_components/hero";
import { SeoHead } from "./_components/seo-head";

interface HomeProps {
    dashboardUrl: string | null;
    alternates: Alternates;
}

const Home = ({ dashboardUrl, alternates }: HomeProps) => {
    return (
        <>
            <SeoHead alternates={alternates} />
            <Hero dashboardUrl={dashboardUrl} />
        </>
    );
};

Home.layout = (page: React.ReactNode) => <MarketingLayout>{page}</MarketingLayout>;

export default Home;
