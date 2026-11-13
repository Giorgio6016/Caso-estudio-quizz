<?php

use App\Models\Curso;
use App\Models\Docente;
use App\Models\Estudiante;
use App\Models\EstudianteMatriculado;
use App\Models\Grupo;
use Illuminate\Database\QueryException;
mutates(Grupo::class);

// ---------------------------------------------------------------
// Datos de prueba que se repiten (tal cual el enunciado)
// ---------------------------------------------------------------

function cursoIF6100(): Curso
{
    return Curso::create([
        'sigla' => 'IF6100',
        'nombreCurso' => 'Análisis y Diseño de Sistemas',
        'creditos' => 4,
    ]);
}

function grupo01(Curso $curso, bool $abierto = false): Grupo
{
    return Grupo::create([
        'curso_id' => $curso->id,
        'numeroCiclo' => 2,
        'ano' => 2026,
        'numeroGrupo' => 1,
        'abierto' => $abierto,
    ]);
}

function docente(int $codEmpleado): Docente
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

function estudianteC12345(Docente $consejero): Estudiante
{
    return Estudiante::create([
        'carne' => 'C12345',
        'nombre' => 'María',
        'apellidos' => 'Solís Quesada',
        'consejero_id' => $consejero->id,
    ]);
}

function matricularEstudiantes(Grupo $grupo, int $cantidad, Docente $consejero): void
{
    Estudiante::factory()
        ->count($cantidad)
        ->create(['consejero_id' => $consejero->id])
        ->each(fn (Estudiante $estudiante) => $grupo->matricularEstudiante($estudiante));
}

// ---------------------------------------------------------------
// CP-01 a CP-06 (primera entrega, migrados a Pest)
// ---------------------------------------------------------------

test('CP-01 registra un grupo con toda su información y su curso', function () {
    $curso = cursoIF6100();
    $creado = grupo01($curso, abierto: true);

    $grupo = Grupo::find($creado->id);

    expect($grupo->numeroCiclo)->toBe(2)
        ->and($grupo->ano)->toBe(2026)
        ->and($grupo->numeroGrupo)->toBe(1)
        ->and($grupo->abierto)->toBeTrue()
        ->and($grupo->curso->sigla)->toBe('IF6100')
        ->and($grupo->curso->creditos)->toBe(4);

    $this->assertDatabaseCount('grupos', 1);
});

test('CP-02 un curso tiene varios grupos y se distinguen los abiertos', function () {
    $curso = cursoIF6100();

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

    expect($grupos)->toHaveCount(3)
        ->and($abiertos)->toHaveCount(2)
        ->and($abiertos->pluck('numeroGrupo')->sort()->values()->all())->toBe([1, 2])
        ->and($grupos->pluck('curso_id')->unique()->values()->all())->toBe([$curso->id]);
});

test('CP-03 un grupo acepta dos docentes', function () {
    $grupo = grupo01(cursoIF6100());
    $docente1001 = docente(1001);
    $docente1002 = docente(1002);

    $grupo->asignarDocente($docente1001);
    $grupo->asignarDocente($docente1002);

    $docentes = $grupo->fresh()->docentes;

    expect($docentes)->toHaveCount(2)
        ->and($docentes->pluck('codEmpleado')->sort()->values()->all())->toBe([1001, 1002])
        ->and($docente1001->grupos->contains($grupo))->toBeTrue();

    $this->assertDatabaseCount('grupo_docente', 2);
});

test('CP-04 rechaza un tercer docente', function () {
    $grupo = grupo01(cursoIF6100());
    $grupo->asignarDocente(docente(1001));
    $grupo->asignarDocente(docente(1002));
    $docente1003 = docente(1003);

    expect(fn () => $grupo->asignarDocente($docente1003))->toThrow(DomainException::class);

    expect($grupo->fresh()->docentes)->toHaveCount(2);
    $this->assertDatabaseMissing('grupo_docente', ['docente_id' => $docente1003->id]);
});

test('CP-05 un grupo con 14 estudiantes no puede abrirse', function () {
    $grupo = grupo01(cursoIF6100());
    $docente1001 = docente(1001);
    $grupo->asignarDocente($docente1001);
    matricularEstudiantes($grupo, 14, $docente1001);

    expect($grupo->puedeAbrirse())->toBeFalse()
        ->and(fn () => $grupo->abrir())->toThrow(DomainException::class)
        ->and($grupo->fresh()->abierto)->toBeFalse();
});

test('CP-06 un grupo con 15 estudiantes y un docente se abre', function () {
    $grupo = grupo01(cursoIF6100());
    $docente1001 = docente(1001);
    $grupo->asignarDocente($docente1001);
    matricularEstudiantes($grupo, 15, $docente1001);

    expect($grupo->puedeAbrirse())->toBeTrue();

    $grupo->abrir();

    expect($grupo->fresh()->abierto)->toBeTrue();
});

// ---------------------------------------------------------------
// Parte A · CP-07 a CP-13
// ---------------------------------------------------------------

test('CP-07 un grupo sin docente no se abre aunque tenga cupo completo', function () {
    $grupo = grupo01(cursoIF6100());
    matricularEstudiantes($grupo, 20, docente(1001));

    expect($grupo->docentes)->toBeEmpty()
        ->and($grupo->puedeAbrirse())->toBeFalse()
        ->and(fn () => $grupo->abrir())->toThrow(DomainException::class)
        ->and($grupo->fresh()->abierto)->toBeFalse();
});

