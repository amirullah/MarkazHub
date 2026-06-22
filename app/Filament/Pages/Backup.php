<?php

namespace App\Filament\Pages;

use App\Services\BackupService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Symfony\Component\HttpFoundation\StreamedResponse;

class Backup extends Page
{
    protected string $view = 'filament.pages.backup';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCircleStack;

    protected static ?string $navigationLabel = 'Backup';

    protected static ?string $title = 'Backup Data';

    protected static ?int $navigationSort = 7;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('download')
                ->label('Unduh Backup Data (.sql)')
                ->icon(Heroicon::OutlinedArrowDownTray)
                ->color('primary')
                ->action(fn (): StreamedResponse => $this->downloadBackup()),
        ];
    }

    protected function downloadBackup(): StreamedResponse
    {
        $orgId = (int) auth()->user()->organization_id;
        $sql = app(BackupService::class)->sqlForOrg($orgId);
        $name = 'markazhub-backup-' . now()->format('Ymd-His') . '.sql';

        return response()->streamDownload(function () use ($sql) {
            echo $sql;
        }, $name, ['Content-Type' => 'application/sql']);
    }
}
