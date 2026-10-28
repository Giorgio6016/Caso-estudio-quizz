<?php

namespace Database\Factories;

use App\Models\Curso;
use App\Models\Grupo;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Grupo>
 */
class GrupoFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'curso_id' => Curso::factory(),
            'numeroCiclo' => fake()->numberBetween(1, 2),
            'ano' => fake()->numberBetween(2020, 2026),
            'numeroGrupo' => fake()->numberBetween(1, 10),
            'abierto' => true,
        ];
    }
}
