<?php

namespace Database\Seeders;

use App\Models\LedAmbienceItem;
use App\Models\LedAmbienceScene;
use App\Models\Product;
use Illuminate\Database\Seeder;

class LedAmbienceSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Idle Scene (Default idle loop when no RFID is scanned - SRS FR-AMB-01)
        $idleScene = LedAmbienceScene::updateOrCreate(
            ['name' => 'Idle Nature & Store Ambience'],
            [
                'scene_type'     => 'idle',
                'activity_slug'  => null,
                'video_url'      => 'https://assets.mixkit.co/videos/preview/mixkit-mountain-range-under-a-clear-sky-40084-large.mp4',
                'audio_url'      => 'https://assets.mixkit.co/active_storage/sfx/2432/2432-preview.mp3',
                'lighting_color' => '#e8500a',
                'description'    => 'Suasana default alam terbuka saat standby di area display LED.',
                'sort_order'     => 0,
                'is_active'      => true,
            ]
        );

        // 2. Active Ambience Scenes matching EIGER Outdoor Activities (SRS FR-AMB-02)
        $scenes = [
            [
                'name'           => 'Mountaineering Summit Storm',
                'scene_type'     => 'active',
                'activity_slug'  => 'mountaineering',
                'video_url'      => 'https://assets.mixkit.co/videos/preview/mixkit-snow-capped-mountains-in-winter-40087-large.mp4',
                'audio_url'      => 'https://assets.mixkit.co/active_storage/sfx/2433/2433-preview.mp3',
                'lighting_color' => '#0284c7', // Cold Blue
                'description'    => 'Efek salju puncak gunung dan tiupan angin untuk perlengkapan ekspedisi teknis.',
                'sort_order'     => 1,
            ],
            [
                'name'           => 'Rainforest Hiking & Trekking Trail',
                'scene_type'     => 'active',
                'activity_slug'  => 'hiking',
                'video_url'      => 'https://assets.mixkit.co/videos/preview/mixkit-aerial-view-of-a-dense-forest-40089-large.mp4',
                'audio_url'      => 'https://assets.mixkit.co/active_storage/sfx/2434/2434-preview.mp3',
                'lighting_color' => '#16a34a', // Forest Green
                'description'    => 'Suara gemerisik dedaunan, gemericik air hutan tropis, dan rimbun pepohonan.',
                'sort_order'     => 2,
            ],
            [
                'name'           => 'Riding / Motorsport Sunset Highway',
                'scene_type'     => 'active',
                'activity_slug'  => 'riding',
                'video_url'      => 'https://assets.mixkit.co/videos/preview/mixkit-motorcyclist-on-the-road-during-sunset-40092-large.mp4',
                'audio_url'      => 'https://assets.mixkit.co/active_storage/sfx/2435/2435-preview.mp3',
                'lighting_color' => '#f59e0b', // Sunset Amber
                'description'    => 'Getaran mesin dan embusan angin perjalanan bermotor jarak jauh.',
                'sort_order'     => 3,
            ],
            [
                'name'           => 'Wilderness Camping & Starlight Fire',
                'scene_type'     => 'active',
                'activity_slug'  => 'camping',
                'video_url'      => 'https://assets.mixkit.co/videos/preview/mixkit-campfire-burning-at-night-40094-large.mp4',
                'audio_url'      => 'https://assets.mixkit.co/active_storage/sfx/2436/2436-preview.mp3',
                'lighting_color' => '#ea580c', // Campfire Orange
                'description'    => 'Keredupan malam di bawah taburan bintang dengan suara kayu api unggun.',
                'sort_order'     => 4,
            ],
            [
                'name'           => 'Tactical Mission Ops',
                'scene_type'     => 'active',
                'activity_slug'  => 'tactical',
                'video_url'      => 'https://assets.mixkit.co/videos/preview/mixkit-dark-stormy-sky-with-clouds-40096-large.mp4',
                'audio_url'      => 'https://assets.mixkit.co/active_storage/sfx/2437/2437-preview.mp3',
                'lighting_color' => '#475569', // Tactical Slate
                'description'    => 'Nuansa dramatis dan tangguh untuk lini perlengkapan taktis modular.',
                'sort_order'     => 5,
            ],
        ];

        $sceneMap = [];
        foreach ($scenes as $sc) {
            $scene = LedAmbienceScene::updateOrCreate(['name' => $sc['name']], $sc);
            if ($sc['activity_slug']) {
                $sceneMap[$sc['activity_slug']] = $scene->id;
            }
        }

        // 3. Initial Scanned RFID Items mapped to LED Ambience
        $sampleProducts = Product::take(3)->get();
        if ($sampleProducts->count() >= 1) {
            LedAmbienceItem::updateOrCreate(
                ['rfid_tag' => 'E28011606000020468900001'],
                [
                    'product_id'    => $sampleProducts[0]->id,
                    'activity_slug' => 'mountaineering',
                    'scene_id'      => $sceneMap['mountaineering'] ?? null,
                    'notes'         => 'Sample jaket ekspedisi untuk trigger LED Ambience',
                    'is_active'     => true,
                ]
            );
        }

        if ($sampleProducts->count() >= 2) {
            LedAmbienceItem::updateOrCreate(
                ['rfid_tag' => 'E28011606000020468900002'],
                [
                    'product_id'    => $sampleProducts[1]->id,
                    'activity_slug' => 'hiking',
                    'scene_id'      => $sceneMap['hiking'] ?? null,
                    'notes'         => 'Sample sepatu hiking untuk trigger LED Ambience',
                    'is_active'     => true,
                ]
            );
        }
    }
}
