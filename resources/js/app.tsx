import { createInertiaApp, router } from "@inertiajs/react";
import type { PageProps as ModalPageProps } from "@inertiaui/modal-react";
import { renderApp } from "@inertiaui/modal-react";
import { LaravelReactI18nProvider as I18nProvider } from "laravel-react-i18n";
import { StrictMode } from "react";
import { createRoot, hydrateRoot } from "react-dom/client";

import { toast, Toaster } from "./components/ui/toast";
import { TooltipProvider } from "./components/ui/tooltip";
import { trackEvent } from "./lib/analytics";

/**
 * Executed whenever a flash message is provided from
 * the server.
 */
router.on("flash", (event) => {
    // Steps that happened on the server, such as signing up,
    // reach Google Analytics from here.
    event.detail.flash.analytics?.forEach(({ name, params }) => trackEvent(name, params));

    const flashed = event.detail.flash.toast;

    if (!flashed) {
        return;
    }

    const action = flashed.action;

    const id: string = toast.add({
        // A write the server says can be taken back offers to do it
        // from the toast, which is where the user is already looking.
        actionProps: action
            ? {
                  children: action.label,
                  onClick: () => {
                      router.visit(action.url, {
                          method: action.method,
                          preserveScroll: true,
                      });

                      // The offer is spent, and the write it undid
                      // announces itself with a toast of its own.
                      toast.close(id);
                  },
              }
            : undefined,
        description: flashed.message,
    });
});

/**
 * The name of the application.
 */
const appName = import.meta.env.VITE_APP_NAME || "Laravel";

/**
 * The dictionaries, one loader per locale, each its own chunk.
 */
const dictionaries = import.meta.glob("/lang/*.json");

/**
 * Fetch the dictionaries for the given locales, keyed as the i18n
 * provider expects them.
 */
const loadDictionaries = async (paths: Array<string>): Promise<Record<string, unknown>> =>
    Object.fromEntries(await Promise.all(paths.map(async (path) => [path, await dictionaries[path]()])));

/**
 * The translation dictionaries handed to the i18n provider.
 *
 * The server renders every locale from one process, so it loads them
 * all. The browser only ever shows one, so it downloads that one alone
 * instead of shipping every language inside the main bundle on every
 * visit. The server sets `<html lang>` from the request's locale before
 * any script runs, which is what tells the browser which one to fetch.
 */
const translations = import.meta.env.SSR
    ? await loadDictionaries(Object.keys(dictionaries))
    : await loadDictionaries(
          Object.keys(dictionaries).filter(
              (path) => path === `/lang/${document.documentElement.lang.split("-")[0] || "en"}.json`,
          ),
      );

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
        const locale = (props.initialPage.props.locale as string | undefined) ?? "en";

        // Create the app to render.
        const app = (
            <StrictMode>
                <I18nProvider locale={locale} fallbackLocale="en" files={translations}>
                    <TooltipProvider>
                        {renderApp(App as unknown as Parameters<typeof renderApp>[0], props as ModalPageProps)}
                        <Toaster />
                    </TooltipProvider>
                </I18nProvider>
            </StrictMode>
        );

        if (el) {
            if (el.hasAttribute("data-server-rendered")) {
                hydrateRoot(el, app);
            } else {
                createRoot(el).render(app);
            }
        }

        return app;
    },

    /**
     * The page's title.
     */
    title: (title) => (title ? `${title} - ${appName}` : appName),
});
