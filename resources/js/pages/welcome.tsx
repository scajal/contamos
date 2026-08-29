import { Fragment } from "react/jsx-runtime";

import { ApplicationLayout } from "@/layouts/application/layout";

const Welcome = () => {
    return <Fragment>It works!</Fragment>;
};

Welcome.layout = (page: React.ReactNode) => <ApplicationLayout>{page}</ApplicationLayout>;

export default Welcome;
