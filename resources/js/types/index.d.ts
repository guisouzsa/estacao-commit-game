export interface User {
    id: string;
    username: string;
    bio?: string | null;
    avatar?: string | null;
    avatar_position_x?: number;
    avatar_position_y?: number;
    avatar_zoom?: number;
    cover?: string | null;
    cover_position_x?: number;
    cover_position_y?: number;
    profile_frame?: string | null;
    primary_badge?: string | null;
    profile_title?: string | null;
    developer_badge?: string | null;
    email: string;
    email_verified_at?: string;
    created_at?: string;
}

export type ToastTone = 'success' | 'warning' | 'info' | 'error';

export type ToastPayload = {
    id: string;
    tone: ToastTone;
    message: string;
};

export type PageProps<
    T extends Record<string, unknown> = Record<string, unknown>,
> = T & {
    auth: {
        user: User;
        /** conta permanente, visitante (sem login) ou ninguém. */
        kind: 'user' | 'guest' | null;
        /** Canal privado de avisos em tempo real desta pessoa. */
        channel: string | null;
        /** Não lidas na central (só contas permanentes). */
        unread_notifications?: number;
    };
    flash?: {
        toast?: ToastPayload | null;
    };
};
