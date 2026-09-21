import { useCallback, useSyncExternalStore } from "react";

/**
 * What a hidden amount reads as. Every masked figure is the same width, so
 * nothing about the number behind it leaks from the layout.
 */
export const MASKED_AMOUNT = "••••";

/**
 * The storage key holding whether amounts are masked in this browser. The
 * choice belongs to the device someone is looking over the shoulder of, not
 * to the account, so it never leaves this browser.
 */
const STORAGE_KEY = "balances-hidden";

/**
 * The components showing an amount, woken whenever the choice changes.
 */
const listeners = new Set<() => void>();

/**
 * The choice, read once and then kept in memory so every amount on the page
 * answers from the same value.
 */
let hidden: boolean | null = null;

/**
 * Read the choice from this browser. Private windows and blocked site data
 * throw on access, where nothing was ever hidden.
 */
function read(): boolean {
    try {
        return window.localStorage.getItem(STORAGE_KEY) === "1";
    } catch {
        return false;
    }
}

/**
 * Whether amounts are currently masked.
 */
function snapshot(): boolean {
    return (hidden ??= read());
}

/**
 * Subscribe to the choice, including the change another tab makes, so the
 * app does not show in one tab what was just hidden in another.
 */
function subscribe(listener: () => void): () => void {
    listeners.add(listener);

    const onStorage = (event: StorageEvent) => {
        if (event.key !== null && event.key !== STORAGE_KEY) {
            return;
        }

        hidden = null;
        listeners.forEach((notify) => notify());
    };

    window.addEventListener("storage", onStorage);

    return () => {
        listeners.delete(listener);
        window.removeEventListener("storage", onStorage);
    };
}

/**
 * Remember the choice and repaint every amount. A browser that refuses to
 * store it keeps the choice for this visit alone.
 */
function write(next: boolean): void {
    hidden = next;

    try {
        window.localStorage.setItem(STORAGE_KEY, next ? "1" : "0");
    } catch {
        // The choice still holds for this visit.
    }

    listeners.forEach((notify) => notify());
}

/**
 * Whether amounts are masked, and the two things a component does with that:
 * mask a figure it formatted, and flip the choice.
 *
 * Wrap every rendered figure in `mask`, including the ones inside a sentence.
 * A balance nobody wants on screen is no less private for sitting in a
 * "spent :amount of :budget" line.
 */
export function useBalances(): { hidden: boolean; mask: (amount: string) => string; toggle: () => void } {
    const hidden = useSyncExternalStore(subscribe, snapshot, () => false);

    const mask = useCallback((amount: string): string => (hidden ? MASKED_AMOUNT : amount), [hidden]);

    const toggle = useCallback((): void => write(!hidden), [hidden]);

    return { hidden, mask, toggle };
}
