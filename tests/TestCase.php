<?php

namespace Javaabu\Workflows\Tests;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Javaabu\FormBuilder\FormBuilderServiceProvider;
use Javaabu\Forms\FormsServiceProvider;
use Javaabu\Manifest\ManifestServiceProvider;
use Javaabu\Permissions\Models\Permission;
use Javaabu\Permissions\Models\Role;
use Javaabu\Permissions\PermissionsServiceProvider;
use Javaabu\Stencil\StencilServiceProvider;
use Javaabu\Translatable\TranslatableServiceProvider;
use Javaabu\Workflows\Tests\TestSupport\Models\Admin;
use Javaabu\Workflows\Tests\TestSupport\Models\LeaveRequest;
use Javaabu\Workflows\Tests\TestSupport\Models\User;
use Javaabu\Workflows\Tests\TestSupport\Providers\TestServiceProvider;
use Javaabu\Workflows\WorkflowsServiceProvider;
use Laravel\Ai\AiServiceProvider;
use Orchestra\Testbench\TestCase as BaseTestCase;
use Spatie\Permission\PermissionServiceProvider;

abstract class TestCase extends BaseTestCase
{
    use RefreshDatabase;

    protected function defineEnvironment($app): void
    {
        $app['config']->set('app.key', 'base64:yWa/ByhLC/GUvfToOuaPD7zDwB64qkc/QkaQOrT5IpE=');
        $app['config']->set('database.default', 'testing');
        $app['config']->set('permission.models.permission', Permission::class);
        $app['config']->set('permission.models.role', Role::class);
        $app['config']->set('permissions.guards', ['web', 'web_admin']);

        $app['config']->set('auth.guards', [
            'web' => ['driver' => 'session', 'provider' => 'users'],
            'web_admin' => ['driver' => 'session', 'provider' => 'admins'],
        ]);

        $app['config']->set('auth.providers', [
            'users' => ['driver' => 'eloquent', 'model' => User::class],
            'admins' => ['driver' => 'eloquent', 'model' => Admin::class],
        ]);
    }

    protected function getPackageProviders($app): array
    {
        $providers = [
            PermissionServiceProvider::class,
            PermissionsServiceProvider::class,
            ManifestServiceProvider::class,
            FormsServiceProvider::class,
            StencilServiceProvider::class,
            FormBuilderServiceProvider::class,
            WorkflowsServiceProvider::class,
            TestServiceProvider::class,
        ];

        if (class_exists(TranslatableServiceProvider::class)) {
            $providers[] = TranslatableServiceProvider::class;
        }

        if (class_exists(AiServiceProvider::class)) {
            $providers[] = AiServiceProvider::class;
        }

        return $providers;
    }

    /**
     * @param  array<int, string>  $permissions
     */
    protected function makeUser(string $name = 'އަޙްމަދު', array $permissions = []): User
    {
        $user = User::create(['name' => $name, 'email' => uniqid() . '@example.com', 'password' => 'secret']);

        if ($permissions !== []) {
            $user->givePermissionTo($permissions);
        }

        return $user;
    }

    /**
     * @param  array<int, string>  $permissions
     */
    protected function makeAdmin(string $name = 'Aisha', array $permissions = []): Admin
    {
        $admin = Admin::create(['name' => $name, 'email' => uniqid() . '@example.com', 'password' => 'secret']);

        if ($permissions !== []) {
            $admin->givePermissionTo($permissions);
        }

        return $admin;
    }

    protected function leaveRequest(int $days = 3, ?User $manager = null): LeaveRequest
    {
        return LeaveRequest::create([
            'title' => 'ހުކުރު ދުވަހުގެ ޗުއްޓީ',
            'days' => $days,
            'manager_id' => ($manager ?? $this->makeUser('މެނޭޖަރު'))->getKey(),
        ]);
    }
}
