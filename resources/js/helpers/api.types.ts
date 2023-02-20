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
