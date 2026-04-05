<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class UpdateController extends Controller
{
    /**
     * Trigger an update check.
     *
     * NativePHP's Updater facade wraps Electron's autoUpdater.
     * - checkForUpdates() tells Electron to hit the update server.
     * - NativePHP then fires UpdateAvailable / UpdateNotAvailable events
     *   which you can listen to in EventServiceProvider.
     *
     * Because the check is async we do two things here:
     *  1. Fire the check via the Updater facade.
     *  2. Return a JSON response so the sidebar button can show feedback.
     *
     * The native notifications for "update available / not available" are
     * sent from the event listeners in App\Listeners (see below), but we
     * also send an immediate "Checking…" notification so the user knows
     * something is happening.
     */
    public function check(Request $request)
    {
        try {
            // NativePHP Updater facade — wraps autoUpdater.checkForUpdates()
            if (class_exists(\Native\Laravel\Facades\Updater::class)) {
                \Native\Laravel\Facades\Updater::checkForUpdates();
            }

            // Immediate native feedback: "checking now"
            $this->notify(
                'Checking for Updates…',
                'Looking for a new version of ' . config('app.name') . '. You\'ll be notified shortly.'
            );

            return response()->json([
                'status'  => 'checking',
                'message' => 'Update check started.',
            ]);

        } catch (\Throwable $e) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Could not check for updates: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Called by NativePHP when Electron reports no update is available.
     * Wire this up in your EventServiceProvider listener for
     * Native\Laravel\Events\Updater\UpdateNotAvailable.
     */
    public static function notifyNoUpdate(): void
    {
        static::sendNotification(
            'You\'re up to date!',
            config('app.name') . ' ' . config('app.version') . ' is the latest version.'
        );
    }

    /**
     * Called by NativePHP when Electron reports an update is available.
     * Wire this up in your EventServiceProvider listener for
     * Native\Laravel\Events\Updater\UpdateAvailable.
     */
    public static function notifyUpdateAvailable(string $version = ''): void
    {
        $versionText = $version ? " (v{$version})" : '';
        static::sendNotification(
            'Update Available' . $versionText,
            'A new version of ' . config('app.name') . ' is ready. It will download in the background and install on next launch.'
        );
    }

    /**
     * Called when the update has been fully downloaded and is ready to install.
     * Wire this up for Native\Laravel\Events\Updater\UpdateDownloaded.
     */
    public static function notifyUpdateReady(string $version = ''): void
    {
        $versionText = $version ? " v{$version}" : '';
        static::sendNotification(
            'Ready to Install' . $versionText,
            'The update has downloaded. Restart ' . config('app.name') . ' to apply it.'
        );
    }

    // ── private ───────────────────────────────────────────────────────────────

    private function notify(string $title, string $body): void
    {
        static::sendNotification($title, $body);
    }

    private static function sendNotification(string $title, string $body): void
    {
        if (!class_exists(\Native\Laravel\Facades\Notification::class)) {
            return;
        }
        try {
            \Native\Laravel\Facades\Notification::title($title)
                ->message($body)
                ->send();
        } catch (\Throwable) {
            // Silently ignore — notification failure should never break the app.
        }
    }
}
