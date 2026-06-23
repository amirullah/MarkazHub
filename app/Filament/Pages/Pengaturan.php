<?php

namespace App\Filament\Pages;

use App\Models\Organization;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;

class Pengaturan extends Page
{
    protected string $view = 'filament.pages.pengaturan';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCog6Tooth;

    protected static ?string $navigationLabel = 'Pengaturan';

    protected static ?string $title = 'Pengaturan Organisasi';

    protected static ?int $navigationSort = 8;

    public function getViewData(): array
    {
        return [
            'org' => Organization::find(auth()->user()->organization_id),
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('ubah')
                ->label('Ubah Pengaturan')
                ->icon(Heroicon::OutlinedPencilSquare)
                ->fillForm(fn (): array => [
                    'uses_jakmall' => (bool) (Organization::find(auth()->user()->organization_id)?->uses_jakmall ?? true),
                ])
                ->schema([
                    Toggle::make('uses_jakmall')
                        ->label('Saya menggunakan Jakmall (dropship)')
                        ->helperText('Aktif: fitur dropship Jakmall tampil (import master/laporan Jakmall, kolom & biaya dropship). Nonaktif: semua tampilan dropship disembunyikan dan file Jakmall dilewati saat import.'),
                ])
                ->action(function (array $data): void {
                    $org = Organization::find(auth()->user()->organization_id);
                    $org->uses_jakmall = (bool) ($data['uses_jakmall'] ?? false);
                    $org->save();

                    Notification::make()
                        ->title('Pengaturan disimpan')
                        ->body('Muat ulang halaman lain agar perubahan tampilan diterapkan.')
                        ->success()
                        ->send();
                }),
        ];
    }
}
