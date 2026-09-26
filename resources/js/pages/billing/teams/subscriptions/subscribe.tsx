import { router } from "@inertiajs/react";
import { useEffect, useRef } from "react";
import { Fragment } from "react/jsx-runtime";

import { Modal } from "@/components/ui/modal";
import { analyticsIds, trackEvent } from "@/lib/analytics";
import { loadPaddle, onPaddleEvent, type PaddleSettings } from "@/lib/paddle";

type PageProps = {
    checkout: {
        customData?: Record<string, unknown>;
        items?: Array<{ priceId: string; quantity: number }>;
        settings?: { successUrl?: string };
    } & Record<string, unknown>;
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
        trackEvent("begin_checkout", {
            items: checkout.items?.map(({ priceId, quantity }) => ({ item_id: priceId, quantity })),
        });

        // The browser's analytics identifiers ride along with the
        // checkout, so the purchase the webhook reports joins the
        // visit that led to it.
        Promise.all([loadPaddle(paddle), analyticsIds()])
            .then(([instance, ids]) => {
                instance.Checkout.open({
                    ...checkout,
                    customData: { ...checkout.customData, ...ids },
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
