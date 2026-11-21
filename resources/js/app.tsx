import { createInertiaApp } from '@inertiajs/react';
import { configureEcho } from '@laravel/echo-react';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import type { ChannelAuthorizationCallback } from 'pusher-js';
import { authenticate } from '@/actions/Illuminate/Broadcasting/BroadcastController';
import { Toaster } from '@/components/ui/sonner';
import { TooltipProvider } from '@/components/ui/tooltip';
import { initializeTheme } from '@/hooks/use-appearance';
import AppLayout from '@/layouts/app-layout';
import AuthLayout from '@/layouts/auth-layout';
import SettingsLayout from '@/layouts/settings/layout';
import { http } from '@/lib/utilities';

// Reverb (Laravel's own WebSocket server; it speaks the Pusher protocol, so the
// client library is pusher-js, but nothing goes to the Pusher service).
// The VITE_REVERB_* values are passed explicitly: echo-react reads them inside
// node_modules, where Vite's pre-bundling leaves them undefined.
// The socket only opens once a page subscribes (useEcho), so guests never connect.
// Private channels are authorised through axios so the request carries the
// current XSRF cookie (a csrf meta tag would go stale after an Inertia login).
const reverbPort = Number(import.meta.env.VITE_REVERB_PORT ?? 8080);

configureEcho({
    broadcaster: 'reverb',
    key: import.meta.env.VITE_REVERB_APP_KEY,
    wsHost: import.meta.env.VITE_REVERB_HOST ?? window.location.hostname,
    wsPort: reverbPort,
    wssPort: reverbPort,
    forceTLS: (import.meta.env.VITE_REVERB_SCHEME ?? 'https') === 'https',
    enabledTransports: ['ws', 'wss'],
    authorizer: (channel: { name: string }) => ({
        authorize: (
            socketId: string,
            callback: ChannelAuthorizationCallback,
        ) => {
            http.post<ChannelAuthorizationData>(authenticate.url(), {
                socket_id: socketId,
                channel_name: channel.name,
            })
                .then((response) => callback(null, response.data))
                .catch((error: Error) => callback(error, null));
        },
    }),
});

type ChannelAuthorizationData = Parameters<ChannelAuthorizationCallback>[1] &
    object;

const queryClient = new QueryClient({
    defaultOptions: {
        queries: {
            staleTime: 30_000,
            retry: 1,
        },
    },
});

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

void createInertiaApp({
    title: (title) => (title ? `${title} - ${appName}` : appName),
    layout: (name) => {
        switch (true) {
            case name.startsWith('public/'):
                return null;
            case name.startsWith('auth/'):
                return AuthLayout;
            case name.startsWith('settings/'):
            case name.startsWith('encoder/settings/'):
                return [AppLayout, SettingsLayout];
            default:
                return AppLayout;
        }
    },
    strictMode: true,
    withApp(app) {
        return (
            <QueryClientProvider client={queryClient}>
                <TooltipProvider delayDuration={0}>
                    {app}
                    <Toaster />
                </TooltipProvider>
            </QueryClientProvider>
        );
    },
    progress: {
        color: '#0284c7',
    },
});

// This will set light / dark mode on load...
initializeTheme();
