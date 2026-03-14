import type { Tag } from '@/types';

export function TagPill({ tag }: { tag: Tag }) {
    return (
        <span
            className="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium"
            style={tag.color ? { backgroundColor: tag.color + '20', color: tag.color } : undefined}
        >
            {tag.name}
        </span>
    );
}
