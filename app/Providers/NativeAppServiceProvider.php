<?php

namespace App\Providers;

use App\Models\ExpenseCategory;
use Native\Desktop\Contracts\ProvidesPhpIni;
// use Native\Desktop\Facades\Menu;
// use Native\Desktop\Facades\MenuBar;
use Native\Desktop\Facades\Window;
use Native\Desktop\Notification;

class NativeAppServiceProvider implements ProvidesPhpIni
{
    /**
     * Executed once the native application has been booted.
     * Use this method to open windows, register global shortcuts, etc.
     */
    public function boot(): void
    {
        // 1. Setup the MenuBar / System Tray
        // MenuBar::create()
        //     ->icon(public_path('IconTemplate.png')) // Use your 22x22px icon
        //     ->withContextMenu(
        //         Menu::new()
        //             ->label('My App v1.0')
        //             ->separator()
        //             ->link('https://your-site.com', 'Visit Website')
        //             ->separator()
        //             ->quit()
        //     );

        Window::open()
            ->width(1920)
            ->height(1080)
            ->minWidth(1280)
            ->minHeight(720)
            ->title('Welfare App')
            ->rememberState() // Restores previous window position
            ->maximized(true)
            ->showDevTools(false);

        if (ExpenseCategory::count() === 0) {
            // This is a good place to trigger a one-time setup or seeder
            \Illuminate\Support\Facades\Artisan::call('db:seed', ['--class' => 'ExpenseCategorySeeder']);

            Notification::new()
                ->title('Setup Complete')
                ->message('We’ve pre-loaded some expense categories for you!')
                ->show();
        }
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
