/* https://blog.logrocket.com/axios-vs-fetch-best-http-requests/ */

import { useUserStore } from "../store/UserStore";

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
    body?: string | object;
    progress?: ApiProgressCallback;
}

export interface ApiResponse {
    status: number;
    message: string;
    data: unknown;
}

const apiDefaultHeaders = {
    Accept: "application/json",
    "Content-Type": "application/json;charset=UTF-8",
};

export const api = {
    timeout: 8000,
    baseUrl: "https://www.stemmechanics.com.au/api",

    send: function (options: ApiOptions) {
        return new Promise((resolve, reject) => {
            let url = this.baseUrl + options.url;

            if (options.params) {
                url =
                    url +
                    "?" +
                    Object.keys(options.params)
                        .map((key) => key + "=" + options.params[key])
                        .join("&");
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
                options.body = JSON.stringify(options.body);
            }

            const fetchOptions: RequestInit = {
                method: options.method || "GET",
                headers: options.headers,
                body: options.body,
            };

            let receivedData = false;

            fetch(url, fetchOptions)
                .then((response) => {
                    receivedData = true;

                    if (options.progress) {
                        if (!response.ok) {
                            return response;
                        }

                        if (!response.body) {
                            return response;
                            // return {
                            //     status: 0,
                            //     message:
                            //         "ReadableStream not yet supported in this browser.",
                            //     data: null,
                            // };
                        }

                        let contentLength =
                            response.headers.get("content-length");
                        if (!contentLength) {
                            contentLength = -1;
                        }

                        // parse the integer into a base-10 number
                        const total = parseInt(contentLength, 10);
                        let loaded = 0;
                        return new Response(
                            // create and return a readable stream
                            new ReadableStream({
                                start(controller) {
                                    const reader = response.body.getReader();
                                    read();
                                    /**
                                     *
                                     */
                                    function read() {
                                        reader
                                            .read()
                                            .then(({ done, value }) => {
                                                if (done) {
                                                    controller.close();
                                                    return;
                                                }
                                                loaded += value.byteLength;
                                                options.progress({
                                                    loaded,
                                                    total,
                                                });
                                                controller.enqueue(value);
                                                read();
                                            })
                                            .catch((error) => {
                                                controller.error(error);
                                                reject({
                                                    status: 0,
                                                    message: "controller error",
                                                    data: null,
                                                });
                                            });
                                    }
                                },
                            })
                        );
                    }

                    return response;
                })
                .then(async (response) => {
                    const data = response.json ? await response.json() : {};
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
                    console.log(error);
                    // Handle any errors thrown during the fetch process
                    const { response, ...rest } = error;
                    reject({
                        ...rest,
                        response: response && response.json(),
                    });
                });
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
