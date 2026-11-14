<?php

declare(strict_types=1);

namespace SloukaPetr\Moneta\Services;

use SloukaPetr\Moneta\Data\TokenInfoData;
use SloukaPetr\Moneta\Exceptions\ApiException;
use SloukaPetr\Moneta\MonetaManager;

final class TokenService
{
    public function __construct(protected MonetaManager $moneta)
    {
    }

    /**
     * Expiration of the current API token.
     *
     * Endpoint: `GET /api/v1/vip/token/introspect`.
     */
    public function introspect(): TokenInfoData
    {
        try {
            $response = $this->moneta->get('api/v1/vip/token/introspect');
        } catch (ApiException $exception) {
            if ($exception->statusCode() === 403) {
                throw new ApiException(
                    'The token is not a Moneta API token.',
                    403,
                    $exception->errorCode,
                    $exception->scope,
                    $exception->parameters,
                    $exception->payload,
                    $exception,
                );
            }

            throw $exception;
        }

        return TokenInfoData::fromArray($response);
    }
}
