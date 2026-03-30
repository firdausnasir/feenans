export function resolveDeferredArray<T>(items: T[] | undefined): T[] {
    return items ?? [];
}
