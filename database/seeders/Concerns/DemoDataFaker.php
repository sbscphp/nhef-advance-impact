<?php

namespace Database\Seeders\Concerns;

use Faker\Factory;
use Faker\Generator;

/** Shared Faker/randomisation helpers for the demo data seeders. */
trait DemoDataFaker
{
    private Generator $faker;

    private function seedFaker(): void
    {
        $this->faker = Factory::create('en_NG');
    }

    /**
     * Calls a Faker formatter by name, e.g. `fakerName('jobTitle')` or, for a chained proxy like
     * `$faker->unique()->safeEmail()`, `fakerName('unique.safeEmail')`. `$args` apply to the final
     * segment only.
     *
     * @param  list<mixed>  $args
     */
    private function fakerName(string $method, array $args = []): mixed
    {
        $segments = explode('.', $method);
        $last = array_pop($segments);

        $target = $this->faker;
        foreach ($segments as $segment) {
            $target = $target->{$segment}();
        }

        return $target->{$last}(...$args);
    }

    /**
     * @template T
     *
     * @param  list<T>  $items
     * @return list<T>
     */
    private function fakerShuffle(array $items): array
    {
        $shuffled = $items;
        shuffle($shuffled);

        return $shuffled;
    }

    private function nigerianPhoneNumber(): string
    {
        $prefixes = ['0803', '0806', '0810', '0813', '0814', '0816', '0903', '0906', '0913'];

        return $prefixes[array_rand($prefixes)].str_pad((string) random_int(0, 9999999), 7, '0', STR_PAD_LEFT);
    }
}
