<?php

namespace Database\Factories;

use App\Models\Contact;
use Illuminate\Database\Eloquent\Factories\Factory;

class ContactFactory extends Factory
{
    protected $model = Contact::class;

    public function definition(): array
    {
        return [
            'organization_id' => 1,
            'location_id' => null,
            'name' => fake()->name(),
            'type' => fake()->randomElement(Contact::types()),
            'job_title' => fake()->optional()->jobTitle(),
            'phone' => fake()->optional()->phoneNumber(),
            'fax' => fake()->optional()->phoneNumber(),
            'email' => fake()->optional()->safeEmail(),
            'clinic_name' => fake()->optional()->company(),
            'provider_id' => fake()->optional()->numerify('NPI-##########'),
            'address_line1' => fake()->optional()->streetAddress(),
            'city' => fake()->optional()->city(),
            'state' => fake()->optional()->stateAbbr(),
            'county' => fake()->optional()->word(),
            'zip' => fake()->optional()->postcode(),
            'notes' => fake()->optional()->sentence(),
            'is_active' => true,
        ];
    }

    public function forOrganization(int $organizationId): static
    {
        return $this->state(['organization_id' => $organizationId]);
    }

    public function forLocation(?int $locationId): static
    {
        return $this->state(['location_id' => $locationId]);
    }

    public function inactive(): static
    {
        return $this->state(['is_active' => false]);
    }

    public function ofType(string $type): static
    {
        return $this->state(['type' => $type]);
    }
}
