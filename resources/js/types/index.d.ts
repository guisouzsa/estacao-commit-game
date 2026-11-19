export interface User {
    id: string;
    username: string;
    display_name: string;
    bio?: string | null;
    avatar?: string | null;
    avatar_position_x?: number;
    avatar_position_y?: number;
    profile_frame?: string | null;
    primary_badge?: string | null;
    profile_title?: string | null;
    developer_badge?: string | null;
    email: string;
    email_verified_at?: string;
    created_at?: string;
}

export type PageProps<
    T extends Record<string, unknown> = Record<string, unknown>,
> = T & {
    auth: {
        user: User;
    };
};
