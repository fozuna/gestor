<?php
declare(strict_types=1);

namespace Tests\Unit;

use App\Services\ClientTasksPdfReportService;
use PHPUnit\Framework\TestCase;

final class ClientTasksPdfReportServiceTest extends TestCase
{
    public function testBuildReportPayloadMapsStatusAndMetrics(): void
    {
        $service = new ClientTasksPdfReportService();
        $payload = $service->buildReportPayload(
            ['name' => 'Cliente Teste'],
            [
                ['title' => 'Tarefa A', 'status' => 'todo', 'created_at' => '2026-04-01 09:00:00', 'due_date' => '2099-04-10', 'description' => 'Desc', 'assignee_name' => 'Ana'],
                ['title' => 'Tarefa B', 'status' => 'doing', 'created_at' => '2026-04-02 09:00:00', 'due_date' => '2099-04-11', 'description' => 'Desc', 'assignee_name' => 'Bruno'],
                ['title' => 'Tarefa C', 'status' => 'done', 'created_at' => '2026-04-03 09:00:00', 'due_date' => '2026-04-09', 'description' => 'Desc', 'assignee_name' => 'Carla'],
                ['title' => 'Tarefa D', 'status' => 'todo', 'created_at' => '2026-04-04 09:00:00', 'due_date' => '2000-01-10', 'description' => 'Desc', 'assignee_name' => null],
            ],
            '2026-04-01',
            '2026-04-30',
            'TRAXTER',
            null,
            '15/04/2026 10:00:00'
        );

        self::assertSame(4, $payload['metrics']['total']);
        self::assertSame(1, $payload['metrics']['completed']);
        self::assertSame(1, $payload['metrics']['overdue']);
        self::assertSame(25.0, $payload['metrics']['completion_rate']);
        self::assertSame('Pendente', $payload['rows'][0]['status_label']);
        self::assertSame('Em andamento', $payload['rows'][1]['status_label']);
        self::assertSame('Concluída', $payload['rows'][2]['status_label']);
        self::assertSame('Atrasada', $payload['rows'][3]['status_label']);
        self::assertSame('Não atribuído', $payload['rows'][3]['assignee']);
    }

    public function testRenderHtmlWithEmptyListShowsInformativeMessage(): void
    {
        $service = new ClientTasksPdfReportService();
        $payload = $service->buildReportPayload(
            ['name' => 'Cliente Sem Tarefas'],
            [],
            '2026-01-01',
            '2026-01-31',
            'TRAXTER'
        );

        $html = $service->renderHtml($payload);
        self::assertStringContainsString('Não há tarefas cadastradas para o cliente no período selecionado.', $html);
        self::assertStringContainsString('Total de tarefas:</strong> 0', $html);
    }

    public function testBuildReportPayloadConvertsUtcTimestampToAppTimezoneDate(): void
    {
        $service = new ClientTasksPdfReportService();
        $payload = $service->buildReportPayload(
            ['name' => 'Cliente Timezone'],
            [
                [
                    'title' => 'Tarefa com horário UTC',
                    'status' => 'todo',
                    'created_at' => '2026-04-10 03:30:00',
                    'due_date' => '2026-04-12',
                    'description' => 'Desc',
                    'assignee_name' => 'Equipe',
                ],
            ],
            '2026-04-01',
            '2026-04-30',
            'TRAXTER',
            null,
            '15/04/2026 10:00:00'
        );

        self::assertSame('09/04/2026', $payload['rows'][0]['start_date']);
        self::assertSame('12/04/2026', $payload['rows'][0]['due_date']);
    }

    public function testRenderHtmlEscapesSpecialCharacters(): void
    {
        $service = new ClientTasksPdfReportService();
        $payload = $service->buildReportPayload(
            ['name' => 'Cliente & Cia'],
            [
                [
                    'title' => '<script>alert("x")</script>',
                    'description' => 'Descrição com <b>HTML</b> & símbolos',
                    'status' => 'doing',
                    'created_at' => '2026-04-01 12:00:00',
                    'due_date' => '2099-04-20',
                    'assignee_name' => 'José & Maria',
                ],
            ],
            '2026-04-01',
            '2026-04-30',
            'TRAXTER'
        );

        $html = $service->renderHtml($payload);
        self::assertStringContainsString('&lt;script&gt;alert(&quot;x&quot;)&lt;/script&gt;', $html);
        self::assertStringContainsString('Descrição com &lt;b&gt;HTML&lt;/b&gt; &amp; símbolos', $html);
        self::assertStringContainsString('José &amp; Maria', $html);
    }

    public function testBuildReportPayloadSupportsLargeTaskCollections(): void
    {
        $tasks = [];
        for ($i = 1; $i <= 3000; $i++) {
            $tasks[] = [
                'title' => 'Tarefa ' . $i,
                'description' => 'Descrição ' . $i,
                'status' => 'done',
                'created_at' => '2026-04-01 10:00:00',
                'due_date' => '2026-04-30',
                'assignee_name' => 'Equipe',
            ];
        }

        $payload = (new ClientTasksPdfReportService())->buildReportPayload(
            ['name' => 'Cliente Grande Volume'],
            $tasks,
            '2026-04-01',
            '2026-04-30',
            'TRAXTER'
        );

        self::assertSame(3000, $payload['metrics']['total']);
        self::assertSame(3000, $payload['metrics']['completed']);
        self::assertSame(100.0, $payload['metrics']['completion_rate']);
    }

    public function testPdfRenderForHundredTasksCompletesUnderFiveSeconds(): void
    {
        $tasks = [];
        for ($i = 1; $i <= 100; $i++) {
            $tasks[] = [
                'title' => 'Tarefa ' . $i,
                'description' => 'Descrição da tarefa ' . $i,
                'status' => $i % 3 === 0 ? 'done' : ($i % 3 === 1 ? 'todo' : 'doing'),
                'created_at' => '2026-04-01 10:00:00',
                'due_date' => '2099-04-30',
                'assignee_name' => 'Equipe',
            ];
        }

        $service = new ClientTasksPdfReportService();
        $payload = $service->buildReportPayload(
            ['name' => 'Cliente Performance'],
            $tasks,
            '2026-04-01',
            '2026-04-30',
            'TRAXTER'
        );
        $html = $service->renderHtml($payload);

        $start = microtime(true);
        $pdf = $service->renderPdfBinaryFromHtml($html);
        $elapsed = microtime(true) - $start;

        self::assertNotEmpty($pdf);
        self::assertStringStartsWith('%PDF', $pdf);
        self::assertLessThan(5.0, $elapsed, 'Geração do PDF excedeu 5 segundos para 100 tarefas.');
    }
}
