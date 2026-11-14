<?php

declare(strict_types=1);

namespace SloukaPetr\Moneta\Data;

use SloukaPetr\Moneta\Exceptions\InvalidPaymentOrderException;
use SloukaPetr\Moneta\Support\MinorAmount;

final readonly class PaymentOrderData
{
    public function __construct(
        public int $amountMinor,
        public string $currency,
        public string $debtorAccount,
        public string $creditorAccount,
        public ?string $vs = null,
        public ?string $ks = null,
        public ?string $ss = null,
        public ?string $message = null,
        public ?string $creditorName = null,
        public ?string $requestedExecutionDate = null,
        public ?string $instructionId = null,
    ) {
        if ($this->amountMinor <= 0) {
            throw new InvalidPaymentOrderException('Payment amount must be greater than zero.');
        }

        if (preg_match('/^[A-Za-z]{3}$/', $this->currency) !== 1) {
            throw new InvalidPaymentOrderException('Payment currency must be a 3-letter code.');
        }

        $this->account($this->debtorAccount);
        $this->account($this->creditorAccount);
        $this->symbol($this->vs, 'Variable symbol');
        $this->symbol($this->ks, 'Constant symbol');
        $this->symbol($this->ss, 'Specific symbol');

        if ($this->instructionId !== null && strlen($this->instructionId) > 35) {
            throw new InvalidPaymentOrderException('Instruction id must be at most 35 characters.');
        }

        if ($this->requestedExecutionDate !== null && ! $this->isDate($this->requestedExecutionDate)) {
            throw new InvalidPaymentOrderException('Requested execution date must use Y-m-d.');
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $payment = [
            'amount' => [
                'instructedAmount' => [
                    'value' => MinorAmount::toApi($this->amountMinor),
                    'currency' => strtoupper($this->currency),
                ],
            ],
            'debtorAccount' => $this->account($this->debtorAccount),
            'creditorAccount' => $this->account($this->creditorAccount),
            'paymentTypeInformation' => [
                'instructionPriority' => 'NORM',
            ],
        ];

        if ($this->creditorName !== null && $this->creditorName !== '') {
            $payment['creditor'] = ['name' => $this->creditorName];
        }

        if ($this->instructionId !== null && $this->instructionId !== '') {
            $payment['paymentIdentification'] = [
                'instructionIdentification' => $this->instructionId,
            ];
        }

        $remittance = [];
        $reference = $this->reference();

        if ($reference !== null) {
            $remittance['structured'] = [
                'creditorReferenceInformation' => [
                    'reference' => $reference,
                ],
            ];
        }

        if ($this->message !== null && $this->message !== '') {
            $remittance['unstructured'] = $this->message;
        }

        if ($remittance !== []) {
            $payment['remittanceInformation'] = $remittance;
        }

        if ($this->requestedExecutionDate !== null) {
            $payment['requestedExecutionDate'] = $this->requestedExecutionDate;
        }

        return $payment;
    }

    /**
     * @return array<string, mixed>
     */
    private function account(string $account): array
    {
        $normalized = strtoupper(str_replace(' ', '', trim($account)));

        if (preg_match('/^(?:\d{1,6}-)?\d{2,10}\/\d{4}$/', $normalized) === 1) {
            return [
                'identification' => [
                    'other' => [
                        'identification' => $normalized,
                    ],
                ],
            ];
        }

        if (preg_match('/^[A-Z]{2}\d{2}[A-Z0-9]{11,30}$/', $normalized) === 1) {
            return [
                'identification' => [
                    'iban' => $normalized,
                ],
            ];
        }

        throw new InvalidPaymentOrderException('Moneta payment account must be an IBAN or a Czech BBAN ([prefix-]number/bank).');
    }

    private function symbol(?string $value, string $name): void
    {
        if ($value === null) {
            return;
        }

        if (preg_match('/^\d{1,10}$/', $value) !== 1) {
            throw new InvalidPaymentOrderException($name.' must contain at most 10 digits.');
        }
    }

    private function reference(): ?string
    {
        $parts = [];

        if ($this->vs !== null) {
            $parts[] = 'VS:'.$this->vs;
        }

        if ($this->ks !== null) {
            $parts[] = 'KS:'.$this->ks;
        }

        if ($this->ss !== null) {
            $parts[] = 'SS:'.$this->ss;
        }

        return $parts === [] ? null : implode(',', $parts);
    }

    private function isDate(string $value): bool
    {
        $parsed = \DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        $errors = \DateTimeImmutable::getLastErrors();

        if ($parsed === false) {
            return false;
        }

        if (is_array($errors) && (($errors['warning_count'] ?? 0) > 0 || ($errors['error_count'] ?? 0) > 0)) {
            return false;
        }

        return $parsed->format('Y-m-d') === $value;
    }
}
