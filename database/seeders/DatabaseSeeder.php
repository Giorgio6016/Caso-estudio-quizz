<?php

namespace Database\Seeders;

use App\Models\Docente;
use App\Models\Estudiante;
use App\Models\Grupo;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $docentes = Docente::factory()->count(5)->create();
        $estudiantes = Estudiante::factory()->count(20)->recycle($docentes)->create();

        $grupo = Grupo::factory()->create();
        $grupo->asignarDocente($docentes[0]);
        $grupo->asignarDocente($docentes[1]);

        foreach ($estudiantes as $estudiante) {
            $grupo->matricularEstudiante($estudiante);
            $grupo->registrarNota($estudiante, fake()->randomFloat(2, 0, 100));
        }
    }
}