import { router } from "@inertiajs/react";
import { useEffect, useRef } from "react";
import { Fragment } from "react/jsx-runtime";

import { Modal } from "@/components/ui/modal";
import { loadPaddle, onPaddleEvent, type PaddleSettings } from "@/lib/paddle";

type PageProps = {
    checkout: { settings?: { successUrl?: string } } & Record<string, unknown>;
    paddle: PaddleSettings;
};

export default function Subscribe({ checkout, paddle }: PageProps) {
    /**
     * Close this modal, captured from the render props so the
     * Paddle listener outside the render tree can call it.
     */
    const close = useRef<() => void>(() => {});

    // Paddle's overlay does not always send the browser to the
    // success URL on its own, so the completed event carries the
    // team to the billing page, which polls until the webhook
    // activates the plan. Closing the overlay without paying just
    // closes this modal.
    useEffect(() => {
        const successUrl = checkout.settings?.successUrl;
        let completed = false;

        return onPaddleEvent((event) => {
            if (event.name === "checkout.completed") {
                completed = true;

                if (successUrl) {
                    router.visit(successUrl);
                }
            }

            if (event.name === "checkout.closed" && !completed) {
                close.current();
            }
        });
    }, [checkout]);

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

    return (
        <Modal>
            {(props) => {
                close.current = props.close;

                return <Fragment></Fragment>;
            }}
        </Modal>
    );
}
