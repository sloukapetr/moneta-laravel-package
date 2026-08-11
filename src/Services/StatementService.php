<?php

namespace SloukaPetr\Moneta\Services;

use SloukaPetr\Moneta\Data\StatementData;
use SloukaPetr\Moneta\MonetaManager;

class StatementService
{
    public function __construct(protected MonetaManager $moneta)
    {
    }

    /** @return array<int, StatementData> */
    public function list(string $accountId, array $filters = []): array
    {
        $response = $this->moneta->get('api/v4/vip/aisp/my/accounts/'.$accountId.'/statements', $filters);

        return array_map(
            fn (array $statement) => StatementData::fromArray($statement),
            $response['accountStatements'] ?? []
        );
    }

    public function download(string $accountId, string $statementId, string $format = 'XML'): array
    {
        return $this->moneta->get('api/v4/vip/aisp/my/accounts/'.$accountId.'/statement/download', [
            'accountStatementId' => $statementId,
            'statementId' => $statementId,
            'format' => $format,
        ]);
    }

    public function downloadUrl(string $accountId, string $statementId, string $format = 'XML'): ?string
    {
        $response = $this->download($accountId, $statementId, $format);

        return $response['dataDownloadURL'] ?? null;
    }

    public function downloadFile(string $fileId): string
    {
        $response = $this->moneta->getRaw('api/v4/vip/aisp/my/accounts/statement', [
            'file' => $fileId,
        ], [
            'Accept' => 'application/octet-stream',
        ]);

        return $response->body();
    }
}
