import { useEffect } from "react";
import { Fragment } from "react/jsx-runtime";

import { Modal } from "@/components/ui/modal";

type PageProps = {
    checkout: Record<string, unknown>;
};

export default function Subscribe({ checkout }: PageProps) {
    useEffect(() => {
        window.Paddle?.Checkout.open({
            ...checkout,
            settings: {
                ...(checkout.settings as Record<string, unknown>),
                displayMode: "overlay",
            },
        });
    }, [checkout]);

    return <Modal>{() => <Fragment></Fragment>}</Modal>;
}
