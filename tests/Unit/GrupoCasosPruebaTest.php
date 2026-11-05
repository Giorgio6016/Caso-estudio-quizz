<?php

namespace Tests\Unit;

use App\Models\Curso;
use App\Models\Docente;
use App\Models\Estudiante;
use App\Models\Grupo;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GrupoCasosPruebaTest extends TestCase
{
    use RefreshDatabase;

    private function crearCursoIF6100(): Curso
    {
        return Curso::create([
            'sigla' => 'IF6100',
            'nombreCurso' => 'Análisis y Diseño de Sistemas',
            'creditos' => 4,
        ]);
    }

    private function crearGrupo01(Curso $curso, bool $abierto = false): Grupo
    {
        return Grupo::create([
            'curso_id' => $curso->id,
            'numeroCiclo' => 2,
            'ano' => 2026,
            'numeroGrupo' => 1,
            'abierto' => $abierto,
        ]);
    }

    private function crearDocente(int $codEmpleado): Docente
    {
        $datos = [
            1001 => ['Ana', 'Mora Vargas'],
            1002 => ['Luis', 'Rojas Solano'],
            1003 => ['Carla', 'Jiménez Araya'],
        ];

        return Docente::create([
            'codEmpleado' => $codEmpleado,
            'nombreEmpleado' => $datos[$codEmpleado][0],
            'apellidosEmpleado' => $datos[$codEmpleado][1],
        ]);
    }

    private function matricularEstudiantes(Grupo $grupo, int $cantidad, Docente $consejero): void
    {
        $estudiantes = Estudiante::factory()
            ->count($cantidad)
            ->create(['consejero_id' => $consejero->id]);

        $grupo->estudiantes()->attach($estudiantes->pluck('id'));
    }


    public function test_cp01_registrar_grupo_con_su_informacion_y_su_curso(): void
    {
        $curso = $this->crearCursoIF6100();
        $creado = $this->crearGrupo01($curso, abierto: true);

        $grupo = Grupo::find($creado->id);

        $this->assertSame(2, $grupo->numeroCiclo);
        $this->assertSame(2026, $grupo->ano);
        $this->assertSame(1, $grupo->numeroGrupo);
        $this->assertTrue($grupo->abierto);

        $this->assertSame('IF6100', $grupo->curso->sigla);
        $this->assertSame(4, $grupo->curso->creditos);

        $this->assertDatabaseCount('grupos', 1);
    }


    public function test_cp02_curso_tiene_varios_grupos_y_se_distinguen_los_abiertos(): void
    {
        $curso = $this->crearCursoIF6100();

        foreach ([1 => true, 2 => true, 3 => false] as $numeroGrupo => $abierto) {
            Grupo::create([
                'curso_id' => $curso->id,
                'numeroCiclo' => 2,
                'ano' => 2026,
                'numeroGrupo' => $numeroGrupo,
                'abierto' => $abierto,
            ]);
        }

        $grupos = $curso->fresh()->grupos;
        $abiertos = $grupos->where('abierto', true);

        $this->assertCount(3, $grupos);
        $this->assertCount(2, $abiertos);
        $this->assertEqualsCanonicalizing([1, 2], $abiertos->pluck('numeroGrupo')->all());

        $this->assertSame([$curso->id], $grupos->pluck('curso_id')->unique()->values()->all());
    }


    public function test_cp03_grupo_acepta_dos_docentes(): void
    {
        $grupo = $this->crearGrupo01($this->crearCursoIF6100());
        $docente1001 = $this->crearDocente(1001);
        $docente1002 = $this->crearDocente(1002);

        $grupo->asignarDocente($docente1001);
        $grupo->asignarDocente($docente1002);

        $docentes = $grupo->fresh()->docentes;

        $this->assertCount(2, $docentes);
        $this->assertEqualsCanonicalizing([1001, 1002], $docentes->pluck('codEmpleado')->all());
        $this->assertTrue($docente1001->grupos->contains($grupo));

        $this->assertDatabaseCount('docente_grupo', 2);
    }


    public function test_cp04_grupo_rechaza_un_tercer_docente(): void
    {
        $grupo = $this->crearGrupo01($this->crearCursoIF6100());
        $grupo->asignarDocente($this->crearDocente(1001));
        $grupo->asignarDocente($this->crearDocente(1002));
        $docente1003 = $this->crearDocente(1003);

        $this->assertThrows(
            fn () => $grupo->asignarDocente($docente1003),
            DomainException::class
        );

        $this->assertCount(2, $grupo->fresh()->docentes);
        $this->assertDatabaseMissing('docente_grupo', ['docente_id' => $docente1003->id]);
    }


    public function test_cp05_grupo_con_14_estudiantes_no_puede_abrirse(): void
    {
        $grupo = $this->crearGrupo01($this->crearCursoIF6100());
        $docente1001 = $this->crearDocente(1001);
        $grupo->asignarDocente($docente1001);
        $this->matricularEstudiantes($grupo, 14, $docente1001);

        $this->assertFalse($grupo->puedeAbrirse());

        $this->assertThrows(fn () => $grupo->abrir(), DomainException::class);

        $this->assertFalse($grupo->fresh()->abierto);
    }


    public function test_cp06_grupo_con_15_estudiantes_y_un_docente_se_abre(): void
    {
        $grupo = $this->crearGrupo01($this->crearCursoIF6100());
        $docente1001 = $this->crearDocente(1001);
        $grupo->asignarDocente($docente1001);
        $this->matricularEstudiantes($grupo, 15, $docente1001);

        $this->assertTrue($grupo->puedeAbrirse());

        $grupo->abrir();

        $this->assertTrue($grupo->fresh()->abierto);
    }
}