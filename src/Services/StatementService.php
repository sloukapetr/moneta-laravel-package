<?php

namespace SloukaPetr\Moneta\Services;

use SloukaPetr\Moneta\Data\StatementData;
use SloukaPetr\Moneta\Data\StatementJsonData;
use SloukaPetr\Moneta\Exceptions\ApiException;
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
            'format' => $format,
        ]);
    }

    public function downloadUrl(string $accountId, string $statementId, string $format = 'XML'): ?string
    {
        $response = $this->download($accountId, $statementId, $format);

        return $response['dataDownloadURL'] ?? null;
    }

    /**
     * Statements whose period intersects the calendar month.
     *
     * Endpoint: `GET /api/v4/vip/aisp/my/accounts/{id}/statements`.
     *
     * @return array<int, StatementData>
     */
    public function listFor(string $accountId, int $year, int $month): array
    {
        if ($month < 1 || $month > 12) {
            throw new \InvalidArgumentException('Statement month must be between 1 and 12.');
        }

        $start = new \DateTimeImmutable(sprintf('%04d-%02d-01', $year, $month));

        return $this->list($accountId, [
            'from' => $start->format('Y-m-d'),
            'to' => $start->modify('last day of this month')->format('Y-m-d'),
        ]);
    }

    /**
     * Official JSON statement. Embedded `exportStatement` is used as-is.
     * A response that only contains `dataDownloadURL` is downloaded and parsed.
     *
     * Endpoint: `GET /api/v4/vip/aisp/my/accounts/{id}/statement/download`.
     */
    public function json(string $accountId, string $statementId): StatementJsonData
    {
        $response = $this->moneta->get('api/v4/vip/aisp/my/accounts/'.$accountId.'/statement/download', [
            'accountStatementId' => $statementId,
            'format' => 'JSON',
            'feature' => 'JSON$EMBD',
        ]);

        if (! is_array($response['exportStatement'] ?? null) && is_string($response['dataDownloadURL'] ?? null)) {
            $response = $this->decodeStatementFile($this->downloadFile($this->fileId($response['dataDownloadURL'])));
        }

        return StatementJsonData::fromArray($response);
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeStatementFile(string $body): array
    {
        try {
            $decoded = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            throw new ApiException('Moneta statement file is not valid JSON.');
        }

        if (! is_array($decoded)) {
            throw new ApiException('Moneta statement file is not valid JSON.');
        }

        return $decoded;
    }

    private function fileId(string $url): string
    {
        parse_str((string) parse_url($url, PHP_URL_QUERY), $query);
        $file = $query['file'] ?? null;

        if (! is_string($file) || $file === '') {
            throw new ApiException('Moneta statement download URL does not contain a file id.');
        }

        return $file;
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
