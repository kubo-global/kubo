<?php

namespace Tests\Feature;

use App\Models\Profile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ProfileGenderTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function gender_is_normalized_to_a_canonical_letter_on_write(): void
    {
        $cases = [
            ['f', 'F'], ['F', 'F'], ['female', 'F'], ['Female', 'F'],
            ['m', 'M'], ['M', 'M'], ['male', 'M'], ['MALE', 'M'],
            ['other', 'O'], ['Other', 'O'],
            ['', null], ['x', null], [null, null], ['  female ', 'F'],
        ];

        foreach ($cases as [$input, $expected]) {
            $profile = new Profile();
            $profile->gender = $input;
            $this->assertSame($expected, $profile->gender, 'input: '.var_export($input, true));
        }
    }

    #[Test]
    public function female_and_male_helpers_use_the_canonical_value(): void
    {
        $female = new Profile();
        $female->gender = 'female';
        $this->assertTrue($female->isFemale());
        $this->assertFalse($female->isMale());

        $male = new Profile();
        $male->gender = 'm';
        $this->assertTrue($male->isMale());
        $this->assertFalse($male->isFemale());
    }
}
