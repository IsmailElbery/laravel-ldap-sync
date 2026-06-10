<?php

declare(strict_types=1);

namespace IsmailElbery\LdapSync\Dto;

use LdapRecord\Models\Model;

final readonly class LdapUserDto
{
    public function __construct(
        public string $username,
        public string $name,
        public ?string $nameAr,
        public ?string $email,
        public ?string $department,
        public ?string $title,
        public ?string $phone,
        public string $dn,
        public string $guid,
        public ?string $managerDn,
    ) {}

    public static function fromLdap(Model $entry, array $config): self
    {
        $get = static function (Model $e, string $attr): ?string {
            $val = $e->getFirstAttribute($attr);

            return ($val !== null && $val !== '') ? $val : null;
        };

        $arabicAttr = $config['search']['arabic_name_attribute'] ?? 'displayname';

        return new self(
            username: (string) $get($entry, 'samaccountname'),
            name: (string) ($get($entry, 'displayname') ?? $get($entry, 'cn') ?? ''),
            nameAr: $arabicAttr !== 'displayname' ? $get($entry, $arabicAttr) : null,
            email: $get($entry, 'mail'),
            department: $get($entry, 'department'),
            title: $get($entry, 'title'),
            phone: $get($entry, 'telephonenumber'),
            dn: $entry->getDn() ?? '',
            guid: (string) $entry->getConvertedGuid(),
            managerDn: $get($entry, 'manager'),
        );
    }
}
