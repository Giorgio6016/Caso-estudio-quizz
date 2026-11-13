<?php

namespace App\Models;

use Database\Factories\EstudianteFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['carne', 'nombre', 'apellidos', 'consejero_id'])]
class Estudiante extends Model
{
    /** @use HasFactory<EstudianteFactory> */
    use HasFactory;

    /**
     * Profesor consejero (exactamente 1).
     *
     * @return BelongsTo<Docente, $this>
     */
    public function consejero(): BelongsTo
    {
        return $this->belongsTo(Docente::class, 'consejero_id');
    }

    /**
     * Grupos en los que está matriculado (0..*), con su nota.
     *
     * @return BelongsToMany<Grupo, $this>
     */
    public function grupos(): BelongsToMany
    {
        return $this->belongsToMany(Grupo::class, 'estudiantes_matriculados')
            ->using(EstudianteMatriculado::class)
            ->withPivot('nota')
            ->withTimestamps();
    }

    /**
     * @return HasMany<EstudianteMatriculado, $this>
     */
    public function matriculas(): HasMany
    {
        return $this->hasMany(EstudianteMatriculado::class);
    }
}