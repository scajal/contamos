/**
 * The browser's Google Analytics identifiers, which a purchase
 * reported by the server needs to join the visit that led to it.
 */
export type AnalyticsIds = {
    ga_client_id?: string;
    ga_session_id?: string;
};

/**
 * Report an event to Google Analytics. Outside production, or
 * when the browser blocks it, the tag is missing and this does
 * nothing.
 */
export const trackEvent = (name: string, params: Record<string, unknown> = {}): void => {
    window.gtag?.("event", name, params);
};

/**
 * Read one field from the Google tag, giving up after the
 * timeout so a blocked tag never holds anything up.
 */
const readTag = (field: "client_id" | "session_id", timeout: number): Promise<string | undefined> =>
    new Promise((resolve) => {
        const gtag = window.gtag;
        const measurementId = window.gaMeasurementId;

        if (!gtag || !measurementId) {
            resolve(undefined);

            return;
        }

        const timer = window.setTimeout(() => resolve(undefined), timeout);

        gtag("get", measurementId, field, (value: unknown) => {
            window.clearTimeout(timer);
            resolve(value === undefined || value === null ? undefined : String(value));
        });
    });

/**
 * Get the browser's client and session identifiers, leaving
 * out whichever the tag could not give in time.
 */
export const analyticsIds = async (timeout = 1000): Promise<AnalyticsIds> => {
    const [clientId, sessionId] = await Promise.all([readTag("client_id", timeout), readTag("session_id", timeout)]);

    return {
        ...(clientId ? { ga_client_id: clientId } : {}),
        ...(sessionId ? { ga_session_id: sessionId } : {}),
    };
};
