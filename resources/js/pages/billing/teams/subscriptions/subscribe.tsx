import { useEffect } from "react";
import { Fragment } from "react/jsx-runtime";

import { Modal } from "@/components/ui/modal";
import { loadPaddle, type PaddleSettings } from "@/lib/paddle";

type PageProps = {
    checkout: Record<string, unknown>;
    paddle: PaddleSettings;
};

export default function Subscribe({ checkout, paddle }: PageProps) {
    useEffect(() => {
        loadPaddle(paddle)
            .then((instance) => {
                instance.Checkout.open({
                    ...checkout,
                    settings: {
                        ...(checkout.settings as Record<string, unknown>),
                        displayMode: "overlay",
                    },
                });
            })
            .catch((error: unknown) => {
                console.error(error);
            });
    }, [checkout, paddle]);

    return <Modal>{() => <Fragment></Fragment>}</Modal>;
}
