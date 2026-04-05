<?php

namespace App\Listeners;

use App\Http\Controllers\UpdateController;
use Native\Laravel\Events\Updater\UpdateAvailable;

class HandleUpdateAvailable
{
    public function handle(UpdateAvailable $event): void
    {
        $version = $event->version ?? '';
        UpdateController::notifyUpdateAvailable($version);
    }
}
