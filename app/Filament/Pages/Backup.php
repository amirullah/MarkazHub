<?php

namespace App\Filament\Pages;

use App\Services\BackupService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Symfony\Component\HttpFoundation\StreamedResponse;

class Backup extends Page
{
    protected string $view = 'filament.pages.backup';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCircleStack;

    protected static ?string $navigationLabel = 'Backup';

    protected static ?string $title = 'Backup & Pemulihan Data';

    protected static ?int $navigationSort = 7;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('download')
                ->label('Unduh Backup (.sql)')
                ->icon(Heroicon::OutlinedArrowDownTray)
                ->color('primary')
                ->action(fn (): StreamedResponse => $this->downloadBackup()),

            Action::make('restore')
                ->label('Pulihkan dari Backup')
                ->icon(Heroicon::OutlinedArrowUpTray)
                ->color('gray')
                ->modalHeading('Pulihkan data dari file backup')
                ->modalDescription('PERHATIAN: data Anda saat ini (toko, produk, pesanan) akan DIGANTI dengan isi file backup. Pastikan file benar.')
                ->modalSubmitActionLabel('Ganti & pulihkan sekarang')
                ->schema([
                    FileUpload::make('file')
                        ->label('File backup MarkazHub (.sql)')
                        ->storeFiles(false)
                        ->required()
                        ->helperText('Hanya file .sql yang diunduh dari menu ini.')
                        ->rules([
                            function (string $attribute, $value, \Closure $fail): void {
                                $f = is_array($value) ? ($value[0] ?? null) : $value;
                                if ($f instanceof \Illuminate\Http\UploadedFile
                                    && strtolower($f->getClientOriginalExtension()) !== 'sql') {
                                    $fail('File harus berformat .sql (hasil backup MarkazHub).');
                                }
                            },
                        ]),
                    TextInput::make('konfirmasi')
                        ->label('Ketik PULIHKAN untuk melanjutkan')
                        ->required()
                        ->rule('in:PULIHKAN')
                        ->validationMessages(['in' => 'Ketik persis: PULIHKAN']),
                ])
                ->action(fn (array $data) => $this->runRestore($data)),
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

    protected function runRestore(array $data): void
    {
        $upload = $data['file'] ?? null;
        $file = is_array($upload) ? ($upload[0] ?? null) : $upload;

        if (! $file instanceof \Illuminate\Http\UploadedFile) {
            Notification::make()->title('File tidak ditemukan')->danger()->send();
            return;
        }

        try {
            $result = app(BackupService::class)->restoreFromSql(
                (int) auth()->user()->organization_id,
                (string) file_get_contents($file->getRealPath()),
            );
            Notification::make()
                ->title('Data berhasil dipulihkan')
                ->body("{$result['orders']} pesanan aktif setelah pemulihan.")
                ->success()
                ->send();
        } catch (\Throwable $e) {
            Notification::make()
                ->title('Pemulihan gagal')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }
}
