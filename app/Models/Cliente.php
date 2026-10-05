<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Cliente extends Model
{
    use HasFactory;

    protected $fillable = [
        'nombre',
        'abreviatura',
        'telefono',
        'contacto',
        'cargo',
        'nit',
        'ciudad',
        'correo_electronico',
        'encargado_de_cuenta',
        'forma_pago', // 👈 agregamos el nuevo campo aquí
    ];
    protected $casts = [
        'abreviatura' => 'string',
    ];

    public function tasks()
    {
        return $this->hasMany(Task::class);
    }
}