test('CP-08 un estudiante queda ligado a su profesor consejero', function () {
    $docente1001 = docente(1001);

    $estudiante = estudianteC12345($docente1001);

    expect($estudiante->consejero->codEmpleado)->toBe(1001);

    $aconsejados = $docente1001->estudiantes;

    expect($aconsejados)->toHaveCount(1)
        ->and($aconsejados->first()->carne)->toBe('C12345');

    $this->assertDatabaseHas('estudiantes', [
        'carne' => 'C12345',
        'consejero_id' => $docente1001->id,
    ]);
});

test('CP-09 cambiar de consejero reemplaza al anterior, no lo suma', function () {
    $docente1001 = docente(1001);
    $estudiante = estudianteC12345($docente1001);
    $docente1002 = docente(1002);

    $estudiante->consejero()->associate($docente1002);
    $estudiante->save();

    $docente1001 = $docente1001->fresh();
    $docente1002 = $docente1002->fresh();

    expect($estudiante->fresh()->consejero->codEmpleado)->toBe(1002)
        ->and($docente1001->estudiantes)->toHaveCount(0)
        ->and($docente1002->estudiantes)->toHaveCount(1);

    $this->assertDatabaseCount('estudiantes', 1);
    $this->assertDatabaseHas('estudiantes', ['carne' => 'C12345', 'consejero_id' => $docente1002->id]);
    $this->assertDatabaseMissing('estudiantes', ['carne' => 'C12345', 'consejero_id' => $docente1001->id]);
});

test('CP-10 al matricular, la nota queda vacía', function () {
    $grupo = grupo01(cursoIF6100());
    $estudiante = estudianteC12345(docente(1001));

    $grupo->matricularEstudiante($estudiante);

    $pivote = $grupo->estudiantes->first()->pivot;

    expect($pivote)->toBeInstanceOf(EstudianteMatriculado::class)
        ->and($pivote->nota)->toBeNull();

    $this->assertDatabaseCount('estudiantes_matriculados', 1);
    $this->assertDatabaseHas('estudiantes_matriculados', [
        'grupo_id' => $grupo->id,
        'estudiante_id' => $estudiante->id,
        'nota' => null,
    ]);
});

test('CP-11 registra la nota al finalizar el curso', function () {
    $grupo = grupo01(cursoIF6100());
    $estudiante = estudianteC12345(docente(1001));
    $grupo->matricularEstudiante($estudiante);

    $buscarMatricula = fn () => EstudianteMatriculado::where('grupo_id', $grupo->id)
        ->where('estudiante_id', $estudiante->id)
        ->first();

    $antes = $buscarMatricula();

    $grupo->registrarNota($estudiante, 8.5);

    $despues = $buscarMatricula();

    expect($despues->nota)->toBeFloat()->toBe(8.5)
        ->and($despues->only(['id', 'grupo_id', 'estudiante_id', 'created_at']))
        ->toEqual($antes->only(['id', 'grupo_id', 'estudiante_id', 'created_at']));

    $this->assertDatabaseCount('estudiantes_matriculados', 1);
});

test('CP-12 rechaza notas menores que 0 o mayores que 10', function (float $nota) {
    $grupo = grupo01(cursoIF6100());
    $estudiante = estudianteC12345(docente(1001));
    $grupo->matricularEstudiante($estudiante);

    expect(fn () => $grupo->registrarNota($estudiante, $nota))
        ->toThrow(InvalidArgumentException::class);

    expect($grupo->estudiantes()->first()->pivot->nota)->toBeNull();
})->with([-1, 10.5]);

test('CP-13 un estudiante no se matricula dos veces en el mismo grupo', function () {
    $grupo = grupo01(cursoIF6100());
    $estudiante = estudianteC12345(docente(1001));
    $grupo->matricularEstudiante($estudiante);

    expect(fn () => $grupo->matricularEstudiante($estudiante))->toThrow(QueryException::class);

    expect(EstudianteMatriculado::where('grupo_id', $grupo->id)
        ->where('estudiante_id', $estudiante->id)
        ->count())->toBe(1);
});

// ---------------------------------------------------------------
// Parte C3 · Tests para eliminar los mutantes que sobrevivieron
// ---------------------------------------------------------------

test('C3-01 acepta las notas en los límites 0 y 10', function (float $nota) {
    $grupo = grupo01(cursoIF6100());
    $estudiante = estudianteC12345(docente(1001));
    $grupo->matricularEstudiante($estudiante);

    $grupo->registrarNota($estudiante, $nota);

    expect($grupo->estudiantes()->first()->pivot->nota)->toBe($nota);
})->with([0.0, 10.0]);

test('C3-02 los atributos numéricos del grupo llegan como enteros aunque se asignen como texto', function () {
    $curso = cursoIF6100();

    $grupo = Grupo::create([
        'curso_id' => $curso->id,
        'numeroCiclo' => '2',
        'ano' => '2026',
        'numeroGrupo' => '1',
        'abierto' => '0',
    ]);

    expect($grupo->numeroCiclo)->toBe(2)
        ->and($grupo->ano)->toBe(2026)
        ->and($grupo->numeroGrupo)->toBe(1)
        ->and($grupo->abierto)->toBeFalse();
});

test('C3-03 abrir() explica en el mensaje la regla que no se cumple', function () {
    $grupo = grupo01(cursoIF6100());

    expect(fn () => $grupo->abrir())->toThrow(
        DomainException::class,
        'El grupo necesita al menos 1 docente y 15 estudiantes para abrirse.'
    );
});