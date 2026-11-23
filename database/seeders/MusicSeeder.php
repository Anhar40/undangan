<?php

namespace Database\Seeders;

use App\Models\Music;
use Illuminate\Database\Seeder;

class MusicSeeder extends Seeder
{
    public function run(): void
    {
        // Super Admin must confirm licensing before offering them to customers.
        foreach ([
            [
                'title' => 'Melapas',
                'artist' => 'Arvian Dwi ft. Tri Suaka',
                'file_path' => 'https://bukaundangan.com/wp-content/uploads/2024/07/arvian-dwi-ft-tri-suaka-melepas-1.mp3',
            ],
            [
                'title' => 'Instrumental Wedding',
                'artist' => 'Sumber template tema1',
                'file_path' => 'https://api.sewaundangan.com/music/music-1755739436735-110611525.mp3',
            ],
            [
                'title' => 'Instrumental Wedding (Lokal)',
                'artist' => 'Tema 1',
                'file_path' => 'musics/instrumental-wedding-tema1.mp3',
            ],
            [
                'title' => 'Akad',
                'artist' => 'Payung Teduh',
                'file_path' => 'musics/payung-teduh-akad.mp3',
            ],
            [
                'title' => 'Beautiful in White (Instrumental)',
                'artist' => 'Piano Cover',
                'file_path' => 'https://bukaundangan.com/wp-content/uploads/2024/07/beautiful-in-white-instrumental.mp3',
            ],
            [
                'title' => 'A Thousand Years (Instrumental)',
                'artist' => 'Piano Cover',
                'file_path' => 'https://bukaundangan.com/wp-content/uploads/2024/07/a-thousand-years-instrumental.mp3',
            ],
            [
                'title' => 'Perfect (Instrumental)',
                'artist' => 'Piano Cover',
                'file_path' => 'https://bukaundangan.com/wp-content/uploads/2024/07/perfect-instrumental.mp3',
            ],
            [
                'title' => 'Canon in D',
                'artist' => 'Pachelbel',
                'file_path' => 'https://bukaundangan.com/wp-content/uploads/2024/07/canon-in-d.mp3',
            ],
        ] as $music) {
            Music::firstOrCreate(['file_path' => $music['file_path']], $music + ['status' => 'active']);
        }
    }
}
