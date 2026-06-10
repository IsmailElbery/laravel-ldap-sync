<?php

declare(strict_types=1);

namespace IsmailElbery\LdapSync\Search;

use Illuminate\Support\Collection;
use IsmailElbery\LdapSync\Dto\LdapUserDto;
use IsmailElbery\LdapSync\Services\OuResolver;
use LdapRecord\Models\ActiveDirectory\User;

final class LdapUserQuery
{
    private ?string $ouFilter = null;

    private ?string $departmentFilter = null;

    private ?string $nameFilter = null;

    private int $limit = 50;

    public function __construct(
        private readonly OuResolver $ouResolver,
        private readonly array $config,
    ) {}

    public function inOu(string $ou): static
    {
        $this->ouFilter = $ou;

        return $this;
    }

    public function department(string $department): static
    {
        $this->departmentFilter = $department;

        return $this;
    }

    public function name(string $name): static
    {
        $this->nameFilter = $name;

        return $this;
    }

    public function limit(int $limit): static
    {
        $this->limit = $limit;

        return $this;
    }

    /** @return Collection<int, LdapUserDto> */
    public function get(): Collection
    {
        $baseDns = $this->ouFilter !== null
            ? [$this->ouFilter]
            : $this->ouResolver->all();

        $seen = [];
        $results = collect();

        foreach ($baseDns as $baseDn) {
            $query = User::query()->in($baseDn);

            if ($this->departmentFilter !== null) {
                $query->whereContains('department', $this->departmentFilter);
            }

            if ($this->nameFilter !== null) {
                $arabicAttr = $this->config['search']['arabic_name_attribute'] ?? 'displayname';
                $query->whereContains('displayname', $this->nameFilter);

                if ($arabicAttr !== 'displayname') {
                    $query->orWhereContains($arabicAttr, $this->nameFilter);
                }
            }

            $query->limit($this->limit);

            /** @var User $entry */
            foreach ($query->get() as $entry) {
                $guid = (string) $entry->getConvertedGuid();

                if (isset($seen[$guid])) {
                    continue;
                }

                $seen[$guid] = true;
                $results->push(LdapUserDto::fromLdap($entry, $this->config));
            }
        }

        return $results;
    }
}
