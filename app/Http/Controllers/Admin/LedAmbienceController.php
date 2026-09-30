<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\LedAmbienceItem;
use App\Models\LedAmbienceTemplate;
use App\Services\LedAmbienceMediaStorage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class LedAmbienceController extends Controller
{
    public function index(): View
    {
        $templates = LedAmbienceTemplate::ordered()->get();

        return view('admin.led-ambience.index', [
            'templates' => $templates,
            'summary' => [
                'templates' => $templates->count(),
                'videos' => $templates->whereNotNull('video_url')->count(),
                'audio' => $templates->whereNotNull('audio_url')->count(),
                'rfid' => LedAmbienceItem::where('is_active', true)->count(),
            ],
        ]);
    }

    public function editTemplate(LedAmbienceTemplate $template): View
    {
        abort_unless(array_key_exists($template->template_key, (array) config('led_ambience.templates')), 404);

        return view('admin.led-ambience.templates.edit', compact('template'));
    }

    public function updateTemplate(
        Request $request,
        LedAmbienceTemplate $template,
        LedAmbienceMediaStorage $mediaStorage,
    ): RedirectResponse {
        abort_unless(array_key_exists($template->template_key, (array) config('led_ambience.templates')), 404);

        $validated = $request->validate([
            'description' => ['nullable', 'string', 'max:1000'],
            'lighting_color' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'is_active' => ['required', 'boolean'],
            'remove_video' => ['nullable', 'boolean'],
            'remove_audio' => ['nullable', 'boolean'],
            'video_file' => [
                'nullable', 'file',
                'mimetypes:video/mp4,video/webm',
                'max:'.((int) config('led_ambience.max_video_mb', 500) * 1024),
            ],
            'audio_file' => [
                'nullable', 'file',
                'mimetypes:audio/mpeg,audio/mp3,audio/wav,audio/x-wav,audio/ogg,audio/mp4,audio/x-m4a,audio/aac',
                'max:'.((int) config('led_ambience.max_audio_mb', 100) * 1024),
            ],
        ], [
            'video_file.mimetypes' => 'Video harus berformat MP4 atau WebM.',
            'audio_file.mimetypes' => 'Audio harus berformat MP3, WAV, OGG, M4A, atau AAC.',
        ]);

        $updates = [
            'description' => $validated['description'] ?? null,
            'lighting_color' => strtolower($validated['lighting_color']),
            'is_active' => (bool) $validated['is_active'],
        ];

        if ($request->boolean('remove_video')) {
            $updates['video_url'] = null;
        }
        if ($request->boolean('remove_audio')) {
            $updates['audio_url'] = null;
        }
        if ($request->hasFile('video_file')) {
            $updates['video_url'] = $mediaStorage->store(
                $request->file('video_file'),
                'video',
                $template->template_key,
            );
        }
        if ($request->hasFile('audio_file')) {
            $updates['audio_url'] = $mediaStorage->store(
                $request->file('audio_file'),
                'audio',
                $template->template_key,
            );
        }

        $template->update($updates);

        return redirect()->route('admin.led-ambience.index')
            ->with('success', "Template {$template->name} berhasil diperbarui.");
    }
}
