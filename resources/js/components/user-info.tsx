import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { useInitials } from '@/hooks/use-initials';
import type { User } from '@/types';

export function UserInfo({
    user,
    showEmail = false,
    caption,
}: {
    user: User;
    showEmail?: boolean;
    /** A second line instead of the email, e.g. the role in the sidebar. */
    caption?: string;
}) {
    const getInitials = useInitials();

    return (
        <>
            <Avatar className="h-8 w-8 overflow-hidden rounded-full">
                <AvatarImage src={user.avatar} alt={user.name} />
                <AvatarFallback className="rounded-full bg-sky-100 text-xs font-semibold text-sky-800">
                    {getInitials(user.name)}
                </AvatarFallback>
            </Avatar>
            <div className="grid flex-1 text-left text-sm leading-tight">
                <span className="truncate font-medium">{user.name}</span>
                {showEmail ? (
                    <span className="truncate text-xs text-muted-foreground">
                        {user.email}
                    </span>
                ) : (
                    caption && (
                        <span className="truncate text-xs opacity-75">
                            {caption}
                        </span>
                    )
                )}
            </div>
        </>
    );
}
