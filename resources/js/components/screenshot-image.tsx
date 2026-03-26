import { useCallback, useEffect, useRef } from 'react';
import type { ImgHTMLAttributes } from 'react';
import { useAppearance } from '@/hooks/use-appearance';

type ScreenshotImageProps = Omit<ImgHTMLAttributes<HTMLImageElement>, 'src'> & {
    readonly name: string;
};

export default function ScreenshotImage({
    name,
    alt,
    className,
    ...props
}: ScreenshotImageProps) {
    const { resolvedAppearance } = useAppearance();
    const hasFallbackAttempted = useRef(false);

    useEffect(() => {
        hasFallbackAttempted.current = false;
    }, [name]);

    const src =
        resolvedAppearance === 'light'
            ? `/screenshots/${name}-light.png`
            : `/screenshots/${name}.png`;

    const handleError = useCallback(
        (e: React.SyntheticEvent<HTMLImageElement>) => {
            if (!hasFallbackAttempted.current) {
                hasFallbackAttempted.current = true;
                e.currentTarget.src = `/screenshots/${name}.png`;
            }
        },
        [name],
    );

    return (
        <img
            src={src}
            alt={alt}
            className={className}
            onError={handleError}
            {...props}
        />
    );
}
