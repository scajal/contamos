/**
 * JSON shape of an AI assistant the user authorized at WorkOS, as
 * App\Modules\Core\Identity\Http\Controllers\User\AssistantController
 * sends it.
 */
export interface ConnectedApp {
    id: string;
    name: string;
}

/**
 * The connected assistants, and whether WorkOS could not be asked
 * for them.
 */
export interface ConnectedApps {
    items: ConnectedApp[];
    failed: boolean;
}
