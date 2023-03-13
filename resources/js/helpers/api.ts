import { useProgressStore } from "../store/ProgressStore";
import { useUserStore } from "../store/UserStore";
import { ImportMetaExtras } from "../../../import-meta";

interface ApiProgressData {
    loaded: number;
    total: number;
}

type ApiProgressCallback = (progress: ApiProgressData) => void;

interface ApiOptions {
    url: string;
    params?: object;
    method?: string;
    headers?: HeadersInit;
    body?: string | object | FormData | ArrayBuffer | Blob;
    signal?: AbortSignal | null;
    progress?: ApiProgressCallback;
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
                        url = url.replace(placeholder, value);
                    } else {
                        params += `&${key}=${value}`;
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

            if (options.body && typeof options.body === "object") {
                if (options.body instanceof FormData) {
                    if (
                        Object.prototype.hasOwnProperty.call(
                            options.headers,
                            "Content-Type"
                        )
                    ) {
                        // remove the "Content-Type" key from the headers object
                        delete options.headers["Content-Type"];
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
                (options.method.toUpperCase() || "GET") == "POST" &&
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
                xhr.send(options.body as XMLHttpRequestBodyInit);

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

                    if (xhr.status < 300) {
                        resolve(result);
                    } else {
                        reject(result);
                    }
                };
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

                const progressStore = useProgressStore();
                progressStore.start();

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

                        if (response.status >= 300) {
                            reject(result);
                        }

                        resolve(result);
                    })
                    .catch((error) => {
                        // Handle any errors thrown during the fetch process
                        const { response, ...rest } = error;
                        reject({
                            ...rest,
                            response: response && response.json(),
                        });
                    })
                    .finally(() => {
                        progressStore.finish();
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
        return await this.send(options);
    },

    put: async function (options: ApiOptions | string): Promise<ApiResponse> {
        let apiOptions = {} as ApiOptions;

        if (typeof options == "string") {
            apiOptions.url = options;
        } else {
            apiOptions = options;
        }

        apiOptions.method = "PUT";
        return await this.send(options);
    },

    delete: async function (
        options: ApiOptions | string
    ): Promise<ApiResponse> {
        let apiOptions = {} as ApiOptions;

        if (typeof options == "string") {
            apiOptions.url = options;
        } else {
            apiOptions = options;
        }

        apiOptions.method = "DELETE";
        return await this.send(options);
    },
};
