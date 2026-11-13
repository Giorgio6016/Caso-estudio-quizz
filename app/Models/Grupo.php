<?php

namespace App\Models;

use Database\Factories\GrupoFactory;
use DomainException;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use InvalidArgumentException;

#[Fillable(['curso_id', 'numeroCiclo', 'ano', 'numeroGrupo', 'abierto'])] // @pest-mutate-ignore: RemoveArrayItem
class Grupo extends Model
{
    /** @use HasFactory<GrupoFactory> */
    use HasFactory;

    public const MAX_DOCENTES = 2; // @pest-mutate-ignore: IncrementInteger,DecrementInteger

    public const MIN_ESTUDIANTES = 15; // @pest-mutate-ignore: IncrementInteger,DecrementInteger

    public const NOTA_MINIMA = 0; // @pest-mutate-ignore: IncrementInteger,DecrementInteger

    public const NOTA_MAXIMA = 10; // @pest-mutate-ignore: IncrementInteger,DecrementInteger

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
        return $this->belongsToMany(Docente::class, 'grupo_docente')->withTimestamps();
    }

    /**
     * Estudiantes matriculados (15..*), con su nota.
     *
     * @return BelongsToMany<Estudiante, $this>
     */
    public function estudiantes(): BelongsToMany
    {
        return $this->belongsToMany(Estudiante::class, 'estudiantes_matriculados')
            ->using(EstudianteMatriculado::class)
            ->withPivot('nota')
            ->withTimestamps();
    }

    /**
     * RN-02: un grupo lo imparten 1 o 2 docentes.
     */
    public function asignarDocente(Docente $docente): void
    {
        if ($this->docentes()->count() >= self::MAX_DOCENTES) {
            throw new DomainException('Un grupo solo puede ser impartido por 1 o 2 docentes.');
        }

        $this->docentes()->attach($docente);
    }

    /**
     * RN-04: al matricular, la nota queda vacía (null).
     * RN-07: el índice único (grupo_id, estudiante_id) impide matricular dos veces.
     */
    public function matricularEstudiante(Estudiante $estudiante): void
    {
        $this->estudiantes()->attach($estudiante);
    }

    /**
     * RN-04 y RN-06: la nota se registra al finalizar el curso y va de 0 a 10.
     */
    public function registrarNota(Estudiante $estudiante, float $nota): void
    {
        if ($nota < self::NOTA_MINIMA || $nota > self::NOTA_MAXIMA) {
            throw new InvalidArgumentException('La nota debe estar entre 0 y 10.');
        }

        $this->estudiantes()->updateExistingPivot($estudiante->id, ['nota' => $nota]);
    }

    public function tieneMatriculaMinima(): bool
    {
        return $this->estudiantes()->count() >= self::MIN_ESTUDIANTES;
    }

    /**
     * RN-02 y RN-05: el grupo solo puede abrirse si tiene al menos 1 docente
     * y al menos 15 estudiantes matriculados.
     */
    public function puedeAbrirse(): bool
    {
        return $this->docentes()->exists() && $this->tieneMatriculaMinima();
    }

    public function abrir(): void
    {
        if (! $this->puedeAbrirse()) {
            throw new DomainException('El grupo necesita al menos 1 docente y '.self::MIN_ESTUDIANTES.' estudiantes para abrirse.');
        }

        $this->abierto = true;
        $this->save();
    }
}