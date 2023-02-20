export interface ApiEventItem {
    start_at: string;
    end_at: string;
}

export interface ApiEvent {
    event: ApiEventItem;
}

export interface ApiMediaItem {
    url: string;
}

export interface ApiMedia {
    medium: ApiMediaItem;
}

export interface ApiPostItem {
    title: string;
    slug: string;
    user_id: string;
    content: string;
    publish_at: string;
    hero: string;
}

export interface ApiCollectionPost {
    post: ApiPostItem;
}

export interface ApiCollectionPosts {
    posts: Array<ApiPostItem>;
}

export interface ApiUser {
    id: string;
    username: string;
}

export interface ApiCollectionUser {
    user: ApiUser;
}

export interface ApiCollectionUsers {
    users: Array<ApiUser>;
}
