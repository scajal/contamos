import { CheckIcon } from "lucide-react";

/**
 * Right-aligned check on a chip whose popover holds a non-default value.
 */
export const ChipSetCheck = ({ show }: { show: boolean }) => {
    if (!show) {
        return null;
    }

    return <CheckIcon aria-hidden className="text-success ml-auto size-3.5 shrink-0" data-icon="inline-end" />;
};
