/**
 * JSON shape of App\Modules\Support\Objects\Toast when flashed via Inertia.
 */
export interface ToastAction {
    label: string;
    method: "delete" | "patch" | "post" | "put";
    url: string;
}

export interface Toast {
    action: ToastAction | null;
    id: string;
    message: string;
}
