<?php

namespace LibreNMS\Tests\Feature\Graphing;

use App\Facades\LibrenmsConfig;
use App\Graphing\Contracts\GraphHandler;
use App\Graphing\Contracts\RenderPlan;
use App\Graphing\GraphAccess;
use App\Graphing\GraphDescription;
use App\Graphing\GraphQuery;
use App\Graphing\GraphRegistry;
use App\Graphing\GraphSubject;
use App\Models\Device;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LibreNMS\Tests\TestCase;
use LogicException;
use Spatie\Permission\Models\Role;

/**
 * Graph input is validated before a graph's subject is resolved or authorized.
 */
class GraphValidationOrderTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Role::findOrCreate('admin');
        LibrenmsConfig::set('auth_mechanism', 'mysql');
        LibrenmsConfig::set('webui.graph_type', 'svg');
        ValidationOrderHandler::$subjectCalls = 0;
        app(GraphRegistry::class)->register('device_validationorder', ValidationOrderHandler::class);
    }

    public function testInvalidGraphInputIsNotResolved(): void
    {
        $device = Device::factory()->create();

        $response = $this->actingAs($this->adminUser())
            ->get("/graph?type=device_validationorder&device=$device->device_id&level=abc");

        $response->assertStatus(500);
        $this->assertStringContainsString('level', $response->getContent());
        $this->assertSame(0, ValidationOrderHandler::$subjectCalls);
    }

    public function testInvalidInputIsReportedBeforeAuthorization(): void
    {
        $device = Device::factory()->create();

        $response = $this->actingAs(User::factory()->create(['enabled' => 1]))
            ->get("/graph?type=device_validationorder&device=$device->device_id&level=abc");

        $this->assertStringContainsString('level', $response->getContent());
        $this->assertStringNotContainsString('No Authorization', $response->getContent());
        $this->assertSame(0, ValidationOrderHandler::$subjectCalls);
    }

    public function testGraphsPageValidatesBeforeResolving(): void
    {
        $device = Device::factory()->create();

        $response = $this->actingAs($this->adminUser())
            ->get("/graphs?type=device_validationorder&device=$device->device_id&level=abc");

        $response->assertSessionHasErrors('level');
        $this->assertSame(0, ValidationOrderHandler::$subjectCalls);
    }

    public function testValidInputIsResolvedOnce(): void
    {
        $device = Device::factory()->create();

        $this->actingAs($this->adminUser())
            ->get("/graph?type=device_validationorder&device=$device->device_id&level=5");

        $this->assertSame(1, ValidationOrderHandler::$subjectCalls);
    }

    private function adminUser(): User
    {
        return User::factory()->create(['enabled' => 1])->assignRole('admin');
    }
}

class ValidationOrderHandler implements GraphHandler
{
    public static int $subjectCalls = 0;

    public function subject(GraphQuery $query, GraphAccess $access): GraphSubject
    {
        self::$subjectCalls++;

        return new GraphSubject;
    }

    public function authorize(GraphSubject $subject, GraphAccess $access): bool
    {
        return $access->isTrusted() || (bool) $access->user?->hasRole('admin');
    }

    public function describe(GraphSubject $subject): GraphDescription
    {
        return new GraphDescription('Validation Order');
    }

    public function plan(GraphSubject $subject, GraphQuery $query): RenderPlan
    {
        throw new LogicException('not rendered in these tests');
    }

    public function rules(): array
    {
        return ['level' => ['required', 'integer']];
    }
}
