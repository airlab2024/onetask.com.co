@php
    $task = $getRecord();
    $files = \App\Support\TaskFiles::paths($getState());
@endphp
<div class="flex flex-wrap gap-2 p-2" style="min-width: 150px; max-width: 300px;">
    @forelse ($files as $path)
        @php
            $name = \App\Support\TaskFiles::originalName($task, 'fotos_ingreso', $path);
            $url = \App\Support\TaskFiles::url($task, 'fotos_ingreso', $path);
        @endphp
        @if (\App\Support\TaskFiles::resolve('fotos_ingreso', $path))
        <a href="{{ $url }}" target="_blank" rel="noopener noreferrer" title="{{ $name }}">
            <img src="{{ $url }}" alt="{{ $name }}" loading="lazy" class="h-8 w-8 object-cover rounded border" />
        </a>
        @else
            <span class="text-gray-500" title="{{ $name }}">Archivo no disponible</span>
        @endif
    @empty
        <span class="text-gray-500">No hay imágenes</span>
    @endforelse
</div>
