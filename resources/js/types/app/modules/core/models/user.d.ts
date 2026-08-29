/**
 * JSON shape of App\Modules\Core\Models\User when serialized for Inertia.
 * Hidden attributes (`external_id`, `remember_token`) are omitted.
 */
export interface User {
    id: string;
    avatar: string | null;
    email: string;
    email_verified_at: string | null;
    name: string;
    created_at: string | null;
    updated_at: string | null;
}
