<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use App\Models\VideoProject;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProjectAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_cannot_view_other_users_project(): void
    {
        $role = Role::create(['name' => 'User', 'slug' => 'user']);
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $owner->roles()->attach($role);
        $other->roles()->attach($role);

        $project = VideoProject::create([
            'user_id' => $owner->id,
            'title' => 'Private Project',
            'slug' => 'private-project',
            'source_type' => 'upload',
            'status' => 'draft',
        ]);

        $this->actingAs($other)->get(route('projects.show', $project))->assertForbidden();
    }
}
