import { Link, usePage } from '@inertiajs/react';
import { Crown } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import {
    SidebarGroup,
    SidebarGroupLabel,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { useCurrentUrl } from '@/hooks/use-current-url';
import type { NavGroup } from '@/types';
import { premium } from '@/routes';

export function NavMain({ groups = [] }: { groups: NavGroup[] }) {
    const { isCurrentUrl, isCurrentOrParentUrl } = useCurrentUrl();
    const { auth } = usePage().props;
    const isPremiumUser = auth.user?.membership.is_premium ?? false;

    return (
        <>
            {groups.map((group) => (
                <SidebarGroup
                    key={group.label}
                    className="mt-4 px-2 py-0 first:mt-0"
                >
                    <SidebarGroupLabel>{group.label}</SidebarGroupLabel>
                    <SidebarMenu>
                        {group.items.map((item) => {
                            const needsUpgrade =
                                item.isPremium && !isPremiumUser;
                            const href = needsUpgrade
                                ? premium.url()
                                : item.href;

                            return (
                                <SidebarMenuItem key={item.title}>
                                    <SidebarMenuButton
                                        asChild
                                        isActive={
                                            needsUpgrade
                                                ? false
                                                : item.title === 'Dashboard'
                                                  ? isCurrentUrl(item.href)
                                                  : isCurrentOrParentUrl(
                                                        item.href,
                                                    )
                                        }
                                        tooltip={{
                                            children: needsUpgrade
                                                ? `${item.title} (Premium)`
                                                : item.title,
                                        }}
                                    >
                                        <Link
                                            href={href}
                                            prefetch={!needsUpgrade}
                                        >
                                            {item.icon && <item.icon />}
                                            <span>{item.title}</span>
                                            {needsUpgrade && (
                                                <Badge
                                                    variant="secondary"
                                                    className="ml-auto gap-1 text-[10px] leading-none"
                                                >
                                                    <Crown className="size-2.5" />
                                                    Premium
                                                </Badge>
                                            )}
                                        </Link>
                                    </SidebarMenuButton>
                                </SidebarMenuItem>
                            );
                        })}
                    </SidebarMenu>
                </SidebarGroup>
            ))}
        </>
    );
}
