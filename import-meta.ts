export interface ImportMetaExtras extends ImportMeta {
    env: {
        APP_URL: string;
        [key: string]: string;
    };
}
