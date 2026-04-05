<?php

namespace App\Listeners;

use App\Http\Controllers\UpdateController;
use Native\Laravel\Events\Updater\UpdateNotAvailable;

class HandleUpdateNotAvailable
{
    public function handle(UpdateNotAvailable $event): void
    {
        UpdateController::notifyNoUpdate();
    }
}
