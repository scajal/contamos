import { Fragment } from "react/jsx-runtime";

import { ApplicationLayout } from "@/layouts/application/layout";

const Index = () => {
    return <Fragment>It works</Fragment>;
};

Index.layout = (page: React.ReactNode) => <ApplicationLayout>{page}</ApplicationLayout>;

export default Index;
