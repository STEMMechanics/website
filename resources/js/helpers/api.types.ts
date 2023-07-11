export type Booleanish = boolean | "true" | "false";

export type EmptyObject = { [key: string]: never };

export interface SessionRequest {
    id: number;
    session_id: number;
    type: string;
    path: string;
    created_at: string;
    updated_at: string;
}

export interface Session {
    id: number;
    ip: string;
    useragent: string;
    created_at: string;
    updated_at: string;
    ended_at: string;
    requests?: SessionRequest[];
}

export interface SessionCollection {
    sessions: Session[];
    total: number;
}

export interface SessionRequestCollection {
    session: Session;
}

export interface Event {
    id: string;
    title: string;
    hero: Media;
    content: string;
    start_at: string;
    end_at: string;
    publish_at: string;
    location: string;
    location_url: string;
    address: string;
    status: string;
    registration_type: string;
    registration_data: string;
    price: string;
    ages: string;
    attachments: Array<Media>;
    created_at: string;
    updated_at: string;
}

export interface EventResponse {
    event: Event;
}

export interface EventCollection {
    events: Event[];
    total: number;
}

export interface Media {
    id: string;
    user_id: string;
    title: string;
    name: string;
    mime_type: string;
    permission: string;
    size: number;
    status: string;
    storage: string;
    url: string;
    description: string;
    dimensions: string;
    variants: { [key: string]: string };
    created_at: string;
    updated_at: string;
}

export interface MediaResponse {
    medium: Media;
}

export interface MediaCollection {
    media: Array<Media>;
    total: number;
}

export interface Article {
    id: string;
    title: string;
    slug: string;
    user_id: string;
    user: User;
    content: string;
    publish_at: string;
    hero: Media;
    attachments: Array<Media>;
}

export interface Article {
    id: string;
    title: string;
    slug: string;
    user: User;
    content: string;
    publish_at: string;
    hero: Media;
    attachments: Array<Media>;
    created_at: string;
    updated_at: string;
}

export interface ArticleResponse {
    article: Article;
}

export interface ArticleCollection {
    articles: Array<Article>;
    total: number;
}

export interface User {
    id: string;
    username: string;
    email: string;
    first_name: string;
    last_name: string;
    phone: string;
    display_name: string;
}

export interface UserResponse {
    user: User;
}

export interface UserCollection {
    users: Array<User>;
    total: number;
}

export interface LoginResponse {
    user: User;
    token: string;
}

export interface LogsDiscordResponse {
    log: {
        output: string;
        error: string;
    };
}

export interface Shortlink {
    id: number;
    code: string;
    url: string;
    used: number;
}

export interface ShortlinkCollection {
    shortlinks: Array<Shortlink>;
    total: number;
}

export interface ShortlinkResponse {
    shortlink: Shortlink;
}

export interface ApiInfo {
    version: string;
    max_upload_size: number;
}
