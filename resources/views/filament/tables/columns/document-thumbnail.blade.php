@php
    $task = $getRecord();
    $files = \App\Support\TaskFiles::paths($getState());
@endphp
<div class="flex flex-wrap gap-2 p-2" style="min-width: 150px; max-width: 300px;">
    @forelse ($files as $path)
        @php
            $name = \App\Support\TaskFiles::originalName($task, 'documento', $path);
            $url = \App\Support\TaskFiles::url($task, 'documento', $path);
        @endphp
        <a href="{{ $url }}" target="_blank" rel="noopener noreferrer" title="{{ $name }}">
            <img src="{{ asset(strtolower(pathinfo($path, PATHINFO_EXTENSION)) === 'pdf' ? 'icons/pdf.png' : 'icons/file.png') }}" alt="{{ $name }}" width="25" />
        </a>
    @empty
        <span class="text-gray-500">No hay documentos</span>
    @endforelse
</div>
