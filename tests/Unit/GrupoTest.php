<?php

namespace Tests\Unit;

use App\Models\Curso;
use App\Models\Docente;
use App\Models\Estudiante;
use App\Models\EstudianteMatriculado;
use App\Models\Grupo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LogicException;
use Tests\TestCase;

class GrupoTest extends TestCase
{
    use RefreshDatabase;

    public function test_grupo_contiene_toda_su_informacion(): void
    {
        $curso = Curso::factory()->create([
            'sigla' => 'IF6100',
            'nombreCurso' => 'Análisis y Diseño de Sistemas',
            'creditos' => 4,
        ]);
        $docentes = Docente::factory()->count(2)->create();
        $estudiantes = Estudiante::factory()->count(Grupo::MIN_ESTUDIANTES)->create();

        $grupo = new Grupo([
            'numeroCiclo' => 2,
            'ano' => 2026,
            'numeroGrupo' => 1,
            'abierto' => true,
        ]);
        $grupo->curso()->associate($curso);
        $grupo->save();

        foreach ($docentes as $docente) {
            $grupo->asignarDocente($docente);
        }

        foreach ($estudiantes as $estudiante) {
            $grupo->matricularEstudiante($estudiante);
            $grupo->registrarNota($estudiante, 85.5);
        }

        $grupo = Grupo::with(['curso', 'docentes', 'estudiantes.consejero'])->find($grupo->id);

        $this->assertInstanceOf(Grupo::class, $grupo);
        $this->assertSame(2, $grupo->numeroCiclo);
        $this->assertSame(2026, $grupo->ano);
        $this->assertSame(1, $grupo->numeroGrupo);
        $this->assertTrue($grupo->abierto);

        $this->assertTrue($grupo->curso->is($curso));
        $this->assertSame('IF6100', $grupo->curso->sigla);
        $this->assertSame('Análisis y Diseño de Sistemas', $grupo->curso->nombreCurso);
        $this->assertSame(4, $grupo->curso->creditos);

        $this->assertCount(2, $grupo->docentes);
        $this->assertEqualsCanonicalizing($docentes->pluck('id')->all(), $grupo->docentes->pluck('id')->all());

        $this->assertCount(Grupo::MIN_ESTUDIANTES, $grupo->estudiantes);
        $this->assertTrue($grupo->tieneMatriculaMinima());

        foreach ($grupo->estudiantes as $estudiante) {
            $this->assertInstanceOf(EstudianteMatriculado::class, $estudiante->matricula);
            $this->assertSame(85.5, $estudiante->matricula->nota);
            $this->assertInstanceOf(Docente::class, $estudiante->consejero);
        }
    }

    public function test_grupo_no_admite_mas_de_dos_docentes(): void
    {
        $grupo = Grupo::factory()->create();
        $grupo->asignarDocente(Docente::factory()->create());
        $grupo->asignarDocente(Docente::factory()->create());

        $this->expectException(LogicException::class);

        $grupo->asignarDocente(Docente::factory()->create());
    }
}