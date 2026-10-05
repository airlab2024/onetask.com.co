<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detalles de la Tarea</title>
</head>
<body>
    <h1>Detalles de la Tarea</h1>
    <p><strong>ID:</strong> {{ $task->id }}</p>
    <p><strong>Nombre:</strong> {{ $task->name }}</p>
    <p><strong>Descripción:</strong> {{ $task->description }}</p>
</body>
</html>
