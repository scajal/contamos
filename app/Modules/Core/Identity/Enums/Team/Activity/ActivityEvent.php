<?php

declare(strict_types=1);

namespace App\Modules\Core\Identity\Enums\Team\Activity;

use Filament\Support\Contracts\HasLabel;

/**
 * What a team's activity entry says happened. The value is
 * the event name the entry is stored with; the label is what
 * the timeline shows in its place.
 */
enum ActivityEvent: string implements HasLabel
{
    /**
     * A record was created.
     */
    case Created = 'created';

    /**
     * A record was changed.
     */
    case Updated = 'updated';

    /**
     * A record was deleted.
     */
    case Deleted = 'deleted';

    /**
     * The tags of a record were changed.
     */
    case TagsSynced = 'tags_synced';

    /**
     * A currency was added to an account.
     */
    case CurrencyAttached = 'currency_attached';

    /**
     * A currency was removed from an account.
     */
    case CurrencyDetached = 'currency_detached';

    /**
     * Somebody joined the team.
     */
    case MemberAdded = 'member_added';

    /**
     * Somebody left or was removed from the team.
     */
    case MemberRemoved = 'member_removed';

    /**
     * A member's role changed.
     */
    case MemberRoleUpdated = 'member_role_updated';

    /**
     * Get the label for the event.
     */
    public function getLabel(): string
    {
        return match ($this) {
            self::Created => trans('Created'),
            self::Updated => trans('Updated'),
            self::Deleted => trans('Deleted'),
            self::TagsSynced => trans('Tags changed'),
            self::CurrencyAttached => trans('Currency added'),
            self::CurrencyDetached => trans('Currency removed'),
            self::MemberAdded => trans('Member added'),
            self::MemberRemoved => trans('Member removed'),
            self::MemberRoleUpdated => trans('Role changed'),
        };
    }

    /**
     * Get the label for a stored event name, falling back to
     * the name itself for one this enum does not know yet.
     */
    public static function labelFor(?string $event): string
    {
        return self::tryFrom((string) $event)?->getLabel() ?? (string) $event;
    }
}
