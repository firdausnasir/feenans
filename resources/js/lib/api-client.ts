type HttpMethod = 'GET' | 'POST' | 'PUT' | 'PATCH' | 'DELETE';

type RequestOptions = {
    params?: Record<string, string | number | boolean | string[] | number[] | null | undefined>;
    body?: Record<string, unknown> | FormData;
    signal?: AbortSignal;
    headers?: Record<string, string>;
};

export class ApiError extends Error {
    constructor(
        public readonly status: number,
        public readonly body: unknown,
        message?: string,
    ) {
        super(message ?? `API request failed with status ${status}`);
        this.name = 'ApiError';
    }

    get isUnauthorized(): boolean {
        return this.status === 401;
    }

    get isForbidden(): boolean {
        return this.status === 403;
    }

    get isNotFound(): boolean {
        return this.status === 404;
    }

    get isValidationError(): boolean {
        return this.status === 422;
    }

    get isServerError(): boolean {
        return this.status >= 500;
    }

    get validationErrors(): Record<string, string[]> {
        if (this.isValidationError && typeof this.body === 'object' && this.body !== null && 'errors' in this.body) {
            return (this.body as { errors: Record<string, string[]> }).errors;
        }

        return {};
    }
}

function getCsrfToken(): string {
    return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';
}

function buildUrl(path: string, params?: RequestOptions['params']): string {
    const url = new URL(path, window.location.origin);

    if (params) {
        for (const [key, value] of Object.entries(params)) {
            if (value === null || value === undefined) {
                continue;
            }

            if (Array.isArray(value)) {
                for (const item of value) {
                    url.searchParams.append(`${key}[]`, String(item));
                }
            } else {
                url.searchParams.set(key, String(value));
            }
        }
    }

    return url.pathname + url.search;
}

async function request<T>(method: HttpMethod, path: string, options: RequestOptions = {}): Promise<T> {
    const { params, body, signal, headers: extraHeaders } = options;
    const url = buildUrl(path, method === 'GET' ? params : undefined);

    const headers: Record<string, string> = {
        Accept: 'application/json',
        ...extraHeaders,
    };

    if (method !== 'GET') {
        headers['X-CSRF-TOKEN'] = getCsrfToken();
    }

    let fetchBody: BodyInit | undefined;

    if (body instanceof FormData) {
        fetchBody = body;
    } else if (body) {
        headers['Content-Type'] = 'application/json';
        fetchBody = JSON.stringify(method === 'GET' ? undefined : { ...body, ...(params ?? {}) });
    } else if (method !== 'GET' && params) {
        headers['Content-Type'] = 'application/json';
        fetchBody = JSON.stringify(params);
    }

    const response = await fetch(url, {
        method,
        headers,
        body: fetchBody,
        credentials: 'same-origin',
        signal,
    });

    if (!response.ok) {
        const errorBody = await response.json().catch(() => null);

        throw new ApiError(response.status, errorBody);
    }

    if (response.status === 204) {
        return undefined as T;
    }

    return response.json() as Promise<T>;
}

export const api = {
    get<T>(path: string, options?: RequestOptions): Promise<T> {
        return request<T>('GET', path, options);
    },

    post<T>(path: string, options?: RequestOptions): Promise<T> {
        return request<T>('POST', path, options);
    },

    put<T>(path: string, options?: RequestOptions): Promise<T> {
        return request<T>('PUT', path, options);
    },

    patch<T>(path: string, options?: RequestOptions): Promise<T> {
        return request<T>('PATCH', path, options);
    },

    delete<T = void>(path: string, options?: RequestOptions): Promise<T> {
        return request<T>('DELETE', path, options);
    },
} as const;
