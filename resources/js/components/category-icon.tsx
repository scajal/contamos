import { categoryIcon } from "@/lib/categories";
import { Category } from "@/types/app/modules/core/finance/enums/transaction/category";

type Props = React.ComponentProps<"svg"> & {
    category: Category;
};

/**
 * The mark of a category, so a listing says what a movement is about
 * without spending a column on it.
 */
export const CategoryIcon = ({ category, ...props }: Props) => {
    /**
     * The lucide component the enum named for the category.
     */
    const Icon = categoryIcon(category);

    return <Icon {...props} />;
};
