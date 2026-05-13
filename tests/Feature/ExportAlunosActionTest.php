<?php

namespace Tests\Feature;

use App\Filament\Actions\ExportAlunosAction;
use App\Jobs\ExportAlunosJob;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;
use ReflectionClass;

class ExportAlunosActionTest extends TestCase
{
    use RefreshDatabase;

    private function invokeMethod(&$object, $methodName, array $parameters = array())
    {
        $reflection = new ReflectionClass(get_class($object));
        $method = $reflection->getMethod($methodName);
        $method->setAccessible(true);
        return $method->invokeArgs($object, $parameters);
    }

    public function test_action_calculates_estimated_time_correctly()
    {
        $action = ExportAlunosAction::make('export_alunos');

        // 2000 -> max(30, 1.5) = 30s -> 1 min
        $this->assertEquals('1 min', $this->invokeMethod($action, 'calculateEstimatedTime', [2000]));

        // 100000 -> 75s -> ceil(75/60) = 2 min
        $this->assertEquals('2 min', $this->invokeMethod($action, 'calculateEstimatedTime', [100000]));

        // 200000 -> 150s -> ceil(150/60) = 3 min
        $this->assertEquals('3 min', $this->invokeMethod($action, 'calculateEstimatedTime', [200000]));
        
        // 1000000 -> 750s -> ceil(750/60) = 13 min
        $this->assertEquals('13 min', $this->invokeMethod($action, 'calculateEstimatedTime', [1000000]));
    }

    public function test_action_calculates_estimated_size_correctly()
    {
        $action = ExportAlunosAction::make('export_alunos');

        // 2000 * 450 = 900000 bytes = 0.858 MB -> round(0.858 * 1024) = 879 KB
        $this->assertEquals('879 KB', $this->invokeMethod($action, 'calculateEstimatedSize', [2000]));

        // 100000 * 450 = 45000000 bytes = 42.9 MB -> round(42.9) = 43 MB
        $this->assertEquals('43 MB', $this->invokeMethod($action, 'calculateEstimatedSize', [100000]));
    }

}
