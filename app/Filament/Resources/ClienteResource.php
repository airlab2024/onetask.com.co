<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ClienteResource\Pages;
use App\Models\Cliente;
use Filament\Forms;
use Filament\Tables;
use Filament\Resources\Form;
use Filament\Resources\Resource;
use Filament\Resources\Table;
use Illuminate\Database\Eloquent\Builder;

class ClienteResource extends Resource
{
    protected static ?string $model = Cliente::class;

    protected static ?string $navigationIcon = 'heroicon-o-user-group';

    protected static ?string $navigationLabel = 'Clientes';

    protected static ?string $pluralModelLabel = 'Clientes';

    protected static ?string $modelLabel = 'Cliente';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
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
                    ->maxLength(255),
                Forms\Components\TextInput::make('cargo')
                    ->label('Cargo')
                    ->maxLength(255),
                Forms\Components\TextInput::make('nit')
                    ->label('NIT')
                    ->maxLength(255),
                Forms\Components\TextInput::make('ciudad')
                    ->label('Ciudad')
                    ->maxLength(255),
                Forms\Components\TextInput::make('telefono')
                    ->label('Teléfono')
                    ->maxLength(255),
                Forms\Components\TextInput::make('correo_electronico')
                    ->label('Correo Electrónico')
                    ->email()
                    ->maxLength(255),
                Forms\Components\TextInput::make('encargado_de_cuenta')
                    ->label('Encargado de Cuenta')
                    ->maxLength(255),
                Forms\Components\Select::make('forma_pago')
                    ->label('Forma de Pago')
                    ->options([
                        'Contado' => 'Contado',
                        'Crédito a 30 Días' => 'Crédito a 30 Días',
                        'Convenio' => 'Convenio',
                    ])
                    ->required(false)
                    ->searchable(),

            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('nombre')
                    ->label('Nombre')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('abreviatura')
                    ->label('abreviatura')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('contacto')
                    ->label('Contacto')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('cargo')
                    ->label('Cargo')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('nit')
                    ->label('NIT')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('ciudad')
                    ->label('Ciudad')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('telefono')
                    ->label('Teléfono')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('correo_electronico')
                    ->label('Correo Electrónico')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('encargado_de_cuenta')
                    ->label('Encargado de Cuenta')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('forma_pago')
                    ->label('Forma de Pago')
                    ->sortable()
                    ->searchable(),
            ])
            ->filters([
                // Puedes agregar filtros aquí si es necesario
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\DeleteBulkAction::make(),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            // Puedes agregar RelationManagers si es necesario
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListClientes::route('/'),
            'create' => Pages\CreateCliente::route('/create'),
            'edit' => Pages\EditCliente::route('/{record}/edit'),
        ];
    }
    public static function shouldRegisterNavigation(): bool
    {
        return auth()->user()?->can('Cliente resource');
    }

}
