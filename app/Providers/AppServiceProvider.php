<?php

namespace App\Providers;

use Filament\Actions\Action;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // Modal tidak tertutup saat klik area luar — cegah kebingungan/aksi
        // batal tak sengaja (mis. saat import berjalan). Berlaku global.
        Action::configureUsing(function (Action $action): void {
            $action->closeModalByClickingAway(false);
        });
    }
}
