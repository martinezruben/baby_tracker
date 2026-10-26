<?php

namespace App\Support\Backup;

use App\Enums\TargetStatus;
use App\Models\Family;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use ZipArchive;

/**
 * Respaldo y restauración de toda la instalación en un ZIP: `manifest.json`, `data/{tabla}.jsonl`
 * (una fila JSON por línea, tal como está en la base) y, si se pide, `files/` (fotos y adjuntos).
 *
 * No incluye lo que no es información de la familia (sesiones, caché, cola), los dispositivos de avisos
 * ni los PIN: un respaldo perdido no debe permitir adivinar los PIN. Al restaurar se define el PIN del
 * administrador y los demás cuidadores quedan para que el administrador les ponga uno nuevo.
 */
class BackupService
{
    public const FORMAT = 1;

    /** Tablas que no se respaldan. */
    private const EXCLUDED_TABLES = ['migrations', 'sessions', 'cache', 'cache_locks', 'jobs', 'job_batches', 'failed_jobs', 'push_subscriptions', 'notification_deliveries', 'password_reset_tokens'];

    /** Columnas que no se exportan. */
    private const EXCLUDED_COLUMNS = ['caretakers' => ['pin', 'remember_token']];

    /** Carpetas del disco privado que forman parte del respaldo completo. */
    public const FILE_DIRS = ['photos', 'appointments', 'contacts'];

    public const DIR = 'backups';

    /** @return array<int, string> tablas con sus padres primero (para insertar sin romper llaves foráneas) */
    public function tables(): array
    {
        $names = collect(Schema::getTables())->pluck('name')->reject(fn ($t) => in_array($t, self::EXCLUDED_TABLES, true))->values();
        $deps = $names->mapWithKeys(fn ($t) => [$t => collect(Schema::getForeignKeys($t))->pluck('foreign_table')->unique()->filter(fn ($p) => $p !== $t && $names->contains($p))->values()->all()]);

        $ordered = [];
        while (count($ordered) < $names->count()) {
            $before = count($ordered);
            foreach ($names as $t) {
                if (! in_array($t, $ordered, true) && collect($deps[$t])->every(fn ($p) => in_array($p, $ordered, true))) {
                    $ordered[] = $t;
                }
            }
            if (count($ordered) === $before) {
                throw new RuntimeException('Hay una dependencia circular entre tablas.');
            }
        }

        return $ordered;
    }

    /**
     * Carpeta de trabajo con permiso de escritura: `storage/app/private/tmp` o, si el servidor no deja, la temporal
     * del sistema (en contenedores `storage/` a veces pertenece a otro usuario que el que ejecuta PHP).
     */
    public function workDir(): string
    {
        foreach ([storage_path('app/private/tmp'), sys_get_temp_dir().'/kidu-backup'] as $base) {
            if ((is_dir($base) || @mkdir($base, 0775, true)) && is_writable($base)) {
                return $base;
            }
        }

        throw new RuntimeException(trans('ui.respaldos.errors.no_write', ['path' => storage_path('app/private')]));
    }

    private function ensureWritableDir(string $dir): void
    {
        if (! is_dir($dir) && ! @mkdir($dir, 0775, true) && ! is_dir($dir)) {
            throw new RuntimeException(trans('ui.respaldos.errors.no_write', ['path' => $dir]));
        }
        if (! is_writable($dir)) {
            throw new RuntimeException(trans('ui.respaldos.errors.no_write', ['path' => $dir]));
        }
    }

