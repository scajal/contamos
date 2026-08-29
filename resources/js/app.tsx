import { createInertiaApp } from "@inertiajs/react";
import type { PageProps as ModalPageProps } from "@inertiaui/modal-react";
import { renderApp } from "@inertiaui/modal-react";
import { StrictMode } from "react";
import { createRoot } from "react-dom/client";

/**
 * The name of the application.
 */
const appName = import.meta.env.VITE_APP_NAME || "Laravel";

/**
 * Create the Inertia app.
 */
void createInertiaApp({
    /**
     * The progress bar's color.
     */
    progress: {
        color: "#7cce00",
    },

    /**
     * Mount the Inertia app using renderApp so ModalRoot is always present at the
     * app root (inside PageContext) on first paint, enabling direct modal URL visits.
     */
    setup({ el, App, props }) {
        createRoot(el!).render(
            <StrictMode>
                {renderApp(
                    App as unknown as Parameters<typeof renderApp>[0],
                    props as ModalPageProps,
                )}
            </StrictMode>,
        );
    },

    /**
     * The page's title.
     */
    title: (title) => (title ? `${title} - ${appName}` : appName),
});
