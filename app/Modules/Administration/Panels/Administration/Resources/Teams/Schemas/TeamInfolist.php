<?php

declare(strict_types=1);

namespace App\Modules\Administration\Panels\Administration\Resources\Teams\Schemas;

use Filament\Schemas\Schema;

class TeamInfolist
{
    /**
     * Configure the resource's infolist.
     */
    public static function configure(Schema $schema): Schema
    {
        return $schema;
    }
}
