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
 * The in-flight or settled load, so the script is only
 * ever injected and initialised once per page.
 */
let loading: Promise<Paddle> | null = null;

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

            paddle.Initialize({ token: settings.token });
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
