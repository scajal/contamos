import { createInertiaApp, router } from "@inertiajs/react";
import type { PageProps as ModalPageProps } from "@inertiaui/modal-react";
import { renderApp } from "@inertiaui/modal-react";
import { LaravelReactI18nProvider as I18nProvider } from "laravel-react-i18n";
import { StrictMode } from "react";
import { createRoot } from "react-dom/client";

import { toast, Toaster } from "./components/ui/toast";
import { TooltipProvider } from "./components/ui/tooltip";

/**
 * Executed whenever a flash message is provided from
 * the server.
 */
router.on("flash", (event) => {
    if (event.detail.flash.toast) {
        toast.add({
            description: event.detail.flash.toast.message,
        });
    }
});

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
                <I18nProvider
                    locale={(document?.documentElement?.getAttribute?.("lang") as string) || "en"}
                    fallbackLocale="en"
                    files={import.meta.glob("/lang/*.json")}
                >
                    <TooltipProvider>
                        {renderApp(App as unknown as Parameters<typeof renderApp>[0], props as ModalPageProps)}
                        <Toaster />
                    </TooltipProvider>
                </I18nProvider>
            </StrictMode>,
        );
    },

    /**
     * The page's title.
     */
    title: (title) => (title ? `${title} - ${appName}` : appName),
});
