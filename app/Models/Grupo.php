<?php

namespace App\Models;

use Database\Factories\GrupoFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use LogicException;

#[Fillable(['curso_id', 'numeroCiclo', 'ano', 'numeroGrupo', 'abierto'])]
class Grupo extends Model
{
    /** @use HasFactory<GrupoFactory> */
    use HasFactory;

    public const MAX_DOCENTES = 2;

    public const MIN_ESTUDIANTES = 15;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'numeroCiclo' => 'integer',
            'ano' => 'integer',
            'numeroGrupo' => 'integer',
            'abierto' => 'boolean',
        ];
    }

    /**
     * Curso al que pertenece el grupo (exactamente 1).
     *
     * @return BelongsTo<Curso, $this>
     */
    public function curso(): BelongsTo
    {
        return $this->belongsTo(Curso::class);
    }

    /**
     * Docentes que imparten el grupo (1..2).
     *
     * @return BelongsToMany<Docente, $this>
     */
    public function docentes(): BelongsToMany
    {
        return $this->belongsToMany(Docente::class)->withTimestamps();
    }

    /**
     * Estudiantes matriculados (15..*), con su nota.
     *
     * @return BelongsToMany<Estudiante, $this>
     */
    public function estudiantes(): BelongsToMany
    {
        return $this->belongsToMany(Estudiante::class, 'estudiante_matriculados')
            ->using(EstudianteMatriculado::class)
            ->as('matricula')
            ->withPivot('id', 'nota')
            ->withTimestamps();
    }

    public function asignarDocente(Docente $docente): void
    {
        if ($this->docentes()->count() >= self::MAX_DOCENTES) {
            throw new LogicException('Un grupo solo puede ser impartido por 1 o 2 docentes.');
        }

        $this->docentes()->attach($docente);
    }

    public function matricularEstudiante(Estudiante $estudiante): void
    {
        if (! $this->abierto) {
            throw new LogicException('No se puede matricular en un grupo cerrado.');
        }

        $this->estudiantes()->attach($estudiante);
    }

    public function registrarNota(Estudiante $estudiante, float $nota): void
    {
        if ($nota < 0 || $nota > 100) {
            throw new LogicException('La nota debe estar entre 0 y 100.');
        }

        $this->estudiantes()->updateExistingPivot($estudiante->id, ['nota' => $nota]);
    }

    public function tieneMatriculaMinima(): bool
    {
        return $this->estudiantes()->count() >= self::MIN_ESTUDIANTES;
    }
}