    /** Crea el ZIP en `$path` (ruta absoluta) y devuelve el manifiesto. */
    public function create(string $path, bool $withFiles): array
    {
        if (! class_exists(ZipArchive::class)) {
            throw new RuntimeException(trans('ui.respaldos.errors.no_zip'));
        }

        $tmp = $this->workDir().'/'.Str::uuid();
        $this->ensureWritableDir($tmp);
        $this->ensureWritableDir(dirname($path));

        try {
            $zip = new ZipArchive;
            if ($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
                throw new RuntimeException(trans('ui.respaldos.errors.no_write', ['path' => dirname($path)]));
            }

            $tables = [];
            foreach ($this->tables() as $table) {
                $file = "{$tmp}/{$table}.jsonl";
                $handle = fopen($file, 'w') ?: throw new RuntimeException(trans('ui.respaldos.errors.no_write', ['path' => $tmp]));
                $count = 0;
                $skip = array_flip(self::EXCLUDED_COLUMNS[$table] ?? []);

                DB::table($table)->orderBy(Schema::hasColumn($table, 'id') ? 'id' : Schema::getColumnListing($table)[0])->chunk(500, function ($rows) use ($handle, $skip, &$count) {
                    foreach ($rows as $row) {
                        fwrite($handle, json_encode(array_diff_key((array) $row, $skip), JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE | JSON_THROW_ON_ERROR)."\n");
                        $count++;
                    }
                });
                fclose($handle);

                $zip->addFile($file, "data/{$table}.jsonl");
                $tables[$table] = ['rows' => $count, 'sha256' => hash_file('sha256', $file)];
            }

            $files = 0;
            if ($withFiles) {
                foreach (self::FILE_DIRS as $dir) {
                    foreach (Storage::disk('local')->allFiles($dir) as $relative) {
                        $zip->addFile(Storage::disk('local')->path($relative), "files/{$relative}");
                        $files++;
                    }
                }
            }

            $manifest = [
                'format' => self::FORMAT,
                'app' => config('app.name'),
                'created_at' => now()->toIso8601String(),
                'with_files' => $withFiles,
                'files' => $files,
                'tables' => $tables,
            ];
            $zip->addFromString('manifest.json', json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

            // addFile lee al cerrar: los temporales deben seguir ahí hasta este punto.
            if (! $zip->close()) {
                throw new RuntimeException(trans('ui.respaldos.errors.no_write', ['path' => dirname($path)]).' '.trans('ui.respaldos.errors.disk_full'));
            }

            return $manifest;
        } finally {
            $this->deleteDir($tmp);
        }
    }

    /**
     * Restaura un respaldo en una instalación vacía (o, con `$wipe`, borrando antes lo que haya).
     *
     * @return array{tables: int, rows: int, files: int, reset_pin: array<int, string>}
     */
    public function restore(string $zipPath, ?string $adminPin, bool $wipe = false): array
    {
        $zip = new ZipArchive;
        if ($zip->open($zipPath) !== true) {
            throw new RuntimeException(trans('ui.respaldos.errors.not_zip'));
        }

        try {
            $manifest = json_decode((string) $zip->getFromName('manifest.json'), true);
            if (! is_array($manifest) || ($manifest['format'] ?? null) !== self::FORMAT || ! isset($manifest['tables'])) {
                throw new RuntimeException(trans('ui.respaldos.errors.not_backup'));
            }

            $this->assertSafeEntries($zip);

            if (! $wipe && Family::query()->exists()) {
                throw new RuntimeException(trans('ui.respaldos.errors.not_empty'));
            }

            // Comprobar la integridad antes de tocar nada.
            foreach ($manifest['tables'] as $table => $info) {
                $data = $zip->getFromName("data/{$table}.jsonl");
                if ($data === false || hash('sha256', $data) !== ($info['sha256'] ?? '')) {
                    throw new RuntimeException(trans('ui.respaldos.errors.corrupt', ['table' => $table]));
                }
            }

            $known = $this->tables();
            $rows = 0;
            $resetPins = [];

            Schema::disableForeignKeyConstraints();
            try {
                DB::transaction(function () use ($zip, $manifest, $known, $wipe, $adminPin, &$rows, &$resetPins) {
                    if ($wipe) {
                        foreach (array_reverse($known) as $table) {
                            DB::table($table)->delete();
                        }
                    }

                    foreach ($known as $table) {
                        if (! isset($manifest['tables'][$table])) {
                            continue;
                        }
                        $columns = array_flip(Schema::getColumnListing($table));
                        $batch = [];

                        foreach (explode("\n", trim((string) $zip->getFromName("data/{$table}.jsonl"))) as $line) {
                            if ($line === '') {
                                continue;
                            }
                            // Solo columnas que existen en esta instalación (un respaldo viejo puede traer menos o más).
                            $row = array_intersect_key(json_decode($line, true, 512, JSON_THROW_ON_ERROR), $columns);
                            // El PIN no viaja en el respaldo: valor provisional hasta `preparePins`.
                            $batch[] = $table === 'caretakers' ? $row + ['pin' => '!'] : $row;
                            $rows++;

                            if (count($batch) >= 100) {
                                $this->insert($table, $batch);
                                $batch = [];
                            }
                        }
                        $this->insert($table, $batch);
                    }

                    $resetPins = $this->preparePins($adminPin);
                    $this->cancelStaleNotices();
                });
            } finally {
                Schema::enableForeignKeyConstraints();
            }

            $files = $this->restoreFiles($zip, $wipe);

            return ['tables' => count($manifest['tables']), 'rows' => $rows, 'files' => $files, 'reset_pin' => $resetPins];
        } finally {
            $zip->close();
        }
    }

    /** @param  array<int, array<string, mixed>>  $batch */
    private function insert(string $table, array $batch): void
    {
        foreach (array_chunk($batch, 50) as $chunk) {
            // Filas con las mismas columnas juntas: no se rellenan columnas ausentes con NULL (perderían su valor por defecto).
            foreach (collect($chunk)->groupBy(fn ($r) => implode(',', array_keys($r))) as $group) {
                DB::table($table)->insert($group->all());
            }
        }
    }

    /**
     * El respaldo no trae PIN: el primer administrador recibe el nuevo y el resto un PIN aleatorio que
     * nadie conoce, para que el administrador se lo cambie en Ajustes > Cuidadores.
     *
     * @return array<int, string> nombres de quienes necesitan un PIN nuevo
     */
    private function preparePins(?string $adminPin): array
    {
        $needReset = [];
        $first = true;

        foreach (DB::table('caretakers')->orderBy('id')->get(['id', 'name', 'role']) as $caretaker) {
            $isFirstAdmin = $first && $caretaker->role === 'admin';
            $first = $first && ! $isFirstAdmin;

            if ($isFirstAdmin && $adminPin) {
                DB::table('caretakers')->where('id', $caretaker->id)->update(['pin' => Hash::make($adminPin)]);

                continue;
            }

            DB::table('caretakers')->where('id', $caretaker->id)->update(['pin' => Hash::make(Str::random(40))]);
            $needReset[] = $caretaker->name;
        }

        return $needReset;
    }

    /** Un respaldo viejo traería avisos ya vencidos: al restaurar saldrían todos de golpe. Se cancelan. */
    private function cancelStaleNotices(): void
    {
        DB::table('notification_targets')
            ->where('status', TargetStatus::Pending->value)
            ->where('next_send_at', '<', now())
            ->update(['status' => TargetStatus::Cancelled->value, 'next_send_at' => null]);
    }

    private function restoreFiles(ZipArchive $zip, bool $wipe): int
    {
        $disk = Storage::disk('local');
        if ($wipe) {
            foreach (self::FILE_DIRS as $dir) {
                $disk->deleteDirectory($dir);
            }
        }

        $count = 0;
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = $zip->getNameIndex($i);
            if (! str_starts_with($name, 'files/') || str_ends_with($name, '/')) {
                continue;
            }

            $stream = $zip->getStream($name);
            $disk->put(substr($name, 6), $stream);
            is_resource($stream) && fclose($stream);
            $count++;
        }

        return $count;
    }

