<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Shareable demo deep links: /demo/teacher signs the visitor straight in as
 * that persona, so "look at the demo as a teacher" is one URL.
 */
class DemoRoleLinkTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function a_role_link_signs_the_visitor_in_as_that_persona(): void
    {
        config(['app.demo' => true]);

        $this->get('/demo/teacher')->assertRedirect(route('home'));
        $this->assertAuthenticated();
        $this->assertTrue(auth()->user()->hasRole('teacher'));
    }

    #[Test]
    public function a_pupil_link_goes_to_the_student_picker_and_unknown_roles_to_the_role_picker(): void
    {
        config(['app.demo' => true]);

        $this->get('/demo/pupil')->assertRedirect(route('student-login.select-grade'));
        $this->get('/demo/wizard')->assertRedirect(route('demo.picker'));
    }

    #[Test]
    public function the_links_do_not_exist_outside_demo_mode(): void
    {
        config(['app.demo' => false]);

        $this->get('/demo/teacher')->assertNotFound();
    }
}
