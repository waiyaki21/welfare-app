<?php

namespace App\Listeners;

use App\Http\Controllers\UpdateController;
use Native\Laravel\Events\Updater\UpdateDownloaded;

class HandleUpdateDownloaded
{
    public function handle(UpdateDownloaded $event): void
    {
        $version = $event->version ?? '';
        UpdateController::notifyUpdateReady($version);
    }
}