    /** Solo se aceptan `manifest.json`, `data/*.jsonl` y `files/{photos|appointments}/…` sin rutas raras (zip-slip). */
    private function assertSafeEntries(ZipArchive $zip): void
    {
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = $zip->getNameIndex($i);
            $ok = $name === 'manifest.json'
                || preg_match('#^data/[a-z_]+\.jsonl$#', $name)
                || (preg_match('#^files/('.implode('|', self::FILE_DIRS).')/#', $name) && ! str_contains($name, '..') && ! str_contains($name, '\\') && ! str_contains($name, "\0"));

            if (! $ok) {
                throw new RuntimeException(trans('ui.respaldos.errors.unsafe'));
            }
        }
    }

    /** @return array<int, array{name: string, size: int, at: int, files: bool}> respaldos guardados en el servidor, el más nuevo primero */
    public function stored(): array
    {
        return collect(Storage::disk('local')->files(self::DIR))
            ->filter(fn ($f) => preg_match('/^kidu-\d{8}-\d{6}(-files)?\.zip$/', basename($f)))
            ->map(fn ($f) => ['name' => basename($f), 'size' => Storage::disk('local')->size($f), 'at' => Storage::disk('local')->lastModified($f), 'files' => str_ends_with($f, '-files.zip')])
            ->sortByDesc('name')->values()->all();
    }

    public function path(string $name): string
    {
        return Storage::disk('local')->path(self::DIR.'/'.$name);
    }

    public static function isValidName(string $name): bool
    {
        return (bool) preg_match('/^kidu-\d{8}-\d{6}(-files)?\.zip$/', $name);
    }

    public function newName(bool $withFiles): string
    {
        return 'kidu-'.now()->format('Ymd-His').($withFiles ? '-files' : '').'.zip';
    }

    /** Conserva solo los `$keep` respaldos más recientes. */
    public function prune(int $keep): int
    {
        $old = array_slice($this->stored(), max(1, $keep));
        foreach ($old as $backup) {
            Storage::disk('local')->delete(self::DIR.'/'.$backup['name']);
        }

        return count($old);
    }

    private function deleteDir(string $dir): void
    {
        if (! is_dir($dir)) {
            return;
        }
        foreach (glob("{$dir}/*") ?: [] as $f) {
            @unlink($f);
        }
        @rmdir($dir);
    }
}
