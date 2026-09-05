import { Fragment } from "react/jsx-runtime";

import { ApplicationLayout } from "@/layouts/application/layout";

const Dashboard = () => {
    return <Fragment>It works!</Fragment>;
};

Dashboard.layout = (page: React.ReactNode) => <ApplicationLayout>{page}</ApplicationLayout>;

export default Dashboard;
