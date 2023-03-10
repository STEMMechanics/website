export interface Event {
    id: string;
    title: string;
    hero: string;
    content: string;
    start_at: string;
    end_at: string;
    publish_at: string;
    location: string;
    address: string;
    status: string;
    registration_type: string;
    registration_data: string;
    price: string;
    ages: string;
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
    mime: string;
    permission: Array<string>;
    size: number;
    url: string;
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

export interface Post {
    id: string;
    title: string;
    slug: string;
    user_id: string;
    content: string;
    publish_at: string;
    hero: string;
    attachments: Array<Media>;
}

export interface PostResponse {
    post: Post;
}

export interface PostCollection {
    posts: Array<Post>;
    total: number;
}

export interface User {
    id: string;
    username: string;
    email: string;
    first_name: string;
    last_name: string;
    phone: string;
}

export interface UserResponse {
    user: User;
}

export interface UserCollection {
    users: Array<User>;
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
