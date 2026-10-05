<?php

namespace App\Filament\Resources;

use App\Filament\Resources\TaskResource\Pages;
use App\Models\Task;
use Filament\Forms;
use Filament\Resources\Form;
use Filament\Resources\Resource;
use Filament\Resources\Table;
use Filament\Tables;
use Filament\Forms\Components\Actions\Action as FormAction; // Para el suffixAction
use Filament\Tables\Actions\Action as TableAction; // Para el action
use App\Forms\Components\TaskFileUpload;
use App\Support\TaskFiles;
use App\Support\TaskInputValidator;
use Illuminate\Support\Facades\Gate;
use Filament\Forms\Components\TextInput;
use App\Models\Cliente;
use App\Models\Marca;
use App\Models\User;
use Filament\Notifications\Notification;
use App\Notifications\TaskAssignedNotification;
use Filament\Tables\Filters\Filter;
use Illuminate\Database\Eloquent\Builder;
use Filament\Tables\Filters\SelectFilter;
use Filament\Forms\Components\DatePicker;
use Illuminate\Support\Facades\Storage;
use Filament\Forms\ComponentContainer;
use Filament\Tables\Columns\IconColumn;
use Illuminate\Database\Eloquent\Model; // Asegúrate de tener este importado








class TaskResource extends Resource
{
    protected static ?string $model = Task::class;
    protected static ?string $navigationIcon = 'heroicon-o-collection';
    protected static function recibidoPorOptions(): array
    {
        return [
            'GERMAN BARKER' => 'GERMAN BARKER',
            'PABLO GUERRERO' => 'PABLO GUERRERO',
            'NORBEY BARAHONA' => 'NORBEY BARAHONA',
            'JOSE LOPEZ' => 'JOSE LOPEZ',
        ];
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Información General')->schema([
                    Forms\Components\Grid::make(3)->schema([
                        Forms\Components\TextInput::make('task_short_code')
                            ->label('TICKET')
                            ->disabled() // Este campo será generado automáticamente y será de solo lectura
                            ->default(fn() => Task::generateTaskShortCode()), // Mostrar el código generado automáticamente
                        Forms\Components\DatePicker::make('start_date')
                            ->label('FECHA INGRESO')
                            ->required()
                            ->displayFormat('Y-m-d'),
                        Forms\Components\Select::make('recibido_por')
                            ->label('RECIBIDO POR')
                            ->options(self::recibidoPorOptions())
                            ->required()
                            // opcional: normaliza a mayúsculas por si llega algo raro
                            ->dehydrateStateUsing(fn($state) => $state ? mb_strtoupper($state) : $state),
                    ]),
                    Forms\Components\Grid::make(3)->schema([
                        Forms\Components\TextInput::make('entregado_por')
                            ->label(label: 'ENTREGADO POR')
                            ->required(),
                        Forms\Components\Select::make('cliente_id')
                            ->label('CLIENTE')
                            ->relationship('cliente', 'nombre')  // Relación con el modelo Cliente
                            ->options(fn() => Cliente::pluck('nombre', 'id'))
                            ->searchable()
                            ->preload()
                            ->required()
                            ->createOptionForm([
                                Forms\Components\TextInput::make('nombre')
                                    ->label('Nombre del Cliente')
                                    ->required()
                                    ->maxLength(255),
                                Forms\Components\TextInput::make('abreviatura')
                                    ->label('Abreviatura')
                                    ->placeholder('Ej.: AIRLAB')
                                    ->maxLength(50)
                                    ->helperText('Abreviatura corta del cliente para usar en tablas.'),


                                Forms\Components\TextInput::make('contacto')
                                    ->label('Contacto')
                                    ->required()
                                    ->maxLength(255),

                                Forms\Components\TextInput::make('cargo')
                                    ->label('Cargo')
                                    ->required()
                                    ->maxLength(255),

                                Forms\Components\TextInput::make('nit')
                                    ->label('NIT')
                                    ->required()
                                    ->maxLength(255),

                                Forms\Components\TextInput::make('ciudad')
                                    ->label('Ciudad')
                                    ->required()
                                    ->maxLength(255),

                                Forms\Components\TextInput::make('telefono')
                                    ->label('Teléfono')
                                    ->required()
                                    ->maxLength(255),

                                Forms\Components\TextInput::make('correo_electronico')
                                    ->label('Correo Electrónico')
                                    ->required()
                                    ->email()
                                    ->maxLength(255),

                                Forms\Components\TextInput::make('encargado_de_cuenta')
                                    ->label('Encargado de Cuenta')
                                    ->maxLength(255),
                            ])
                            ->createOptionUsing(function (array $data) {
                                $cliente = Cliente::create($data);

                                // Notificación de éxito
                                Notification::make()
                                    ->title('Cliente Creado')
                                    ->success()
                                    ->body("El cliente **{$cliente->nombre}** ha sido registrado exitosamente.")
                                    ->send();

                                return $cliente->getKey();
                            }),
                        Forms\Components\Select::make('equipo')
                            ->label('EQUIPO (CONTAMINANTE)')
                            ->options([
                                'PM10' => 'PM10',
                                'PM2.5' => 'PM2.5',
                                'TSP' => 'TSP',
                                'SO2' => 'SO2',
                                'O3' => 'O3',
                                'CO' => 'CO',
                                'NO' => 'NO',
                                'NO2' => 'NO2',
                                'NOX' => 'NOX',
                                'DV' => 'DV',
                                'VV' => 'VV',
                                'HR' => 'HR',
                                'TEMP' => 'TEMP',
                                'PB' => 'PB',
                                'RS' => 'RS',
                                'RAIN' => 'RAIN',
                                'H2S' => 'H2S',
                                'NH3' => 'NH3',
                                'TRS' => 'TRS',
                                'MP' => 'MP',
                                'ZAG' => 'ZAG',
                                'CMG' => 'CMG',
                            ])
                            ->required(),
                    ]),
                    Forms\Components\Grid::make(3)->schema([
                        Forms\Components\Select::make('marca_id')
                            ->label('MARCA')
                            ->relationship('marca', 'nombre')
                            ->searchable()
                            ->preload()
                            ->required()
                            ->suffixAction(
                                FormAction::make('addMarca')
                                    ->label('Agregar')
                                    ->icon('heroicon-o-plus')
                                    ->form([
                                        TextInput::make('new_marca')
                                            ->label('Nombre de la Marca')
                                            ->required(),
                                    ])
                                    ->action(function ($data, $livewire) {
                                        if (Marca::where('nombre', $data['new_marca'])->exists()) {
                                            $livewire->notify('danger', 'El nombre de la marca ya existe.');
                                        } else {
                                            Marca::create(['nombre' => $data['new_marca']]);
                                            $livewire->notify('success', 'Marca agregada exitosamente.');
                                        }
                                    })
                            ),


                        Forms\Components\TextInput::make('modelo')
                            ->label('MODELO')
                            ->required(),
                        Forms\Components\TextInput::make('serial')
                            ->label('SERIAL')
                            ->required(),
                    ]),
                    Forms\Components\Grid::make(3)->schema([
                        Forms\Components\TextInput::make('codigo_interno')
                            ->label('CODIGO INTERNO'),
                    ]),
                    TaskFileUpload::make('remision_ingreso')
                        ->label('REMISIÓN DE INGRESO')
                        ->acceptedFileTypes([
                            'application/pdf',
                            'application/msword',
                            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                            'application/vnd.ms-excel',
                            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
                        ])


                        ->multiple()
                        ->enableDownload()
                        ->enableOpen(),
                ]),
                Forms\Components\Section::make('Detalles del Equipo')->schema([
                    Forms\Components\Grid::make(3)->schema([
                        Forms\Components\Textarea::make('accesorios_entregados')
                            ->label('ACCESORIOS ENTREGADOS')
                            ->required(),
                        Forms\Components\Textarea::make('descripcion_falla_cliente')
                            ->label('DESCRIPCION FALLA SEGUN CLIENTE')
                            ->required(),
                        Forms\Components\Textarea::make('observaciones')
                            ->label('OBSERVACIONES')
                            ->required(),
                    ]),
                    Forms\Components\Grid::make(2)->schema([
                        TaskFileUpload::make('fotos_ingreso')
                            ->label('FOTOS INGRESO')
                            ->multiple()
                            ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/gif'])
                            ->image()



                            ->enableDownload()
                            ->enableOpen(),


                    ]),
                ]),
                Forms\Components\Section::make('Prioridad y Asignación')->schema([
                    Forms\Components\Grid::make(2)->schema([
                        Forms\Components\Select::make('prioridad')
                            ->label('PRIORIDAD')
                            ->options([
                                'Critica' => 'Critica',
                                'Alta' => 'Alta',
                                'Media' => 'Media',
                                'Baja' => 'Baja',
                            ])
                            ->required(),
                        Forms\Components\Select::make('asignado_a')
                            ->label('Asignado a')
                            ->options(User::all()->pluck('name', 'id'))
                            ->searchable()
                            ->disabled(fn() => auth()->user()?->hasRole('TECNICO'))
                            ->required(),
                    ]),
                ]),
                Forms\Components\Section::make('Avance del Trabajo')->schema([
                    Forms\Components\Grid::make(3)->schema([
                        Forms\Components\DatePicker::make('cronograma_inicio')
                            ->label('FECHA INICIO')
                            ->required()
                            ->displayFormat('Y-m-d'),

                        Forms\Components\DatePicker::make('cronograma_fin')
                            ->afterOrEqual(fn (\Closure $get) => $get('cronograma_inicio'))
                            ->label('FECHA FIN')
                            ->required()
                            ->displayFormat('Y-m-d'),

                        Forms\Components\TextInput::make('dias_restantes')
                            ->label('DÍAS RESTANTES')
                            ->disabled()
                            ->formatStateUsing(fn($record) => $record?->dias_restantes !== null ? $record->dias_restantes . ' días' : 'No calculado')
                            ->dehydrated(false),

                    ]),
                    Forms\Components\Grid::make(3)->schema([
                        Forms\Components\Select::make('avance_trabajo')
                            ->label('AVANCE DEL TRABAJO')
                            ->options([
                                'No iniciado' => 'No iniciado',
                                'Iniciado' => 'Iniciado',
                                'En Curso' => 'En Curso',
                                'Detenido' => 'Detenido',
                                'Listo' => 'Listo',
                            ])
                            ->required(),
                        Forms\Components\Textarea::make('actividades_realizadas')
                            ->label('ACTIVIDADES REALIZADAS')
                            ->required(),
                        Forms\Components\Select::make('informe_diagnostico')
                            ->label('INFORME DE DIAGNOSTICO')
                            ->options([
                                'LISTO' => 'LISTO',
                                'EN CURSO' => 'EN CURSO',
                                'DETENIDO' => 'DETENIDO',
                                'NO INICIADO' => 'NO INICIADO',
                            ])
                            ->required(),
                    ]),
                ]),
                Forms\Components\Section::make('Documentación y Estado')->schema([
                    Forms\Components\Grid::make(3)->schema([
                        TaskFileUpload::make('documento')
                            ->label('INFORME TÉCNICO')
                            ->multiple()
                            ->enableDownload()
                            ->acceptedFileTypes(['application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document']) // Solo permite estos tipos de archivos
                                // Directorio de almacenamiento

                            ->enableOpen(),
                        Forms\Components\Select::make('estado')
                            ->label('ESTADO')
                            ->options([
                                'En Servicio Técnico' => 'En Servicio Técnico',
                                'Pen. Aut Cliente' => 'Pen. Aut Cliente',
                                'Pendiente de repuestos' => 'Pendiente de repuestos',
                                'Para entrega' => 'Para entrega',
                                'Entrega sin reparación' => 'Entrega sin reparación',
                                'Entregado' => 'Entregado',
                            ])
                            ->required(),

                    ]),
                    Forms\Components\Grid::make(2)->schema([
                        Forms\Components\DatePicker::make('fecha_retiro')
                            ->label('FECHA RETIRO')
                            ->displayFormat('Y-m-d'),
                        Forms\Components\TextInput::make('retirado_por')
                            ->label('RETIRADO POR'),
                    ]),
                    TaskFileUpload::make('remision_salida')
                        ->label('REMISIÓN DE SALIDA')
                        ->acceptedFileTypes(['application/pdf'])


                        ->enableDownload()
                        ->enableOpen()
                        ->multiple(),
                ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table

            ->columns([

                Tables\Columns\TextColumn::make('task_short_code')
                    ->label('TICKET')
                    ->sortable()
                    ->extraAttributes([
                        'style' => 'min-width: 200px; text-align: center; padding: 0px;',
                    ])
                    ->searchable(),
                Tables\Columns\TextInputColumn::make('start_date')
                    ->disabled(fn($record) => !static::canEdit($record))
                    ->updateStateUsing(fn($record, $state) => TaskInputValidator::update($record, 'start_date', $state))
                    ->label('FECHA INGRESO')
                    ->alignCenter() // Agregado para centrar encabezado y contenido
                    ->type('date')
                    ->sortable()
                    ->extraAttributes([
                        'class' => 'w-full text-center', // Hace que ocupe todo el ancho disponible y centra el texto
                    ])
                    ->searchable(),
                Tables\Columns\SelectColumn::make('recibido_por')
                    ->disabled(fn($record) => !static::canEdit($record))
                    ->updateStateUsing(fn($record, $state) => TaskInputValidator::update($record, 'recibido_por', $state))
                    ->label('RECIBIDO POR')
                    ->alignCenter()
                    ->options(self::recibidoPorOptions())
                    ->sortable()
                    ->extraAttributes([
                        'class' => 'w-full  rounded-md p-1.5  text-gray-2000 text-center text-sm',
                        'style' => 'min-height: 100%; height: 100%; min-width: 180px; ', // Ajusta aquí el ancho
                    ])

                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextInputColumn::make('entregado_por')
                    ->disabled(fn($record) => !static::canEdit($record))
                    ->updateStateUsing(fn($record, $state) => TaskInputValidator::update($record, 'entregado_por', $state))
                    ->label('ENTREGADO POR')
                    ->alignCenter() // Agregado para centrar encabezado y contenido
                    ->extraAttributes([
                        'class' => 'w-full text-center', // Hace que ocupe todo el ancho disponible y centra el texto
                    ])
                    ->sortable()
                    ->searchable(),
                Tables\Columns\ViewColumn::make('remision_ingreso')
                    ->label('REMISIÓN INGRESO')
                    ->alignCenter() // Agregado para centrar encabezado y contenido
                    ->view('filament.tables.columns.remision-ingreso-thumbnail'),
                Tables\Columns\SelectColumn::make('cliente_id')
                    ->disabled(fn($record) => !static::canEdit($record))
                    ->updateStateUsing(fn($record, $state) => TaskInputValidator::update($record, 'cliente_id', $state))
                    ->label('CLIENTE')
                    ->alignCenter()
                    ->options(
                        fn() =>
                        Cliente::all()->mapWithKeys(function ($cliente) {
                            return [
                                $cliente->id => $cliente->abreviatura ?: $cliente->nombre,
                            ];
                        })
                    )
                    ->searchable()
                    ->extraAttributes([
                        'class' => 'w-full rounded-md p-1.5 text-gray-2000 text-center text-sm',
                        'style' => 'min-height: 100%; height: 100%; min-width: 180px;',
                    ])
                    ->sortable(),



                Tables\Columns\SelectColumn::make('marca_id')
                    ->disabled(fn($record) => !static::canEdit($record))
                    ->updateStateUsing(fn($record, $state) => TaskInputValidator::update($record, 'marca_id', $state))
                    ->label('MARCA')
                    ->alignCenter() // Agregado para centrar encabezado y contenido
                    ->options(fn() => Marca::pluck('nombre', 'id'))
                    ->searchable()
                    ->sortable()
                    ->extraAttributes([
                        'class' => 'w-full  rounded-md p-1.5  text-gray-2000 text-center text-sm',
                        'style' => 'min-height: 100%; height: 100%; min-width: 150px; ', // Ajusta aquí el ancho
                    ]),
                Tables\Columns\TextInputColumn::make('equipo')
                    ->disabled(fn($record) => !static::canEdit($record))
                    ->updateStateUsing(fn($record, $state) => TaskInputValidator::update($record, 'equipo', $state))
                    ->label('EQUIPO (CONTAMINANTE)')
                    ->alignCenter() // Agregado para centrar encabezado y contenido
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextInputColumn::make('modelo')
                    ->disabled(fn($record) => !static::canEdit($record))
                    ->updateStateUsing(fn($record, $state) => TaskInputValidator::update($record, 'modelo', $state))
                    ->label('MODELO')
                    ->alignCenter() // Agregado para centrar encabezado y contenido

                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextInputColumn::make('serial')
                    ->disabled(fn($record) => !static::canEdit($record))
                    ->updateStateUsing(fn($record, $state) => TaskInputValidator::update($record, 'serial', $state))
                    ->label('SERIAL')
                    ->sortable()
                    ->alignCenter() // Agregado para centrar encabezado y contenido
                    ->searchable(),
                Tables\Columns\TextInputColumn::make('codigo_interno')
                    ->disabled(fn($record) => !static::canEdit($record))
                    ->updateStateUsing(fn($record, $state) => TaskInputValidator::update($record, 'codigo_interno', $state))
                    ->label('CODIGO INTERNO')
                    ->sortable()
                    ->alignCenter() // Agregado para centrar encabezado y contenido

                    ->searchable(),
                Tables\Columns\TextInputColumn::make('accesorios_entregados')
                    ->disabled(fn($record) => !static::canEdit($record))
                    ->updateStateUsing(fn($record, $state) => TaskInputValidator::update($record, 'accesorios_entregados', $state))
                    ->label('ACCESORIOS ENTREGADOS')
                    ->sortable()
                    ->alignCenter() // Agregado para centrar encabezado y contenido
                    ->extraAttributes([
                        'class' => 'w-full text-center', // Hace que ocupe todo el ancho disponible y centra el texto
                    ])
                    ->searchable(),
                Tables\Columns\TextInputColumn::make('descripcion_falla_cliente')
                    ->disabled(fn($record) => !static::canEdit($record))
                    ->updateStateUsing(fn($record, $state) => TaskInputValidator::update($record, 'descripcion_falla_cliente', $state))
                    ->label('DESCRIPCION FALLA SEGUN CLIENTE')
                    ->alignCenter() // Agregado para centrar encabezado y contenido
                    ->sortable()
                    ->extraAttributes([
                        'class' => 'w-full text-center', // Hace que ocupe todo el ancho disponible y centra el texto
                    ])
                    ->searchable(),
                Tables\Columns\TextInputColumn::make('observaciones')
                    ->disabled(fn($record) => !static::canEdit($record))
                    ->updateStateUsing(fn($record, $state) => TaskInputValidator::update($record, 'observaciones', $state))
                    ->label('OBSERVACIONES')
                    ->alignCenter() // Agregado para centrar encabezado y contenido
                    ->sortable()
                    ->searchable(),
                // Reemplazar la columna actual de fotos_ingreso con:
                Tables\Columns\ViewColumn::make('fotos_ingreso')
                    ->label('FOTOS INGRESO')
                    ->view('filament.tables.columns.manage-images')
                    ->alignCenter(), // Agregado para centrar encabezado y contenido
                Tables\Columns\SelectColumn::make('prioridad')
                    ->disabled(fn($record) => !static::canEdit($record))
                    ->updateStateUsing(fn($record, $state) => TaskInputValidator::update($record, 'prioridad', $state))
                    ->label('PRIORIDAD')
                    ->alignCenter() // Agregado para centrar encabezado y contenido
                    ->options([
                        'Critica' => 'Crítica ⚠️',
                        'Alta' => 'Alta',
                        'Media' => 'Media',
                        'Baja' => 'Baja',
                    ])
                    ->alignCenter()
                    ->alignCenter() // Agregado para centrar encabezado y contenido
                    ->extraAttributes(fn($record) => [
                        'class' => 'w-full h-full flex items-center justify-center rounded-md', // Ajuste de ancho y alto y centrado del contenido
                        'style' => match ($record->prioridad) {
                            'Critica' => 'background-color: #333333; color: white; font-weight: bold; padding: 4px; min-width: 200px;',
                            'Alta' => 'background-color: #401694; color: white; font-weight: bold; padding: 4px; min-width: 200px;',
                            'Media' => 'background-color: #5559df; color: white; font-weight: bold; padding: 4px; min-width: 200px;',
                            'Baja' => 'background-color: #579bfc; color: white; font-weight: bold; padding: 4px; min-width: 200px; ',
                            default => '',
                        },
                    ])
                    ->sortable()
                    ->searchable(),
                Tables\Columns\SelectColumn::make('asignado_a')
                    ->disabled(fn($record) => !static::canEdit($record) || auth()->user()?->hasRole('TECNICO'))
                    ->updateStateUsing(fn($record, $state) => TaskInputValidator::update($record, 'asignado_a', $state))
                    ->label('ASIGNADO A')
                    ->alignCenter() // Agregado para centrar encabezado y contenido
                    ->options(fn() => User::pluck('name', 'id'))
                    ->searchable()
                    ->sortable()
                    ->disabled(fn() => auth()->user()?->hasRole('TECNICO'))
                    ->alignCenter() // Agregado para centrar encabezado y contenido
                    ->extraAttributes([
                        'class' => 'w-full border rounded-md p-1.5 bg-white text-gray-2000 text-center text-sm',
                        'style' => 'min-height: 100%; height: 100%; min-width: 150px; border-color: #d1d5db;', // Ajusta aquí el ancho
                    ]),
                Tables\Columns\TextInputColumn::make('cronograma_inicio')
                    ->disabled(fn($record) => !static::canEdit($record))
                    ->updateStateUsing(fn($record, $state) => TaskInputValidator::update($record, 'cronograma_inicio', $state))
                    ->label('FECHA INICIO')
                    ->type('date')
                    ->sortable()
                    ->searchable()
                    ->alignCenter() // Agregado para centrar encabezado y contenido
                    ->extraAttributes([
                        'class' => 'w-full text-center', // Hace que ocupe todo el ancho disponible y centra el texto
                    ]),
                Tables\Columns\TextInputColumn::make('cronograma_fin')
                    ->disabled(fn($record) => !static::canEdit($record))
                    ->updateStateUsing(fn($record, $state) => TaskInputValidator::update($record, 'cronograma_fin', $state))
                    ->label('FECHA FIN')
                    ->type('date')
                    ->alignCenter() // Agregado para centrar encabezado y contenido
                    ->sortable()
                    ->searchable()
                    ->extraAttributes([
                        'class' => 'w-full text-center', // Hace que ocupe todo el ancho disponible y centra el texto
                    ]),
                Tables\Columns\TextColumn::make('dias_restantes')
                    ->label('DÍAS RESTANTES')
                    ->alignCenter() // Agregado para centrar encabezado y contenido
                    ->formatStateUsing(function ($record) {
                        $hoy = \Carbon\Carbon::today();
                        $inicio = \Carbon\Carbon::parse($record->cronograma_inicio);
                        $fin = \Carbon\Carbon::parse($record->cronograma_fin);

                        if ($hoy->lt($inicio)) {
                            $diasFaltantes = $hoy->diffInDays($inicio);
                            return "Faltan {$diasFaltantes} día(s) para iniciar";
                        } elseif ($hoy->between($inicio, $fin)) {
                            $diasDisponibles = $hoy->diffInDays($fin);
                            return "Quedan {$diasDisponibles} día(s) para terminar";
                        } else {
                            $diasVencidos = $fin->diffInDays($hoy);
                            return "{$diasVencidos} día(s) vencido(s)";
                        }
                    })
                    ->color(function ($record) {
                        $hoy = \Carbon\Carbon::today();
                        $inicio = \Carbon\Carbon::parse($record->cronograma_inicio);
                        $fin = \Carbon\Carbon::parse($record->cronograma_fin);

                        if ($hoy->lt($inicio)) {
                            return 'warning'; // Antes de iniciar
                        } elseif ($hoy->between($inicio, $fin)) {
                            return 'success'; // Dentro del rango de servicio
                        } else {
                            return 'danger'; // Ya vencido
                        }
                    })
                    ->sortable(false), // No ordenable porque es cálculo dinámico
                Tables\Columns\SelectColumn::make('avance_trabajo')
                    ->disabled(fn($record) => !static::canEdit($record))
                    ->updateStateUsing(fn($record, $state) => TaskInputValidator::update($record, 'avance_trabajo', $state))
                    ->label('AVANCE DEL TRABAJO')
                    ->alignCenter() // Agregado para centrar encabezado y contenido
                    ->options([
                        'No iniciado' => 'NO INICIADO',
                        'Iniciado' => 'INICIADO',
                        'En Curso' => 'EN CURSO',
                        'Detenido' => 'DETENIDO',
                        'Listo' => 'LISTO',
                    ])
                    ->alignCenter()
                    ->extraAttributes(fn($record) => [
                        'class' => 'w-full h-full flex items-center justify-center rounded-md', // Para que el select ocupe todo el espacio y se centre
                        'style' => match ($record->avance_trabajo) {
                            'No iniciado' => 'background-color: #c4c4c4; color: white; font-weight: bold; padding: 4px; min-width: 200px;',
                            'Iniciado' => 'background-color: #007eb5; color: white; font-weight: bold; padding: 4px; min-width: 200px;',
                            'En Curso' => 'background-color: #fdab3d; color: white; font-weight: bold; padding: 4px; min-width: 200px;',
                            'Detenido' => 'background-color: #df2f4a; color: white; font-weight: bold; padding: 4px; min-width: 200px;',
                            'Listo' => 'background-color: #00c875; color: white; font-weight: bold; padding: 4px; min-width: 200px;',
                            default => '',
                        },
                    ])
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextInputColumn::make('actividades_realizadas')
                    ->disabled(fn($record) => !static::canEdit($record))
                    ->updateStateUsing(fn($record, $state) => TaskInputValidator::update($record, 'actividades_realizadas', $state))
                    ->label('ACTIVIDADES REALIZADAS')
                    ->alignCenter() // Agregado para centrar encabezado y contenido
                    ->sortable()
                    ->extraAttributes([
                        'style' => 'min-width: 300px;', // Hace que ocupe todo el ancho disponible y centra el texto
                    ])
                    ->searchable(),
                Tables\Columns\SelectColumn::make('informe_diagnostico')
                    ->disabled(fn($record) => !static::canEdit($record))
                    ->updateStateUsing(fn($record, $state) => TaskInputValidator::update($record, 'informe_diagnostico', $state))
                    ->label('INFORME DE DIAGNOSTICO')
                    ->options([
                        'LISTO' => 'LISTO',
                        'EN CURSO' => 'EN CURSO',
                        'DETENIDO' => 'DETENIDO',
                        'NO INICIADO' => 'NO INICIADO',
                    ])
                    ->alignCenter()
                    ->alignCenter() // Agregado para centrar encabezado y contenido
                    ->extraAttributes(fn($record) => [
                        'class' => 'w-full h-full flex items-center justify-center rounded-md',
                        'style' => match ($record->informe_diagnostico) {
                            'LISTO' => 'background-color: #00c875; color: white; font-weight: bold; padding: 4px;',
                            'EN CURSO' => 'background-color: #fdab3d; color: white; font-weight: bold; padding: 4px;',
                            'DETENIDO' => 'background-color: #df2f4a; color: white; font-weight: bold; padding: 4px;',
                            'NO INICIADO' => 'background-color: #c4c4c4; color: white; font-weight: bold; padding: 4px;',
                            default => '',
                        },
                    ])
                    ->sortable()
                    ->searchable(),

                Tables\Columns\ViewColumn::make('documento')
                    ->label('DOCUMENTO INFORME')
                    ->alignCenter() // Agregado para centrar encabezado y contenido
                    ->view('filament.tables.columns.document-thumbnail')
                    ->extraAttributes([
                        'style' => 'min-width: 250px; cursor: pointer; text-align: center; padding: 0px;',
                        'x-data' => '',
                        'x-on:click' => '$wire.mountAction(\'manageImages\', { record: @js($getRecord()->id) })',
                    ]),

                Tables\Columns\SelectColumn::make('estado')
                    ->disabled(fn($record) => !static::canEdit($record))
                    ->updateStateUsing(fn($record, $state) => TaskInputValidator::update($record, 'estado', $state))
                    ->label('ESTADO')
                    ->alignCenter() // Agregado para centrar encabezado y contenido
                    ->options([
                        'En Servicio Técnico' => 'En Servicio Técnico',
                        'Pen. Aut Cliente' => 'Pen. Aut Cliente',
                        'Pendiente de repuestos' => 'Pendiente de repuestos',
                        'Para entrega' => 'Para entrega',
                        'Entrega sin reparación' => 'Entrega sin reparación',
                        'Entregado' => 'Entregado',
                    ])
                    ->alignCenter()
                    ->extraAttributes(fn($record) => [
                        'class' => 'w-full h-full flex items-center justify-center rounded-md',
                        'style' => match ($record->estado) {
                            'En Servicio Técnico' => 'background-color: #c4c4c4; color: white; font-weight: bold; padding: 4px; min-width: 200px;',
                            'Pen. Aut Cliente' => 'background-color: #9cd326; color: white; font-weight: bold; padding: 4px; min-width: 200px;',
                            'Pendiente de repuestos' => 'background-color: #fdab3d; color: white; font-weight: bold; padding: 4px; min-width: 200px;',
                            'Para entrega' => 'background-color: #007eb5; color: white; font-weight: bold; padding: 4px; min-width: 200px;',
                            'Entrega sin reparación' => 'background-color: #df2f4a; color: white; font-weight: bold; padding: 4px; min-width: 200px;',
                            'Entregado' => 'background-color: #00c875; color: white; font-weight: bold; padding: 4px; min-width: 200px;',
                            default => '',
                        },
                    ])
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextInputColumn::make('fecha_retiro')
                    ->disabled(fn($record) => !static::canEdit($record))
                    ->updateStateUsing(fn($record, $state) => TaskInputValidator::update($record, 'fecha_retiro', $state))
                    ->label('FECHA RETIRO')
                    ->alignCenter() // Agregado para centrar encabezado y contenido
                    ->sortable()
                    ->searchable()
                    ->type('date')
                    ->sortable()
                    ->searchable()
                    ->extraAttributes([
                        'class' => 'w-full text-center', // Hace que ocupe todo el ancho disponible y centra el texto
                    ]),
                Tables\Columns\TextInputColumn::make('retirado_por')
                    ->disabled(fn($record) => !static::canEdit($record))
                    ->updateStateUsing(fn($record, $state) => TaskInputValidator::update($record, 'retirado_por', $state))
                    ->label('RETIRADO POR')
                    ->sortable()
                    ->alignCenter() // Agregado para centrar encabezado y contenido
                    ->searchable(),
                Tables\Columns\ViewColumn::make('remision_salida')
                    ->label('REMISIÓN SALIDA')
                    ->alignCenter() // Agregado para centrar encabezado y contenido
                    ->view('filament.tables.columns.remision-salida-thumbnail')

            ])
            ->filters([
                SelectFilter::make('estado')
                    ->label('Estado')
                    ->options([
                        'En Servicio Técnico' => 'En Servicio Técnico',
                        'Pen. Aut Cliente' => 'Pen. Aut Cliente',
                        'Pendiente de repuestos' => 'Pendiente de repuestos',
                        'Para entrega' => 'Para entrega',
                        'Entrega sin reparación' => 'Entrega sin reparación',
                        'Entregado' => 'Entregado',
                    ]),

                SelectFilter::make('prioridad')
                    ->label('Prioridad')
                    ->options([
                        'Critica' => 'Critica',
                        'Alta' => 'Alta',
                        'Media' => 'Media',
                        'Baja' => 'Baja',
                    ]),
                SelectFilter::make('cliente_id')
                    ->label('Cliente')
                    ->relationship('cliente', 'nombre')
                    ->searchable(),

                SelectFilter::make('asignado_a')
                    ->label('Responsable')
                    ->options(fn() => User::pluck('name', 'id')->toArray()),

                Filter::make('rango_fecha')
                    ->label('Rango de Fecha')
                    ->form([
                        DatePicker::make('from')
                            ->label('Desde')
                            ->displayFormat('d/m/Y'),
                        DatePicker::make('until')
                            ->label('Hasta')
                            ->displayFormat('d/m/Y'),
                    ])
                    ->query(function ($query, array $data) {
                        return $query
                            ->when($data['from'], fn($q) => $q->whereDate('cronograma_inicio', '>=', \Carbon\Carbon::parse($data['from'])->format('Y-m-d')))
                            ->when($data['until'], fn($q) => $q->whereDate('cronograma_inicio', '<=', \Carbon\Carbon::parse($data['until'])->format('Y-m-d')));
                    }),
            ])
            ->actions([

                TableAction::make('manageImages')
                    ->label('F I')
                    ->icon('heroicon-o-photograph')
                    ->modalHeading('🖼️ Gestionar Fotos de Ingreso')
                    ->modalWidth('lg')
                    ->mountUsing(function (Forms\ComponentContainer $form, $record) {
                        $form->fill([
                            'fotos_ingreso' => $record->fotos_ingreso,
                            'original_filenames_fotos_ingreso' => $record->original_filenames_fotos_ingreso,
                        ]);
                    })
                    ->form([
                        TaskFileUpload::make('fotos_ingreso')
                            ->label('FOTOS INGRESO')
                            ->multiple()
                            ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/gif'])
                            ->image()


                            ->enableOpen()

                            ->enableDownload(),
                    ])
                    ->modalButton('Guardar')
                    ->action(function ($record, $data) {
                        Gate::authorize('update', $record);
                        $record->update([
                            'fotos_ingreso' => $data['fotos_ingreso'],
                            'original_filenames_fotos_ingreso' => $data['original_filenames_fotos_ingreso'] ?? [],
                        ]);

                        Notification::make()
                            ->title('¡Guardado correctamente!')
                            ->success()
                            ->body('Las fotos de ingreso se han actualizado.')
                            ->send();
                    })
                    ->visible(fn($record) => static::canEdit($record)),

                TableAction::make('manageDocuments')
                    ->label('D I')
                    ->icon('heroicon-o-document-text')
                    ->modalHeading('🗂️ Gestionar Documento Informe')
                    ->modalWidth('lg')
                    ->mountUsing(function (Forms\ComponentContainer $form, $record) {
                        $form->fill([
                            'documento' => $record->documento,
                            'original_filename' => $record->original_filename,
                        ]);
                    })
                    ->form([
                        TaskFileUpload::make('documento')
                            ->label('DOCUMENTO')
                            ->multiple()
                            ->acceptedFileTypes([
                                'application/pdf',
                                'application/msword',
                                'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                                'application/vnd.ms-excel',
                                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
                            ])



                            ->enableDownload(), // Guarda con el nombre original
                    ])
                    ->modalButton('Guardar')
                    ->action(function ($record, $data) {
                        Gate::authorize('update', $record);
                        $record->update([
                            'documento' => $data['documento'],
                            'original_filename' => $data['original_filename'] ?? [],
                        ]);

                        Notification::make()
                            ->title('¡Guardado correctamente!')
                            ->success()
                            ->body('El documento se ha actualizado.')
                            ->send();
                    })
                    ->visible(fn($record) => static::canEdit($record)),
                TableAction::make('manageRemisionIngreso')
                    ->label('R I')
                    ->icon('heroicon-o-cloud-upload')  // Puedes cambiar el ícono
                    ->modalHeading('📥 Gestionar Remisión de Ingreso')
                    ->modalWidth('lg')
                    ->mountUsing(function (Forms\ComponentContainer $form, $record) {
                        $form->fill([
                            'remision_ingreso' => $record->remision_ingreso,
                            'remision_ingreso_nombre' => $record->remision_ingreso_nombre,
                        ]);
                    })
                    ->form([
                        TaskFileUpload::make('remision_ingreso')
                            ->label('Remisión Ingreso')
                            ->multiple()
                            ->acceptedFileTypes([
                                'application/pdf',
                                'application/msword',
                                'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                                'application/vnd.ms-excel',
                                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
                            ])

                             // se guarda en storage/app/public/remisiones/ingreso

                            ->enableDownload(),
                    ])
                    ->modalButton('Guardar')
                    ->action(function ($record, $data) {
                        Gate::authorize('update', $record);
                        $record->update([
                            'remision_ingreso' => $data['remision_ingreso'],
                            'remision_ingreso_nombre' => $data['remision_ingreso_nombre'] ?? [],
                        ]);

                        Notification::make()
                            ->title('¡Guardado correctamente!')
                            ->success()
                            ->body('La remisión de ingreso se ha actualizado.')
                            ->send();
                    })
                    ->visible(fn($record) => static::canEdit($record)),
                TableAction::make('manageRemisionSalida')
                    ->label('R S')
                    ->icon('heroicon-o-cloud-download') // Puedes cambiar el ícono
                    ->modalHeading('📤 Gestionar Remisión de Salida')
                    ->modalWidth('lg')
                    ->mountUsing(function (Forms\ComponentContainer $form, $record) {
                        $form->fill([
                            'remision_salida' => $record->remision_salida,
                            'remision_salida_nombre' => $record->remision_salida_nombre,
                        ]);
                    })
                    ->form([
                        TaskFileUpload::make('remision_salida')
                            ->label('Remisión Salida')
                            ->multiple()
                            ->acceptedFileTypes([
                                'application/pdf',
                                'application/msword',
                                'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                                'application/vnd.ms-excel',
                                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
                            ])



                            ->enableDownload(),
                    ])
                    ->modalButton('Guardar')
                    ->action(function ($record, $data) {
                        Gate::authorize('update', $record);
                        $record->update([
                            'remision_salida' => $data['remision_salida'],
                            'remision_salida_nombre' => $data['remision_salida_nombre'] ?? [],
                        ]);

                        Notification::make()
                            ->title('¡Guardado correctamente!')
                            ->success()
                            ->body('La remisión de salida se ha actualizado.')
                            ->send();
                    })

                    ->visible(fn($record) => static::canEdit($record)),


            ])
            ->bulkActions([
                Tables\Actions\DeleteBulkAction::make()
                    ->visible(fn() => auth()->user()->hasRole('SUPER ADMINISTRADOR')),
                \Filament\Tables\Actions\BulkAction::make('select_only')
                    ->label('Seleccionar')
                    ->action(fn() => null),
            ]);

    }


    public static function getEloquentQuery(): Builder
    {
        if (auth()->user()->hasRole('SUPER ADMINISTRADOR')) {
            return parent::getEloquentQuery();
        }

        return parent::getEloquentQuery()
            ->where('asignado_a', auth()->id());
    }


    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTasks::route('/'),
            'create' => Pages\CreateTask::route('/create'),
            'edit' => Pages\EditTask::route('/{record}/edit'),
            'view' => Pages\ViewTask::route('/{record}'), // Asegúrate de incluir "Pages\"
        ];
    }
    public static function shouldRegisterNavigation(): bool
    {
        return static::canViewAny();
    }
    public static function canDelete(Model $record): bool
    {
        // Solo SUPER ADMINISTRADOR puede eliminar
        return Gate::allows('delete', $record);
    }


}
