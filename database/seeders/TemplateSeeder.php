<?php

namespace Database\Seeders;

use App\Models\Template;
use Illuminate\Database\Seeder;

class TemplateSeeder extends Seeder
{
    public function run(): void
    {
        $templates = [
            [
                'name' => 'Thema 38',
                'slug' => 'tema1',
                'description' => 'Tema undangan klasik elegan dengan galeri, cerita pasangan, amplop digital, dan RSVP.',
                'category' => 'Klasik',
                'template_path' => 'template/tema1.html',
                'thumbnail' => null,
                'price' => (int) config('invitation.default_price', 150000),
                'version' => '1.0',
                'status' => 'active',
                'features' => [
                    'story' => true,
                    'gallery' => true,
                    'music' => true,
                    'theme' => true,
                    'rsvp' => true,
                    'families' => true,
                    'gift' => true,
                    'live_stream' => true,
                    'countdown' => true,
                    'quote' => true,
                    'multiple_events' => true,
                ],
                'config' => [
                    'renderer' => 'tema1',
                    'save_the_date_format' => 'd.m.Y',
                    'countdown_selector' => '.elementor-countdown-wrapper',
                    'countdown_format' => 'timestamp',
                    'guest_label' => 'Kepada yth:',
                    'backgrounds' => [
                        'cover' => ['id' => '3fe3ed95', 'label' => 'Foto sampul', 'multiple' => true],
                        'groom' => ['id' => '5c22588c', 'label' => 'Latar mempelai pria', 'multiple' => false],
                        'bride' => ['id' => '5d7ac283', 'label' => 'Latar mempelai wanita', 'multiple' => false],
                        'closing' => ['id' => '27998b08', 'label' => 'Latar penutup', 'multiple' => true],
                    ],
                ],
            ],
            [
                'name' => 'Thema 40',
                'slug' => 'tema2',
                'description' => 'Tema undangan modern dengan save the date, galeri, love story, wedding gift, dan wedding wish.',
                'category' => 'Modern',
                'template_path' => 'template/tema2.html',
                'thumbnail' => null,
                'price' => (int) config('invitation.default_price', 150000),
                'version' => '1.0',
                'status' => 'active',
                'features' => [
                    'story' => true,
                    'gallery' => true,
                    'music' => true,
                    'theme' => true,
                    'rsvp' => true,
                    'families' => true,
                    'gift' => true,
                    'live_stream' => false,
                    'countdown' => true,
                    'quote' => true,
                    'multiple_events' => true,
                ],
                'config' => [
                    'renderer' => 'tema2',
                    'save_the_date_format' => 'd.m.Y',
                    'countdown_selector' => '.wpkoi-elements-countdown-items',
                    'countdown_format' => 'text',
                    'guest_label' => 'DEAR TO',
                    'backgrounds' => [
                        'cover' => ['id' => '56efded2', 'label' => 'Foto sampul', 'multiple' => true],
                        'groom' => ['id' => '1a8ab2c4', 'label' => 'Latar mempelai pria', 'multiple' => false],
                        'bride' => ['id' => '76dba0dd', 'label' => 'Latar mempelai wanita', 'multiple' => false],
                        'closing' => ['id' => 'a09598', 'label' => 'Latar penutup', 'multiple' => true],
                    ],
                ],
            ],
            [
                'name' => 'Thema 39',
                'slug' => 'tema3',
                'description' => 'Tema undangan editorial dengan sampul bergambar, hitung mundur, detail acara, cerita cinta, galeri, amplop digital, dan buku ucapan.',
                'category' => 'Editorial',
                'template_path' => 'template/tema3.html',
                'thumbnail' => null,
                'price' => (int) config('invitation.default_price', 150000),
                'version' => '1.0',
                'status' => 'active',
                'features' => [
                    'story' => true,
                    'gallery' => true,
                    'music' => true,
                    'theme' => true,
                    'rsvp' => true,
                    'families' => true,
                    'gift' => true,
                    'live_stream' => false,
                    'countdown' => true,
                    'quote' => true,
                    'multiple_events' => true,
                ],
                'config' => [
                    'renderer' => 'tema3',
                    'save_the_date_format' => 'd.m.Y',
                    'countdown_selector' => '.wpkoi-elements-countdown-items',
                    'countdown_format' => 'text',
                    'guest_label' => 'DEAR TO',
                    'backgrounds' => [
                        'cover' => ['id' => '6f09d350', 'label' => 'Foto sampul', 'multiple' => true],
                        'groom' => ['id' => '7ffa566e', 'label' => 'Latar mempelai pria', 'multiple' => false],
                        'bride' => ['id' => '171b4b90', 'label' => 'Latar mempelai wanita', 'multiple' => false],
                        'closing' => ['id' => '58ed48e', 'label' => 'Latar penutup', 'multiple' => true],
                    ],
                ],
            ],
            [
                'name' => 'Thema 42',
                'slug' => 'tema4',
                'description' => 'Tema undangan modern dengan tanggal kreatif, hitung mundur, galeri, cerita cinta, amplop digital, dan buku ucapan.',
                'category' => 'Modern',
                'template_path' => 'template/tema4.html',
                'thumbnail' => null,
                'price' => (int) config('invitation.default_price', 150000),
                'version' => '1.0',
                'status' => 'active',
                'features' => [
                    'story' => true,
                    'gallery' => true,
                    'music' => true,
                    'theme' => true,
                    'rsvp' => true,
                    'families' => true,
                    'gift' => true,
                    'live_stream' => false,
                    'countdown' => true,
                    'quote' => true,
                    'multiple_events' => true,
                ],
                'config' => [
                    'renderer' => 'tema4',
                    'save_the_date_format' => 'd.m.Y',
                    'countdown_selector' => '.wpkoi-elements-countdown-items',
                    'countdown_format' => 'text',
                    'guest_label' => 'DEAR TO',
                    'backgrounds' => [
                        'cover' => ['id' => '4e4391bb', 'label' => 'Foto sampul', 'multiple' => true],
                        'groom' => ['id' => '5e892ecd', 'label' => 'Latar mempelai pria', 'multiple' => false],
                        'bride' => ['id' => '5204381e', 'label' => 'Latar mempelai wanita', 'multiple' => false],
                        'closing' => ['id' => '5c3c2729', 'label' => 'Latar penutup', 'multiple' => true],
                    ],
                ],
            ],
            [
                'name' => 'Thema 41',
                'slug' => 'tema5',
                'description' => 'Tema undangan klasik elegan dengan pembuka bergambar, hitung mundur, cerita cinta, galeri, amplop digital, dan doa restu.',
                'category' => 'Klasik',
                'template_path' => 'template/tema5.html',
                'thumbnail' => null,
                'price' => (int) config('invitation.default_price', 150000),
                'version' => '1.0',
                'status' => 'active',
                'features' => [
                    'story' => true,
                    'gallery' => true,
                    'music' => true,
                    'theme' => true,
                    'rsvp' => true,
                    'families' => true,
                    'gift' => true,
                    'live_stream' => false,
                    'countdown' => true,
                    'quote' => true,
                    'multiple_events' => true,
                ],
                'config' => [
                    'renderer' => 'tema5',
                    'save_the_date_format' => 'd.m.Y',
                    'countdown_selector' => '.elementor-countdown-wrapper',
                    'countdown_format' => 'timestamp',
                    'guest_label' => 'DEAR TO',
                    'backgrounds' => [
                        'gate' => ['id' => '90e124f', 'label' => 'Latar halaman pembuka', 'multiple' => true],
                        'cover' => ['id' => '5d8c82ef', 'label' => 'Foto sampul', 'multiple' => true],
                        'groom' => ['id' => '1b5619e', 'label' => 'Latar mempelai pria', 'multiple' => false],
                        'bride' => ['id' => '2247578f', 'label' => 'Latar mempelai wanita', 'multiple' => false],
                        'closing' => ['id' => 'ad718c9', 'label' => 'Latar penutup', 'multiple' => true],
                    ],
                ],
            ],
        ];

        foreach ($templates as $template) {
            // Installation seed only: admin-edited prices, flags and statuses
            // must survive a re-seed of an existing installation.
            Template::firstOrCreate(['slug' => $template['slug']], $template);
        }
    }
}
