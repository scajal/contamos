import type { Phase } from "../enums/launch/phase";

/**
 * JSON shape of App\Modules\Marketing\Objects\Launch, the Product Hunt
 * launch the homepage hero announces.
 */
export interface Launch {
    phase: Phase;
    url: string;
    code: string | null;
}
