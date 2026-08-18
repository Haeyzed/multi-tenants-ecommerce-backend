<?php

declare(strict_types=1);

namespace App\DTO\Interview;

/**
 * Declares what a meeting provider can do so Interview logic never guesses.
 */
readonly class MeetingProviderCapabilities
{
    /**
     * @param  list<string>  $requiredCredentialKeys
     */
    public function __construct(
        public bool $canCreate = true,
        public bool $canUpdate = true,
        public bool $canCancel = true,
        public bool $canGet = false,
        public bool $supportsPassword = false,
        public bool $supportsHostUrl = false,
        public bool $requiresExternalApi = false,
        public array $requiredCredentialKeys = [],
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'can_create' => $this->canCreate,
            'can_update' => $this->canUpdate,
            'can_cancel' => $this->canCancel,
            'can_get' => $this->canGet,
            'supports_password' => $this->supportsPassword,
            'supports_host_url' => $this->supportsHostUrl,
            'requires_external_api' => $this->requiresExternalApi,
            'required_credential_keys' => $this->requiredCredentialKeys,
        ];
    }
}
