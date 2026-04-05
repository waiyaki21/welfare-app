<?php

namespace App\Http\Controllers;

use Native\Desktop\Facades\Window;
// use Illuminate\Http\Request;

class NativeWindowController extends Controller
{
    public function control($action)
    {
        $window = Window::current();
        $id = $window ? $window->id : 'app';

        switch ($action) {
            case 'minimize':
                Window::minimize($id);
                break;

            case 'maximize':
                Window::maximize($id);
                break;

            case 'unmaximize':
                Window::resize(1360, 720, $id);
                break;

            case 'close':
                Window::close($id);
                break;
        }

        return response()->json([
            'status' => 'ok',
            'action' => $action,
            'window' => $id,
        ]);
    }

    // 🔥 Window switching (IMPORTANT)

    public function openApp()
    {
        Window::open('app')
            ->route('/')
            ->width(1360)
            ->height(750);

        Window::close('auth');

        return response()->noContent();
    }

    public function openAuth()
    {
        Window::open('auth')
            ->route('/login')
            ->width(420)
            ->height(600)
            ->resizable(false);

        Window::close('app');

        return response()->noContent();
    }
}
