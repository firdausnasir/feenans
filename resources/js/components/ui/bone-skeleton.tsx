import type { ReactNode } from 'react';

type BoneSkeletonProps = {
    loading: boolean;
    fallback: ReactNode;
    children: ReactNode;
    name?: string;
};

export function BoneSkeleton({
    loading,
    fallback,
    children,
}: BoneSkeletonProps) {
    if (loading) {
        return <>{fallback}</>;
    }

    return <>{children}</>;
}
