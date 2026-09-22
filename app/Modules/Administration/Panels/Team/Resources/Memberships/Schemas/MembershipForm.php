<?php

declare(strict_types=1);

namespace App\Modules\Administration\Panels\Team\Resources\Memberships\Schemas;

use App\Modules\Core\Identity\Enums\Team\Role;
use Filament\Forms;
use Filament\Schemas;
use Filament\Schemas\Schema;

class MembershipForm
{
    /**
     * Configure the resource's form.
     */
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Schemas\Components\Section::make()
                    ->schema([
                        Forms\Components\Select::make('role')
                            ->placeholder(trans('Select a role'))
                            ->options(Role::class)
                            ->enum(Role::class)
                            ->required(),
                    ]),
            ]);
    }
}
