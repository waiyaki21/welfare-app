<?php

namespace App\Providers;

use App\Models\ExpenseCategory;
use Native\Desktop\Contracts\ProvidesPhpIni;
use Native\Desktop\Facades\Alert;
use Native\Desktop\Facades\Notification;
use Native\Desktop\Facades\Window;

use Native\Desktop\Facades\AutoUpdater;
use Illuminate\Support\Facades\Event;

use Native\Desktop\Events\AutoUpdater\CheckingForUpdate;
use Native\Desktop\Events\AutoUpdater\UpdateAvailable;
use Native\Desktop\Events\AutoUpdater\UpdateNotAvailable;
use Native\Desktop\Events\AutoUpdater\DownloadProgress;
use Native\Desktop\Events\AutoUpdater\UpdateDownloaded;
use Native\Desktop\Events\AutoUpdater\Error as UpdaterError;

class NativeAppServiceProvider implements ProvidesPhpIni
{
    /**
     * Executed once the native application has been booted.
     * Use this method to open windows, register global shortcuts, etc.
     */
    public function boot(): void
    {
        Window::open('main')
            ->width(1360)
            ->height(750)
            ->minWidth(1280)
            ->minHeight(720)
            ->title('Welfare App')
            // ->rememberState()
            ->hideMenu()
            ->hasShadow(true)
            ->zoomFactor(0.85)
            ->frameless()
            ->showDevTools(false)
            ->webPreferences([
                'nodeIntegration' => true,
            ]);

        // ── First-run seeder ────────────────────────────────────────────────
        if (ExpenseCategory::count() === 0) {
            \Illuminate\Support\Facades\Artisan::call('db:seed', ['--class' => 'ExpenseCategorySeeder']);

            Notification::new()
                ->title('Setup Complete')
                ->message('We\'ve pre-loaded some expense categories for you!')
                ->show();
        }

        // ── Changelog: show what's new after an update ───────────────────────
        $this->showChangelogIfNew();

        // ── Auto-updater ────────────────────────────────────────────────────
        AutoUpdater::checkForUpdates();

        Event::listen(CheckingForUpdate::class, function () {
            // Optional: log or show subtle UI that a check is in progress
        });

        Event::listen(UpdateAvailable::class, function ($event) {
            $response = Alert::new()
                ->type('info')
                ->title('Update Available')
                ->detail('Would you like to download and install the latest version?')
                ->buttons(['Not Now', 'Download Update'])
                ->defaultId(1)
                ->cancelId(0)
                ->show('A new version of Welfare App is available.');

            if ($response === 1) {
                AutoUpdater::downloadUpdate();
            }
        });

        Event::listen(UpdateNotAvailable::class, function () {
            // App is already on the latest version — no action needed
        });

        Event::listen(DownloadProgress::class, function ($event) {
            // $event->percent — push to frontend via broadcast if needed
        });

        Event::listen(UpdateDownloaded::class, function () {
            $response = Alert::new()
                ->type('question')
                ->title('Update Ready to Install')
                ->detail('The app will restart to apply the update. Any unsaved work will be lost.')
                ->buttons(['Later', 'Restart & Install'])
                ->defaultId(1)
                ->cancelId(0)
                ->show('The update has been downloaded and is ready to install.');

            if ($response === 1) {
                AutoUpdater::quitAndInstall();
            }
        });

        Event::listen(UpdaterError::class, function ($event) {
            Alert::error(
                'Update Error',
                'Something went wrong while checking for updates. Please try again later.'
            );
        });
    }

    /**
     * Show a native alert with the changelog if this version has entries
     * the user hasn't acknowledged before.
     *
     * The release script writes changelog.json to public/changelog.json.
     * We track which version was last seen in storage/app/seen_version.txt
     * so the alert only shows once per new version.
     */
    protected function showChangelogIfNew(): void
    {
        $changelogPath = public_path('changelog.json');
        $seenVersionPath = storage_path('app/seen_version.txt');

        // No changelog file — nothing to show
        if (! file_exists($changelogPath)) {
            return;
        }

        $changelog = json_decode(file_get_contents($changelogPath), true);
        if (! is_array($changelog) || empty($changelog)) {
            return;
        }

        // Current running version from config
        $currentVersion = config('nativephp.version', '');

        // Which version did the user last acknowledge?
        $seenVersion = file_exists($seenVersionPath)
            ? trim(file_get_contents($seenVersionPath))
            : null;

        // Already acknowledged this version — skip
        if ($seenVersion === $currentVersion) {
            return;
        }

        // Find the changelog entry for the current version
        $entry = collect($changelog)->firstWhere('version', $currentVersion);
        if (! $entry || empty($entry['changes'])) {
            return;
        }

        // Format the changes as a numbered list for the alert detail
        $changeLines = collect($entry['changes'])
            ->map(fn($line, $i) => ($i + 1) . '. ' . $line)
            ->implode("\n");

        $detail = "Version {$currentVersion}  •  {$entry['date']}\n\n{$changeLines}";

        Alert::new()
            ->type('info')
            ->title("What's New in v{$currentVersion}")
            ->detail($detail)
            ->buttons(['Got it'])
            ->defaultId(0)
            ->show("Welfare App has been updated to v{$currentVersion}.");

        // Mark this version as seen so the alert doesn't appear again
        file_put_contents($seenVersionPath, $currentVersion);
    }

    /**
     * Return an array of php.ini directives to be set.
     */
    public function phpIni(): array
    {
        return [
            'memory_limit' => '512M',
            'max_execution_time' => '0',
        ];
    }
}
