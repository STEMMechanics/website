type ImageLoadCallback = (url: string) => void;

export const imageLoad = (
    url: string,
    callback: ImageLoadCallback,
    postfix = "h=50"
) => {
    callback(`${url}?${postfix}`);
    const tmp = new Image();
    tmp.onload = function () {
        callback(url);
    };
    tmp.src = url;
};
