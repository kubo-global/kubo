<?php

namespace Tests\Feature;

use App\Models\Enrollment;
use App\Models\Offering;
use App\Models\Profile;
use App\Models\User;
use Tests\TestCase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;

class StudentsTest extends TestCase
{
    use WithFaker, RefreshDatabase;

    protected array $attributes, $assert_attributes;
    protected Offering $offering;
    protected Profile $profile;
    protected array $routes;
    protected User $student;
    protected string $class_name;
    protected string $table_name;

    public function setUp(): void
    {
        parent::setUp();

        $this->class_name = 'App\Models\Student';
        $this->table_name = 'users';

        $this->offering = Offering::factory()->create([
            'schoolyear_id' => $this->schoolyear->id
        ]);

        $this->attributes = [
            'first_name' => $this->faker->firstName,
            'last_name' => $this->faker->lastName,
            'offering_id' => $this->offering->id,
            'gender' => $this->faker->randomElement($array = array('M', 'F')),
            'birth_date' => $this->faker->dateTimeBetween('1930-01-01', '2012-12-31')->format('Y-m-d'),
            'address' => $this->faker->address,
            'tribe' => $this->faker->word,
            'comment' => $this->faker->text(40),
        ];

        $this->assert_attributes = [
            'first_name' => $this->attributes["first_name"],
            'last_name' => $this->attributes["last_name"]
        ];

        $this->profile = Profile::factory()->create(['user_id' => $this->student->id]);
        Enrollment::create(['user_id' => $this->student->id, 'offering_id' => $this->offering->id]);

        $this->routes = [
            'index' => 'students.index',
            'show' => 'students.show',
            'create' => 'students.create' //,
            // 'post' => 'headmaster.students.post',
            // 'edit' => 'headmaster.students.edit',
            // 'patch' => 'headmaster.students.patch',
            // 'delete' => 'headmaster.students.delete'
        ];
    }

    #[Test]
    public function its_index_page_can_be_accessed_by_a_headmaster()
    {
        $this->assert_that_specific_user_can_access_endpoint($this->headmaster, route($this->routes["index"]));
    }

    #[Test]
    public function its_create_can_be_accessed_by_a_headmaster()
    {
        $this->assert_that_specific_user_can_access_endpoint($this->headmaster, route($this->routes['create']));
    }

    #[Test]
    public function its_create_page_can_not_be_accessed_by_a_user_a_teacher_or_user_without_roles()
    {
        $this->assert_that_specific_user_can_not_access_endpoint($this->teacher, route($this->routes['create']));
        $this->assert_that_specific_user_can_not_access_endpoint($this->user_without_roles, route($this->routes['create']));
    }

    #[Test]
    public function its_show_can_be_accessed_by_a_headmaster()
    {
        $this->assert_that_specific_user_can_access_endpoint($this->headmaster, route($this->routes['show'], $this->student->id));
    }

    #[Test]
    public function its_show_can_be_accessed_by_a_teacher()
    {
        $this->assert_that_specific_user_can_access_endpoint($this->teacher, route($this->routes['show'], $this->student->id));
    }


    #[Test]
    public function its_show_can_be_accessed_by_an_admin()
    {
        $this->assert_that_specific_user_can_access_endpoint($this->admin, route($this->routes['show'], $this->student->id));
    }

    #[Test]
    public function its_show_page_can_not_be_accessed_by_a_user_a_student()
    {
        $this->assert_that_specific_user_can_not_access_endpoint($this->student, route($this->routes['show'], $this->student->id));
    }

    #[Test]
    public function its_show_page_can_not_be_accessed_by_a_user_a_user_without_roles()
    {
        $this->assert_that_specific_user_can_not_access_endpoint($this->user_without_roles, route($this->routes['show'], $this->student->id));
    }

    // #[Test]
    // public function its_edit_can_be_accessed_by_a_headmaster()
    // {
    //     $this->assert_that_specific_user_can_access_endpoint($this->headmaster, route($this->routes['edit'], $this->student->id));
    // }

    // #[Test]
    // public function its_edit_page_can_not_be_accessed_by_a_user_a_teacher_or_an_admin()
    // {
    //     $this->assert_that_specific_user_can_not_access_endpoint($this->admin, route($this->routes['edit'], $this->student->id));
    //     $this->assert_that_specific_user_can_not_access_endpoint($this->teacher, route($this->routes['edit'], $this->student->id));
    //     $this->assert_that_specific_user_can_not_access_endpoint($this->user_without_roles, route($this->routes['edit'], $this->student->id));
    // }

    // #[Test]
    // public function it_can_be_saved_by_a_headmaster()
    // {
    //     $this->assert_that_specific_user_can_post($this->headmaster, $this->class_name, $this->table_name, route($this->routes['post']), $this->attributes, $this->assert_attributes);
    //     $this->assertDatabaseHas('profiles', [
    //         'gender' => $this->attributes['gender'],
    //         'birth_date' => $this->attributes['birth_date'],
    //         'address' => $this->attributes['address'],
    //         'tribe' => $this->attributes['tribe'],
    //         'comment' => $this->attributes['comment']
    //     ]);
    //     $this->assertDatabaseHas('enrollments', [
    //         'user_id' => Student::all()->last()->id,
    //         'offering_id' => $this->attributes['offering_id']
    //     ]);
    // }

    // #[Test]
    // public function it_can_be_updated_by_a_headmaster()
    // {
    //     $attributes = [
    //         'first_name' => $this->faker->firstName,
    //         'last_name' => $this->faker->lastName,
    //         'offering_id' => $this->offering->id,
    //         'gender' => $this->faker->randomElement($array = array('M', 'F')),
    //         'birth_date' => $this->faker->dateTimeBetween('1930-01-01', '2012-12-31')->format('Y-m-d'),
    //         'address' => $this->faker->address,
    //         'tribe' => $this->faker->word,
    //         'comment' => $this->faker->text(40),
    //     ];
    //     $assert_attributes = [
    //         'first_name' => $this->attributes['first_name'],
    //         'last_name' => $this->attributes['last_name'],
    //     ];

    //     $this->assert_that_specific_user_can_patch($this->headmaster, $this->class_name, $this->table_name, route($this->routes['patch'], $this->student->id), $attributes, $assert_attributes);
    // }

    private function assert_that_specific_user_can_access_endpoint(User $user, string $route) {
        $this->actingAs($user)->get($route)->assertOk();
    }

    private function assert_that_specific_user_can_not_access_endpoint(User $user, string $route) {
        $this->actingAs($user)->get($route)->assertForbidden();
    }
    
}
