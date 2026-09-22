/**
 * What the server tells the client about Paddle so the
 * script can be initialised without a Blade directive.
 */
export type PaddleSettings = {
    /** Paddle's client-side token. */
    token: string;
    /** Whether to point Paddle at its sandbox. */
    sandbox: boolean;
};

/**
 * The parts of Paddle.js the app uses.
 */
export type Paddle = NonNullable<Window["Paddle"]>;

/**
 * An event emitted by Paddle.js, such as checkout.completed
 * or checkout.closed.
 */
export type PaddleEvent = {
    name: string;
    data?: Record<string, unknown>;
};

/**
 * A listener for Paddle.js events.
 */
export type PaddleListener = (event: PaddleEvent) => void;

/**
 * The in-flight or settled load, so the script is only
 * ever injected and initialised once per page.
 */
let loading: Promise<Paddle> | null = null;

/**
 * Who is listening for Paddle.js events. Paddle takes one
 * callback at initialisation and never again, so it fans out
 * to whichever checkout page is mounted at the time.
 */
const listeners = new Set<PaddleListener>();

/**
 * Listen for Paddle.js events until the returned function
 * is called.
 */
export const onPaddleEvent = (listener: PaddleListener): (() => void) => {
    listeners.add(listener);

    return () => {
        listeners.delete(listener);
    };
};

/**
 * Load Paddle.js on demand and initialise it. Checkout is
 * a rare action, so the script is fetched when a checkout
 * page mounts rather than on every page of the app.
 */
export const loadPaddle = (settings: PaddleSettings): Promise<Paddle> => {
    if (loading) {
        return loading;
    }

    loading = new Promise<Paddle>((resolve, reject) => {
        const initialise = () => {
            const paddle = window.Paddle;

            if (!paddle) {
                reject(new Error("Paddle.js loaded but window.Paddle is missing."));

                return;
            }

            if (settings.sandbox) {
                paddle.Environment.set("sandbox");
            }

            paddle.Initialize({
                eventCallback: (event) => {
                    listeners.forEach((listener) => listener(event));
                },
                token: settings.token,
            });
            resolve(paddle);
        };

        if (window.Paddle) {
            initialise();

            return;
        }

        const script = document.createElement("script");

        script.src = "https://cdn.paddle.com/paddle/v2/paddle.js";
        script.async = true;
        script.onload = initialise;
        script.onerror = () => {
            loading = null;
            reject(new Error("Paddle.js could not be loaded."));
        };

        document.head.appendChild(script);
    });

    return loading;
};
