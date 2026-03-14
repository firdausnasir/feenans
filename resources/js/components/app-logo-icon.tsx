import type { SVGAttributes } from 'react';

export default function AppLogoIcon(props: SVGAttributes<SVGElement>) {
    return (
        <svg {...props} viewBox="0 0 32 32" xmlns="http://www.w3.org/2000/svg">
            <path d="M8 6h16v4H12v4h10v4H12v10H8V6z" fill="currentColor" />
        </svg>
    );
}
