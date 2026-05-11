<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class DebugMedia extends Command
{
    protected $signature = 'debug:media';
    protected $description = 'Debug media URLs and storage';

    public function handle(): void
    {
        $count = Media::count();
        $this->info("Total media: {$count}");

        foreach (Media::take(5)->get() as $m) {
            $this->line('---');
            $this->line("Collection: {$m->collection_name}");
            $this->line("Disk: {$m->disk}");
            $this->line("File: {$m->file_name}");
            try {
                $url  = $m->getUrl();
                $path = $m->getPath();
                $exists = file_exists($path) ? 'YES' : 'NO';
                $this->line("URL: {$url}");
                $this->line("Path: {$path}");
                $this->line("Exists: {$exists}");
            } catch (\Throwable $e) {
                $this->error("URL Error: " . $e->getMessage());
            }
        }

        $this->line('---');
        $this->info('APP_URL: ' . config('app.url'));
        $this->info('MEDIA_DISK: ' . config('media-library.disk_name'));
        $symlink = public_path('storage');
        $this->info('Symlink exists: ' . (is_link($symlink) ? 'YES' : 'NO'));
        if (is_link($symlink)) {
            $this->info('Symlink target: ' . readlink($symlink));
            $this->info('Target exists: ' . (is_dir(storage_path('app/public')) ? 'YES' : 'NO'));
        }

        // List files in public disk
        $pubPath = storage_path('app/public');
        if (is_dir($pubPath)) {
            $files = [];
            $rit = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($pubPath));
            foreach ($rit as $file) {
                if ($file->isFile()) {
                    $files[] = str_replace($pubPath . DIRECTORY_SEPARATOR, '', $file->getPathname());
                }
            }
            $this->info("\nFiles in storage/app/public (" . count($files) . " total):");
            foreach (array_slice($files, 0, 10) as $f) {
                $this->line("  {$f}");
            }
        }
    }
}
