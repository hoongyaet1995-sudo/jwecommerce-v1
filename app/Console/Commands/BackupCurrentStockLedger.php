<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Symfony\Component\Process\Process;

final class BackupCurrentStockLedger extends Command
{
    protected $signature = 'inventory:backup-current-stock {--database= : Exact database name} {--production : Allow the approved production cutover while maintenance mode is active}';

    protected $description = 'Create a local full MySQL dump before a Current Stock ledger reset';

    public function handle(): int
    {
        $production = app()->environment('production') && $this->option('production')
            && app()->isDownForMaintenance()
            && parse_url((string) config('app.url'), PHP_URL_HOST) === 'ecom.jwaistudio.online'
            && str_replace('\\', '/', base_path()) === '/home/u762348670/apps/jwecommerce';
        if (! (app()->environment('local') || $production) || DB::connection()->getDriverName() !== 'mysql'
            || DB::connection()->getDatabaseName() !== $this->option('database')) {
            $this->error('Backup requires the exact MySQL database and an approved environment.');
            return self::FAILURE;
        }

        $connection = config('database.connections.mysql');
        $database = DB::connection()->getDatabaseName();
        $binary = PHP_OS_FAMILY === 'Windows' ? 'C:\\xampp\\mysql\\bin\\mysqldump.exe' : 'mysqldump';
        $directory = storage_path('app/private/current-stock-reset');
        if (! is_dir($directory) && ! mkdir($directory, 0700, true)) {
            $this->error('Could not create the private backup directory.');
            return self::FAILURE;
        }
        $path = $directory.DIRECTORY_SEPARATOR.$database.'-'.now()->format('Ymd-His').'.sql';
        $optionFile = tempnam($directory, 'mysql-');
        $stream = false;
        try {
            if (! $optionFile) {
                throw new RuntimeException('Could not prepare the temporary MySQL client options.');
            }
            $escape = static fn (string $value): string => str_replace(['\\', '"', "\n", "\r"], ['\\\\', '\\"', '\\n', '\\r'], $value);
            file_put_contents($optionFile, "[client]\n".
                'host="'.$escape((string) ($connection['host'] ?? '127.0.0.1'))."\"\n".
                'port="'.$escape((string) ($connection['port'] ?? '3306'))."\"\n".
                'user="'.$escape((string) ($connection['username'] ?? ''))."\"\n".
                'password="'.$escape((string) ($connection['password'] ?? ''))."\"\n");
            @chmod($optionFile, 0600);
            $stream = fopen($path, 'wb');
            if (! $stream) {
                throw new RuntimeException('Could not open the backup output file.');
            }
            $errors = '';
            $process = new Process([$binary, '--defaults-extra-file='.$optionFile,
                '--single-transaction', '--quick', '--routines', '--triggers', '--databases', $database]);
            $process->setTimeout(300);
            $process->run(static function (string $type, string $data) use ($stream, &$errors): void {
                if ($type === Process::OUT) {
                    fwrite($stream, $data);
                } else {
                    $errors .= $data;
                }
            });
            fclose($stream);
            $stream = false;
            if (! $process->isSuccessful() || filesize($path) < 1024) {
                throw new RuntimeException('mysqldump failed: '.trim($errors));
            }
            $this->line(json_encode(['database' => $database, 'backup' => $path,
                'sha256' => hash_file('sha256', $path), 'bytes' => filesize($path)], JSON_PRETTY_PRINT));
            return self::SUCCESS;
        } catch (\Throwable $error) {
            if (is_file($path)) {
                unlink($path);
            }
            $this->error($error->getMessage());
            return self::FAILURE;
        } finally {
            if ($stream) {
                fclose($stream);
            }
            if ($optionFile && is_file($optionFile)) {
                unlink($optionFile);
            }
        }
    }
}
