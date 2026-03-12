@php
    $formatBytes = static function (int $bytes): string {
        if ($bytes <= 0) {
            return '0 B';
        }

        $units = ['B', 'KB', 'MB', 'GB'];
        $index = 0;
        $size = (float) $bytes;

        while ($size >= 1024 && $index < count($units) - 1) {
            $size /= 1024;
            $index++;
        }

        return number_format($size, $size >= 10 || $index === 0 ? 0 : 1).' '.$units[$index];
    };
@endphp

<x-app-layout>
    <x-slot name="header">
        <h2 class="tg-app-title">{{ __('Shared Media') }}</h2>
    </x-slot>

    <section class="tg-dashboard-grid">
        <article class="tg-panel">
            <h3>{{ __('Recent attachments') }}</h3>
            <p>{{ __('Images, videos and files from your dialogs.') }}</p>
        </article>

        <article class="tg-panel">
            <div class="tg-media-grid">
                @forelse ($attachments as $item)
                    <article class="tg-media-card">
                        @if ($item['type'] === 'image')
                            <img
                                class="tg-media-card__preview"
                                src="{{ $item['url'] }}"
                                alt="{{ $item['name'] }}">
                        @elseif ($item['type'] === 'video')
                            <video class="tg-media-card__preview" src="{{ $item['url'] }}" controls preload="metadata"></video>
                        @else
                            <div class="tg-media-card__file">📎</div>
                        @endif

                        <div class="tg-media-card__body">
                            <p class="tg-media-card__name">{{ $item['name'] }}</p>
                            <p class="tg-media-card__meta">
                                {{ $item['peer_name'] }} • {{ $formatBytes($item['size']) }}
                            </p>
                            <a class="tg-panel__cta tg-panel__cta--secondary" href="{{ $item['url'] }}" target="_blank" rel="noopener noreferrer">
                                {{ __('Open file') }}
                            </a>
                        </div>
                    </article>
                @empty
                    <p class="tg-panel__empty">{{ __('No attachments yet.') }}</p>
                @endforelse
            </div>
        </article>
    </section>
</x-app-layout>
