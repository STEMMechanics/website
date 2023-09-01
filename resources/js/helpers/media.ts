import { Media } from "./api.types";

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
export const mediaStatus = (media: Media): MediaStatus => {
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
