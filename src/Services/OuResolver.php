<?php

declare(strict_types=1);

namespace IsmailElbery\LdapSync\Services;

final class OuResolver
{
    /** @var list<string> */
    private array $resolved;

    public function __construct(
        private readonly string $baseDn,
        private readonly string $ouList,
    ) {
        $this->resolved = $this->parse();
    }

    /** @return list<string> */
    public function all(): array
    {
        return $this->resolved;
    }

    /** @return list<string> */
    private function parse(): array
    {
        $ous = array_filter(
            array_map('trim', explode(';', $this->ouList)),
            fn (string $ou) => $ou !== '',
        );

        return array_values(array_map(function (string $ou): string {
            // Already absolute (contains dc=)
            if (str_contains(strtolower($ou), 'dc=')) {
                return $ou;
            }

            return $ou.','.$this->baseDn;
        }, $ous));
    }
}
