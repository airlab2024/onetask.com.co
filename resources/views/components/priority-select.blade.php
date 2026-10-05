<select
    {{ $attributes->merge(['class' => 'custom-select-prioridad']) }}
    onchange="updatePriorityColor(this)"
>
    @foreach ($options as $key => $value)
        <option
            value="{{ $key }}"
            data-color="
                @switch($key)
                    @case('Critica') #FF4C4C @break  {{-- Rojo --}}
                    @case('Alta') #FFD700 @break  {{-- Amarillo --}}
                    @case('Media') #5559DF @break  {{-- Azul --}}
                    @case('Baja') #32CD32 @break  {{-- Verde --}}
                    @default white
                @endswitch
            "
            @selected($key == $selected)
        >
            {{ $value }}
        </option>
    @endforeach
</select>

<style>
    .custom-select-prioridad {
        width: 100%;
        padding: 8px;
        font-size: 16px;
        font-weight: bold;
        border-radius: 8px;
        border: 2px solid #ccc;
        text-align: center;
    }
</style>

<script>
    document.addEventListener("DOMContentLoaded", function () {
        const select = document.querySelector(".custom-select-prioridad");
        updatePriorityColor(select);
    });

    function updatePriorityColor(select) {
        const selectedOption = select.options[select.selectedIndex];
        const color = selectedOption.getAttribute("data-color");
        select.style.backgroundColor = color;
        select.style.color = "#fff";  // Texto blanco para contraste
    }
</script>
