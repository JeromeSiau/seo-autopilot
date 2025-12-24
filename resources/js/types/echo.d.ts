import Echo from 'laravel-echo';

declare global {
    interface Window {
        Echo: Echo;
        Pusher: typeof import('pusher-js').default;
    }
}
