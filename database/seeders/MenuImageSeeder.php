<?php

namespace Database\Seeders;

use App\Models\MenuItem;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class MenuImageSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        foreach (MenuItem::all() as $item) {
            $filename = ($item->slug === 'satay-skewers' ? 'satay' : str_replace('-', '', $item->slug)).'.jpg';
            $source = public_path('images/'.$filename);
            $path = 'menu/'.$filename;

            if (! File::isFile($source) || ($item->image_path !== null && $item->image_path !== $path)) {
                continue;
            }

            if (! Storage::disk('public')->put($path, File::get($source))) {
                throw new RuntimeException('Unable to store menu image: '.$filename);
            }

            $item->update(['image_path' => $path]);
        }
    }
}
