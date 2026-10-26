<?php

namespace Database\Factories;

use App\Models\Client;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Client>
 */
class ClientFactory extends Factory
{
    protected $model = Client::class;

    public const FIRST_NAMES = ['Ana', 'Maria', 'João', 'José', 'Paulo', 'Celeste', 'Albertina', 'Ernesto', 'Fátima', 'Lurdes', 'Armando',
        'Benedito', 'Cristina', 'Dércio', 'Edson', 'Graça', 'Hélder', 'Inocência', 'Jacinto', 'Lucinda', 'Momade', 'Rosita', 'Sérgio', 'Teresa'];

    public const LAST_NAMES = ['Sumbane', 'Machava', 'Cossa', 'Mondlane', 'Macuácua', 'Nhantumbo', 'Muianga', 'Tembe', 'Langa', 'Matsinhe',
        'Cumbe', 'Mabunda', 'Sitoe', 'Bila', 'Uamusse', 'Zandamela', 'Massingue', 'Mucavele', 'Novela', 'Chiziane'];

    private static int $seq = 0;

    public function definition(): array
    {
        self::$seq++;
        $name = fake()->randomElement(self::FIRST_NAMES).' '.fake()->randomElement(self::LAST_NAMES).' '.fake()->randomElement(self::LAST_NAMES);

        return [
            'client_number' => sprintf('CLI-T%06d', self::$seq.random_int(100, 999)),
            'full_name' => $name,
            'document_type' => 'bi',
            'document_number' => strtoupper(fake()->unique()->bothify('1101########?')),
            'nuit' => (string) fake()->unique()->numberBetween(100000000, 999999999),
            'birth_date' => fake()->dateTimeBetween('-60 years', '-21 years')->format('Y-m-d'),
            'nationality' => 'Moçambicana',
            'phone' => fake()->randomElement(['82', '84', '85', '86', '87']).fake()->numerify('#######'),
            'email' => null,
            'occupation' => fake()->randomElement(['Comerciante', 'Professor(a)', 'Enfermeiro(a)', 'Motorista', 'Costureira', 'Electricista', 'Agricultor(a)']),
            'monthly_income' => (string) fake()->numberBetween(8000, 60000),
            'status' => 'active',
            'kyc_status' => 'verified',
            'is_demo' => true,
        ];
    }

    /** Cliente com conta de acesso ao portal. */
    public function withUser(?string $password = null): static
    {
        return $this->afterMaking(function (Client $client) use ($password) {
            $user = User::factory()->create([
                'name' => $client->full_name,
                'email' => $client->email ?? strtolower(str_replace(' ', '.', iconv('UTF-8', 'ASCII//TRANSLIT', $client->full_name))).random_int(10, 999).'@demo.sumbanecredit.local',
                'phone' => $client->phone,
            ] + ($password ? ['password' => $password] : []));
            $user->assignRole(User::CLIENT_ROLE);
            $client->user_id = $user->id;
            $client->email = $user->email;
        });
    }
}
