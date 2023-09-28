import { ImportMetaExtras } from "../../../import-meta";
import { Media, MediaJob } from "./api.types";
import { strCaseCmp, toTitleCase } from "./string";

export const mediaGetVariantUrl = (
    media: Media,
    variant = "scaled",
): string => {
    if (!media) {
        return "";
    }

    // If the variant is 'original', return the media url
    if (variant === "original") {
        return media.url;
    }

    // If the variant key exists in media.variants, return the corresponding variant URL
    if (media.variants && media.variants[variant]) {
        return media.url.replace(media.name, media.variants[variant]);
    }

    // If the variant key does not exist, return the 'scaled' variant
    return media.variants && media.variants["scaled"]
        ? media.url.replace(media.name, media.variants["scaled"])
        : media.url;
};

export const mediaGetWebURL = (media: Media): string => {
    const webUrl = (import.meta as ImportMetaExtras).env.APP_URL;
    const apiUrl = (import.meta as ImportMetaExtras).env.APP_URL_API;

    let url = media.url;

    // Is the URL a API request?
    if (media.url.startsWith(apiUrl)) {
        const fileUrlPath = media.url.substring(apiUrl.length);
        const fileUrlParts = fileUrlPath.split("/");

        if (
            fileUrlParts.length >= 4 &&
            fileUrlParts[0].length === 0 &&
            strCaseCmp("media", fileUrlParts[1]) === true &&
            strCaseCmp("download", fileUrlParts[3]) === true
        ) {
            url = webUrl + "/file/" + fileUrlParts[2];
        }
    }

    return url;
};

/**
 * Check if a mime matches.
 * @param {string} mimeExpected The mime expected.
 * @param {string} mimeToCheck The mime to check.
 * @returns {boolean} The mimeToCheck matches mimeExpected.
 */
export const mimeMatches = (
    mimeExpected: string,
    mimeToCheck: string,
): boolean => {
    if (mimeExpected.length == 0) {
        mimeExpected = "*";
    }

    const escapedExpectation = mimeExpected.replace(
        /[.*+?^${}()|[\]\\]/g,
        "\\$&",
    );
    const pattern = escapedExpectation.replace(/\\\*/g, ".*");
    const regex = new RegExp(`^${pattern}$`);

    return regex.test(mimeToCheck);
};

/**
 * MediaGetThumbnailCallback Type
 */
export type mediaGetThumbnailCallback = (url: string) => void;

/**
 * Get Media/File Thumbnail.
 * @param {Media|File} media The Media/File object.
 * @param {string|null} useVariant The variable to use.
 * @param {mediaGetThumbnailCallback|null} callback Callback with the thumbnail. Required when passing File.
 * @returns {string} The thumbnail url.
 */
export const mediaGetThumbnail = (
    media: Media | File,
    useVariant: string | null = "",
    callback: mediaGetThumbnailCallback | null = null,
): string => {
    let url: string = "";

    if (media) {
        if (media instanceof File) {
            if (callback != null) {
                if (mimeMatches("image/*", media.type) == true) {
                    const reader = new FileReader();

                    reader.onload = function (e) {
                        callback(e.target.result.toString());
                    };

                    reader.readAsDataURL(media);
                    return "";
                }
            }
        } else {
            if (
                useVariant &&
                useVariant != "" &&
                useVariant != null &&
                media.variants &&
                media.variants[useVariant]
            ) {
                url = media.url.replace(media.name, media.variants[useVariant]);
            }

            if (media.thumbnail && media.thumbnail.length > 0) {
                url = media.thumbnail;
            }

            if (media.variants && media.variants["thumb"]) {
                url = media.url.replace(media.name, media.variants["thumb"]);
            }
        }

        if (url === "") {
            url = "/assets/fileicons/unknown.webp";
        }
    }

    if (callback != null) {
        callback(url);
        return "";
    }

    return url;
};

/**
 * Check if the media is currently busy.
 * @param {Media} media The media item to check.
 * @returns {boolean} If the media is busy.
 */
export const mediaIsBusy = (media: Media): boolean => {
    let busy = false;

    if (media.jobs) {
        media.jobs.forEach((item) => {
            if (
                item.status != "invalid" &&
                item.status != "complete" &&
                item.status != "failed"
            ) {
                busy = true;
            }
        });
    }

    return busy;
};

interface MediaStatus {
    busy: boolean;
    status: string;
    status_text: string;
    progress: number;
}

/**
 * Get the current Media status
 * @param {Media} media The media item to check.
 * @returns {MediaStatus} The media status.
 */
export const getMediaStatus = (media: Media): MediaStatus => {
    const status = {
        busy: false,
        status: "",
        status_text: "",
        progress: 0,
    };

    if (media.jobs) {
        for (const item of media.jobs) {
            if (
                item.status != "invalid" &&
                item.status != "complete" &&
                item.status != "failed"
            ) {
                status.busy = true;
                status.status = item.status;
                status.status_text = item.status_text;
                status.progress = item.progress;
                break;
            }
        }
    }

    return status;
};

/**
 * Get the current Media status Text
 * @param {Media} media The media item to check.
 * @returns {string} Human readable string.
 */
export const getMediaStatusText = (media: Media): string => {
    let status = "";

    if (media.jobs.length > 0) {
        if (
            media.jobs[0].status != "invalid" &&
            media.jobs[0].status != "failed" &&
            media.jobs[0].status != "complete"
        ) {
            if (media.jobs[0].status_text != "") {
                status = toTitleCase(media.jobs[0].status_text);
            } else {
                status = toTitleCase(media.jobs[0].status);
            }

            if (media.jobs[0].progress_max != 0) {
                status += ` ${Math.floor(
                    (media.jobs[0].progress / media.jobs[0].progress_max) * 100,
                )}%`;
            }
        }
    }

    return status;
};

export interface MediaParams {
    id?: string;
    user_id?: string;
    title?: string;
    name?: string;
    mime_type?: string;
    permission?: string;
    size?: number;
    storage?: string;
    url?: string;
    thumbnail?: string;
    description?: string;
    dimensions?: string;
    variants?: { [key: string]: string };
    created_at?: string;
    updated_at?: string;
    jobs?: Array<MediaJob>;
}

export interface MediaJobParams {
    id?: string;
    media_id?: string;
    user_id?: string;
    status?: string;
    status_text?: string;
    progress?: number;
    progress_max?: number;
}

export const createMediaItem = (params?: MediaParams): Media => {
    const media = {
        id: params.id || "",
        user_id: params.user_id || "",
        title: params.title || "",
        name: params.name || "",
        mime_type: params.mime_type || "",
        permission: params.permission || "",
        size: params.size !== undefined ? params.size : 0,
        storage: params.storage || "",
        url: params.url || "",
        thumbnail: params.thumbnail || "",
        description: params.description || "",
        dimensions: params.dimensions || "",
        variants: params.variants || {},
        created_at: params.created_at || "",
        updated_at: params.updated_at || "",
        jobs: params.jobs || [],
    };

    return media;
};

export const createMediaJobItem = (params?: MediaJobParams): MediaJob => {
    const job = {
        id: params.id || "",
        media_id: params.media_id || "",
        user_id: params.user_id || "",
        status: params.status || "",
        status_text: params.status_text || "",
        progress: params.progress || 0,
        progress_max: params.progress_max || 0,
    };

    return job;
};
