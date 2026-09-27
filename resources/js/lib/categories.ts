import {
    BabyIcon,
    BriefcaseIcon,
    BusIcon,
    EllipsisIcon,
    GiftIcon,
    GraduationCapIcon,
    HeartHandshakeIcon,
    HeartPulseIcon,
    HouseIcon,
    LandmarkIcon,
    type LucideIcon,
    PawPrintIcon,
    PlaneIcon,
    PlugIcon,
    RepeatIcon,
    RotateCcwIcon,
    ShieldCheckIcon,
    ShoppingBagIcon,
    ShoppingCartIcon,
    SparklesIcon,
    TicketIcon,
    TrendingUpIcon,
    UtensilsIcon,
    WalletIcon,
} from "lucide-react";

import { Category } from "@/types/app/modules/core/finance/enums/transaction/category";

/**
 * One option of the category picker, as the controller serializes it
 * from the PHP enum: the stored value, its translated label, and
 * the name of the icon that stands for it.
 */
export type CategoryOption = {
    icon: string;
    label: string;
    value: Category;
};

/**
 * The categories each type a member records by hand offers, keyed by
 * the type value, as the transaction and recurring rule pages
 * receive them.
 */
export type CategoryOptions = Record<string, Array<CategoryOption>>;

/**
 * The lucide component behind every icon the category enum names, so
 * the chip, the listing and the detail draw the same mark the
 * backend chose.
 */
export const CATEGORY_ICONS: Record<Category, LucideIcon> = {
    eating_out: UtensilsIcon,
    education: GraduationCapIcon,
    entertainment: TicketIcon,
    freelance: BriefcaseIcon,
    gifts_and_donations: HeartHandshakeIcon,
    gifts_received: GiftIcon,
    groceries: ShoppingCartIcon,
    health: HeartPulseIcon,
    housing: HouseIcon,
    insurance: ShieldCheckIcon,
    investment_returns: TrendingUpIcon,
    kids_and_family: BabyIcon,
    other: EllipsisIcon,
    personal_care: SparklesIcon,
    pets: PawPrintIcon,
    refunds: RotateCcwIcon,
    salary: WalletIcon,
    shopping: ShoppingBagIcon,
    subscriptions: RepeatIcon,
    taxes_and_fees: LandmarkIcon,
    transport: BusIcon,
    travel: PlaneIcon,
    utilities: PlugIcon,
};

/**
 * The icon for a category, falling back to the neutral mark when a
 * row carries a category this build does not know.
 */
export const categoryIcon = (category: Category | null): LucideIcon =>
    (category && CATEGORY_ICONS[category]) || EllipsisIcon;

/**
 * Every category a team can filter by, in the order the enum lists
 * them, with the ones both types share kept once.
 */
export const flattenCategories = (categories: CategoryOptions): Array<CategoryOption> => {
    const seen = new Set<string>();

    return Object.values(categories)
        .flat()
        .filter((option) => !seen.has(option.value) && seen.add(option.value));
};
