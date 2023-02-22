export interface Event {
    start_at: string;
    end_at: string;
}

export interface EventResponse {
    event: Event;
}

export interface EventCollection {
    events: Event;
}

export interface Media {
    url: string;
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
}

export interface PostResponse {
    post: Post;
}

export interface PostCollection {
    posts: Array<Post>;
}

export interface User {
    id: string;
    username: string;
}

export interface UserResponse {
    user: User;
}

export interface UserCollection {
    users: Array<User>;
}
