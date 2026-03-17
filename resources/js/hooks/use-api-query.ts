import { useCallback, useEffect, useMemo, useRef, useState } from 'react';

import { api, ApiError } from '@/lib/api-client';

type UseApiQueryOptions = {
    params?: Record<string, unknown>;
    deps?: unknown[];
};

type UseApiQueryReturn<T> = {
    data: T | null;
    loading: boolean;
    error: ApiError | null;
    refetch: () => void;
};

export function useApiQuery<T>(
    url: string | null,
    options?: UseApiQueryOptions,
): UseApiQueryReturn<T> {
    const [data, setData] = useState<T | null>(null);
    const [loading, setLoading] = useState<boolean>(url !== null);
    const [error, setError] = useState<ApiError | null>(null);

    const fetchIdRef = useRef(0);
    const abortControllerRef = useRef<AbortController | null>(null);

    const deps = options?.deps ?? [];
    const serializedParams = JSON.stringify(options?.params ?? null);
    const stableParams = useMemo(
        () => (options?.params ?? null) as Record<string, string> | null,
        // eslint-disable-next-line react-hooks/exhaustive-deps
        [serializedParams],
    );

    const executeRequest = useCallback(() => {
        if (abortControllerRef.current) {
            abortControllerRef.current.abort();
        }

        if (url === null) {
            setData(null);
            setLoading(false);
            setError(null);

            return;
        }

        const fetchId = ++fetchIdRef.current;
        const controller = new AbortController();
        abortControllerRef.current = controller;

        setLoading(true);
        setError(null);

        api.get<T>(url, {
            params: stableParams ?? undefined,
            signal: controller.signal,
        })
            .then((result) => {
                if (fetchId !== fetchIdRef.current) {
                    return;
                }

                setData(result);
                setLoading(false);
            })
            .catch((err: unknown) => {
                if (fetchId !== fetchIdRef.current) {
                    return;
                }

                if (err instanceof DOMException && err.name === 'AbortError') {
                    return;
                }

                setError(
                    err instanceof ApiError
                        ? err
                        : new ApiError(0, null, String(err)),
                );
                setLoading(false);
            });
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [url, stableParams, ...deps]);

    useEffect(() => {
        executeRequest();

        return () => {
            if (abortControllerRef.current) {
                abortControllerRef.current.abort();
            }
        };
    }, [executeRequest]);

    const refetch = useCallback(() => {
        executeRequest();
    }, [executeRequest]);

    return { data, loading, error, refetch };
}
