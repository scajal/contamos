import { useLaravelReactI18n } from "laravel-react-i18n";
import { ArrowLeftRightIcon } from "lucide-react";

import { Avatar, AvatarFallback, AvatarImage } from "@/components/ui/avatar";
import { Item, ItemActions, ItemContent } from "@/components/ui/item";
import { Tooltip, TooltipContent, TooltipTrigger } from "@/components/ui/tooltip";
import { useBalances } from "@/hooks/use-balances";
import { TRANSACTION_AMOUNT_COLOR, TRANSACTION_AMOUNT_SIGN } from "@/lib/transactions";
import { counterpartAccountName } from "@/lib/transfers";
import { cn, formatAmount, initials } from "@/lib/utils";
import { Transaction } from "@/types/app/modules/core/finance/models/team/transaction";
import { User } from "@/types/app/modules/core/identity/models/user";

type Props = {
    actions?: React.ReactNode;
    /** Whether the team is shared, and so whether the author is worth showing. */
    showsAuthor?: boolean;
    transaction: Transaction;
};

/**
 * A movement's signed amount, in the colour its type carries.
 */
export const TransactionAmount = ({ className, transaction }: { className?: string; transaction: Transaction }) => {
    /**
     * Get the current locale.
     */
    const currentLocale = useLaravelReactI18n().currentLocale;

    /**
     * Mask the amount while balances are hidden.
     */
    const mask = useBalances().mask;

    return (
        <span className={cn("font-medium tabular-nums", TRANSACTION_AMOUNT_COLOR[transaction.type], className)}>
            {mask(
                `${TRANSACTION_AMOUNT_SIGN[transaction.type]} ${formatAmount(transaction.amount, currentLocale(), transaction.currency)}`,
            )}
        </span>
    );
};

/**
 * Who recorded the movement. On a shared ledger this is the difference
 * between reading the list as "my movements" and as "ours", and it costs
 * a row nothing but the width of an avatar.
 */
export const TransactionAuthor = ({ className, user }: { className?: string; user: User }) => {
    return (
        <Tooltip>
            <TooltipTrigger
                render={
                    <Avatar className={className} size="sm">
                        <AvatarImage src={user.avatar ?? undefined} />
                        <AvatarFallback>{initials(user.name)}</AvatarFallback>
                    </Avatar>
                }
            />
            <TooltipContent>{user.name}</TooltipContent>
        </Tooltip>
    );
};

/**
 * A movement as a two-line row, for viewports where the table's columns do
 * not fit. The amount is the point of the row, so it shares the first line
 * with the description instead of scrolling off the right edge.
 */
export const TransactionRow = ({ actions, showsAuthor = false, transaction }: Props) => {
    /**
     * Translate the provided string to the
     * current locale.
     */
    const { currentLocale, t } = useLaravelReactI18n();

    /**
     * The account on the other side, so a transfer row says where the
     * money went or came from.
     */
    const counterpart = transaction.transfer_id ? counterpartAccountName(transaction) : null;

    /**
     * The time of day the movement happened, which sits on the second
     * line because the day already heads the group.
     */
    const time = new Intl.DateTimeFormat(currentLocale(), {
        hour: "2-digit",
        minute: "2-digit",
    }).format(new Date(transaction.occurred_at));

    return (
        <Item className="rounded-none border-0 border-b last:border-b-0" size="sm" variant="outline">
            <ItemContent className="gap-0.5">
                <div className="flex items-baseline justify-between gap-3">
                    <span className="min-w-0 truncate font-medium">
                        {counterpart ?? (transaction.description || t(`enums.${transaction.type}`))}
                    </span>
                    <TransactionAmount className="shrink-0" transaction={transaction} />
                </div>
                <div className="text-muted-foreground flex min-w-0 items-center gap-1.5 text-xs">
                    {transaction.transfer_id ? (
                        <ArrowLeftRightIcon aria-label={t("Transfer")} className="size-3 shrink-0" />
                    ) : null}
                    <span className="truncate">{transaction.account?.name ?? "-"}</span>
                    <span aria-hidden="true">·</span>
                    <span className="shrink-0 tabular-nums">{time}</span>
                    {transaction.tags?.length ? (
                        <>
                            <span aria-hidden="true">·</span>
                            <span className="truncate">{transaction.tags.map((tag) => tag.name).join(", ")}</span>
                        </>
                    ) : null}
                    {showsAuthor && transaction.user ? (
                        <>
                            <span aria-hidden="true">·</span>
                            <span className="shrink-0">{transaction.user.name.split(" ")[0]}</span>
                        </>
                    ) : null}
                </div>
            </ItemContent>
            {actions ? <ItemActions>{actions}</ItemActions> : null}
        </Item>
    );
};
