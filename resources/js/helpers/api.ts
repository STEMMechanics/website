import { useUserStore } from "../store/UserStore";
import { useApplicationStore } from "../store/ApplicationStore";
import { useCacheStore } from "../store/CacheStore";
import { ImportMetaExtras } from "../../../import-meta";

interface ApiProgressData {
    loaded: number;
    total: number;
}

interface ApiCallbackData {
    status: number;
    statusText: string;
    url: string;
    headers: unknown;
    data: unknown;
}

type ApiProgressCallback = (progress: ApiProgressData) => void;
type ApiResultCallback = (data: ApiCallbackData) => void;

export interface ApiOptions {
    url: string;
    params?: object;
    method?: string;
    headers?: HeadersInit;
    body?: string | object | FormData | ArrayBuffer | Blob;
    signal?: AbortSignal | null;
    progress?: ApiProgressCallback;
    callback?: ApiResultCallback;
    chunk?: string;
}

export interface ApiResponse {
    status: number;
    message: string;
    data: unknown;
    json?: Record<string, unknown>;
}

const apiDefaultHeaders = {
    Accept: "application/json",
    "Content-Type": "application/json;charset=UTF-8",
};

export const api = {
    timeout: 8000,
    baseUrl: (import.meta as ImportMetaExtras).env.APP_URL_API,

    send: function (options: ApiOptions) {
        return new Promise((resolve, reject) => {
            let url = this.baseUrl + options.url;

            if (options.params) {
                let params = "";

                for (const [key, value] of Object.entries(options.params)) {
                    const placeholder = `{${key}}`;
                    if (url.includes(placeholder)) {
                        url = url.replace(
                            placeholder,
                            encodeURIComponent(value),
                        );
                    } else {
                        params += `&${encodeURIComponent(
                            key,
                        )}=${encodeURIComponent(value)}`;
                    }
                }

                url = url.replace(/{(.*?)}/g, "$1");
                if (params.length > 0) {
                    url += (url.includes("?") ? "" : "?") + params.substring(1);
                }
            }

            options.headers = {
                ...apiDefaultHeaders,
                ...(options.headers || {}),
            };

            const userStore = useUserStore();
            if (userStore.id) {
                options.headers["Authorization"] = `Bearer ${userStore.token}`;
            }

            options.method = options.method.toUpperCase() || "GET";

            if (options.body && typeof options.body === "object") {
                if (options.body instanceof FormData) {
                    if (
                        Object.prototype.hasOwnProperty.call(
                            options.headers,
                            "Content-Type",
                        )
                    ) {
                        // remove the "Content-Type" key from the headers object
                        delete options.headers["Content-Type"];
                    }

                    if (options.method != "POST") {
                        options.body.append("_method", options.method);
                        options.method = "POST";
                    }
                } else if (
                    options.body instanceof Blob ||
                    options.body instanceof ArrayBuffer
                ) {
                    // do nothing, let XHR handle these types of bodies without a Content-Type header
                } else {
                    options.body = JSON.stringify(options.body);
                    options.headers["Content-Type"] = "application/json";
                }
            }

            if (
                (options.method == "POST" ||
                    options.method == "PUT" ||
                    options.method == "PATCH") &&
                options.progress
            ) {
                const xhr = new XMLHttpRequest();

                xhr.upload.onprogress = function (event) {
                    if (event.lengthComputable) {
                        options.progress({
                            loaded: event.loaded,
                            total: event.total,
                        });
                    }
                };

                xhr.open(options.method, url);
                for (const header in options.headers) {
                    xhr.setRequestHeader(header, options.headers[header]);
                }
                xhr.onload = function () {
                    const result = {
                        status: xhr.status,
                        statusText: xhr.statusText,
                        url: url,
                        headers: {},
                        data: "",
                    };

                    const headersString = xhr.getAllResponseHeaders();
                    const headersArray = headersString.trim().split("\n");
                    headersArray.forEach((header) => {
                        const [name, value] = header.trim().split(":");
                        result.headers[name] = value.trim();
                    });

                    if (
                        xhr.response &&
                        result.headers["content-type"] == "application/json"
                    ) {
                        try {
                            result.data = JSON.parse(xhr.response);
                        } catch (error) {
                            result.data = xhr.response;
                        }
                    } else {
                        result.data = xhr.response;
                    }

                    useApplicationStore().unavailable = false;
                    if (xhr.status < 300) {
                        if (options.callback) {
                            options.callback(result);
                        } else {
                            resolve(result);
                        }

                        return;
                    } else {
                        if (xhr.status == 503) {
                            useApplicationStore().unavailable = true;
                        }

                        if (options.callback) {
                            options.callback(result);
                        } else {
                            reject(result);
                        }

                        return;
                    }
                };

                try {
                    xhr.send(options.body as XMLHttpRequestBodyInit);
                } catch (e) {
                    console.log(e);
                }
            } else {
                const fetchOptions: RequestInit = {
                    method: options.method.toUpperCase() || "GET",
                    headers: options.headers,
                    signal: options.signal || null,
                };

                if (
                    (typeof options.body == "string" &&
                        options.body.length > 0) ||
                    options.body instanceof FormData
                ) {
                    fetchOptions.body = options.body;
                }

                if (fetchOptions.method == "GET" && options.callback) {
                    const cache = useCacheStore().getCacheByUrl(url);
                    if (cache != null) {
                        options.callback(cache);
                    }
                }

                fetch(url, fetchOptions)
                    .then(async (response) => {
                        let data: string | object = "";
                        if (response.headers.get("content-length") !== "0") {
                            if (
                                response &&
                                response.headers.get("content-type") == null
                            ) {
                                try {
                                    data = response.json
                                        ? await response.json()
                                        : {};
                                } catch (error) {
                                    try {
                                        data = response.text
                                            ? await response.text()
                                            : "";
                                    } catch (error) {
                                        data = "";
                                    }
                                }
                            } else {
                                data =
                                    response && response.json
                                        ? await response.json()
                                        : {};
                            }
                        }

                        const result = {
                            status: response.status,
                            statusText: response.statusText,
                            url: response.url,
                            headers: response.headers,
                            data: data,
                        };

                        useApplicationStore().unavailable = false;
                        if (response.status >= 300) {
                            if (response.status === 503) {
                                useApplicationStore().unavailable = true;
                            }

                            if (options.callback) {
                                options.callback(result);
                            } else {
                                reject(result);
                            }

                            return;
                        }

                        if (options.callback) {
                            if (fetchOptions.method == "GET") {
                                const modified = useCacheStore().updateCache(
                                    url,
                                    result,
                                );

                                if (modified == false) {
                                    return;
                                }
                            }

                            options.callback(result);
                            return;
                        }

                        resolve(result);
                    })
                    .catch((error) => {
                        // Handle any errors thrown during the fetch process
                        const { response, ...rest } = error;
                        const result = {
                            ...rest,
                            response: response && response.json(),
                        };

                        if (options.callback) {
                            options.callback(result);
                        } else {
                            reject(result);
                        }

                        return;
                    });
            }
        });
    },

    get: async function (options: ApiOptions | string): Promise<ApiResponse> {
        let apiOptions = {} as ApiOptions;

        if (typeof options == "string") {
            apiOptions.url = options;
        } else {
            apiOptions = options;
        }

        apiOptions.method = "GET";
        return await this.send(apiOptions);
    },

    post: async function (options: ApiOptions | string): Promise<ApiResponse> {
        let apiOptions = {} as ApiOptions;

        if (typeof options == "string") {
            apiOptions.url = options;
        } else {
            apiOptions = options;
        }

        apiOptions.method = "POST";
        return await this.send(apiOptions);
    },

    put: async function (options: ApiOptions | string): Promise<ApiResponse> {
        let apiOptions = {} as ApiOptions;

        if (typeof options == "string") {
            apiOptions.url = options;
        } else {
            apiOptions = options;
        }

        apiOptions.method = "PUT";
        return await this.send(apiOptions);
    },

    delete: async function (
        options: ApiOptions | string,
    ): Promise<ApiResponse> {
        let apiOptions = {} as ApiOptions;

        if (typeof options == "string") {
            apiOptions.url = options;
        } else {
            apiOptions = options;
        }

        apiOptions.method = "DELETE";
        return await this.send(apiOptions);
    },

    chunk: async function (options: ApiOptions | string): Promise<ApiResponse> {
        let apiOptions = {} as ApiOptions;

        // setup api options
        if (typeof options == "string") {
            apiOptions.url = options;
        } else {
            apiOptions = options;
        }

        // set method to post by default
        if (!Object.prototype.hasOwnProperty.call(apiOptions, "method")) {
            apiOptions.method = "POST";
        }

        // check for chunk option
        if (
            Object.prototype.hasOwnProperty.call(apiOptions, "chunk") &&
            Object.prototype.hasOwnProperty.call(apiOptions, "body") &&
            apiOptions.body instanceof FormData
        ) {
            if (apiOptions.body.has(apiOptions.chunk)) {
                const file = apiOptions.body.get(apiOptions.chunk);

                if (file instanceof File) {
                    const chunkSize = 2 * 1024 * 1024;
                    let chunk = 0;
                    let chunkCount = 1;
                    let job_id = -1;

                    if (file.size > chunkSize) {
                        chunkCount = Math.ceil(file.size / chunkSize);
                    }

                    let result = null;
                    for (chunk = 0; chunk < chunkCount; chunk++) {
                        const offset = chunk * chunkSize;
                        const fileChunk = file.slice(
                            offset,
                            offset + chunkSize,
                        );

                        const chunkFormData = new FormData();
                        if (job_id == -1) {
                            for (const [field, value] of apiOptions.body) {
                                chunkFormData.append(field, value);
                            }

                            chunkFormData.append("name", file.name);
                            chunkFormData.append("size", file.size.toString());
                            chunkFormData.append("mime_type", file.type);
                        } else {
                            chunkFormData.append("job_id", job_id.toString());
                        }

                        chunkFormData.set(apiOptions.chunk, fileChunk);
                        chunkFormData.append("chunk", (chunk + 1).toString());
                        chunkFormData.append(
                            "chunk_count",
                            chunkCount.toString(),
                        );

                        const chunkOptions = {
                            method: apiOptions.method,
                            url: apiOptions.url,
                            params: apiOptions.params || {},
                            body: chunkFormData,
                            headers: apiOptions.headers || {},
                            progress: (progressEvent) => {
                                if (
                                    Object.prototype.hasOwnProperty.call(
                                        apiOptions,
                                        "progress",
                                    )
                                ) {
                                    apiOptions.progress({
                                        loaded:
                                            chunk * chunkSize +
                                            progressEvent.loaded,
                                        total: file.size,
                                    });
                                }
                            },
                        };

                        result = await this.send(chunkOptions);
                        job_id = result.data.media_job.id;
                    }

                    return result;
                }
            }
        }

        return await this.send(apiOptions);
    },
};

/**
 * Get an api result data as type.
 * @param result The api result object.
 * @param defaultValue The default data to return if no result exists.
 * @returns Data object.
 */
export function getApiResultData<T>(
    // eslint-disable-next-line @typescript-eslint/no-explicit-any
    result: any,
    defaultValue: T | null = null,
): T | null {
    if (!result || !Object.prototype.hasOwnProperty.call(result, "data")) {
        return defaultValue;
    }

    const data = result.data as T;
    return data instanceof Object ? data : defaultValue;
}
