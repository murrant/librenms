<?php

namespace Database\Factories;

use App\Models\Credential;
use Illuminate\Database\Eloquent\Factories\Factory;

class CredentialFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Credential::class;

    /**
     * Define the model's default state.
     *
     * @return array
     */
    public function definition()
    {
        return [
            'credential_type' => $this->faker->randomElement(['snmp', 'ipmi', 'api', 'ssh', $this->faker->randomAscii()]),
            'description' => $this->faker->text(),
            'credentials' => ['username' => 'username', 'password' => 'password'],
        ];
    }
}
