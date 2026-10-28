<?php

namespace App\Models;

use Database\Factories\DocenteFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['codEmpleado', 'nombreEmpleado', 'apellidosEmpleado'])]
class Docente extends Model
{
    /** @use HasFactory<DocenteFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'codEmpleado' => 'integer',
        ];
    }

    /**
     * Estudiantes de los que es profesor consejero (0..*).
     *
     * @return HasMany<Estudiante, $this>
     */
    public function estudiantes(): HasMany
    {
        return $this->hasMany(Estudiante::class, 'consejero_id');
    }

    /**
     * Grupos que imparte (0..*).
     *
     * @return BelongsToMany<Grupo, $this>
     */
    public function grupos(): BelongsToMany
    {
        return $this->belongsToMany(Grupo::class)->withTimestamps();
    }
}
