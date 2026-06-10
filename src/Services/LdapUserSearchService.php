<?php

declare(strict_types=1);

namespace IsmailElbery\LdapSync\Services;

use Illuminate\Support\Collection;
use IsmailElbery\LdapSync\Dto\LdapUserDto;
use IsmailElbery\LdapSync\Search\LdapUserQuery;
use LdapRecord\Models\ActiveDirectory\User;

final class LdapUserSearchService
{
    private int $defaultLimit;

    public function __construct(
        private readonly OuResolver $ouResolver,
        private readonly array $config,
    ) {
        $this->defaultLimit = (int) ($config['search']['default_limit'] ?? 50);
    }

    public function byUsername(string $username): ?LdapUserDto
    {
        $escaped = ldap_escape($username, '', LDAP_ESCAPE_FILTER);

        foreach ($this->ouResolver->all() as $baseDn) {
            $query = User::query()->in($baseDn);
            $entry = $query->whereEquals('samaccountname', $escaped)->first();

            if ($entry !== null) {
                return LdapUserDto::fromLdap($entry, $this->config);
            }
        }

        return null;
    }

    public function byEmail(string $email): ?LdapUserDto
    {
        $escaped = ldap_escape($email, '', LDAP_ESCAPE_FILTER);

        foreach ($this->ouResolver->all() as $baseDn) {
            $query = User::query()->in($baseDn);
            $entry = $query->whereEquals('mail', $escaped)->first();

            if ($entry !== null) {
                return LdapUserDto::fromLdap($entry, $this->config);
            }
        }

        return null;
    }

    /** @return Collection<int, LdapUserDto> */
    public function byName(string $name): Collection
    {
        $arabicAttr = $this->config['search']['arabic_name_attribute'] ?? 'displayname';
        $seen = [];
        $results = collect();

        foreach ($this->ouResolver->all() as $baseDn) {
            // Fresh builder per OU — never reuse across iterations
            $query = User::query()->in($baseDn)->whereContains('displayname', $name);

            if ($arabicAttr !== 'displayname') {
                $query->orWhereContains($arabicAttr, $name);
            }

            $query->limit($this->defaultLimit);

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

    /** @return Collection<int, LdapUserDto> */
    public function byDepartment(string $department): Collection
    {
        $seen = [];
        $results = collect();

        foreach ($this->ouResolver->all() as $baseDn) {
            // Fresh builder per OU
            $query = User::query()->in($baseDn)->whereContains('department', $department);
            $query->limit($this->defaultLimit);

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

    /** @return Collection<int, LdapUserDto> */
    public function search(string $term): Collection
    {
        if (filter_var($term, FILTER_VALIDATE_EMAIL)) {
            $result = $this->byEmail($term);

            return $result !== null ? collect([$result]) : collect();
        }

        if (preg_match('/^[a-z0-9._-]+$/i', $term) && ! str_contains($term, ' ')) {
            $user = $this->byUsername($term);

            if ($user !== null) {
                return collect([$user]);
            }
        }

        return $this->byName($term);
    }

    public function query(): LdapUserQuery
    {
        return new LdapUserQuery($this->ouResolver, $this->config);
    }

    public function directManagerOf(LdapUserDto|string $user): ?LdapUserDto
    {
        if (is_string($user)) {
            $user = $this->byUsername($user);

            if ($user === null) {
                return null;
            }
        }

        if ($user->managerDn === null) {
            return null;
        }

        $entry = User::query()->find($user->managerDn);

        if ($entry === null) {
            return null;
        }

        return LdapUserDto::fromLdap($entry, $this->config);
    }

    /**
     * @return Collection<int, LdapUserDto>
     */
    public function managersOf(LdapUserDto|string $user, int $maxDepth = 10): Collection
    {
        if (is_string($user)) {
            $user = $this->byUsername($user);

            if ($user === null) {
                return collect();
            }
        }

        $chain = collect();
        $visited = [strtolower($user->dn) => true]; // prevent cycling back to the start
        $current = $user;
        $depth = 0;

        while ($depth < $maxDepth) {
            if ($current->managerDn === null) {
                break;
            }

            $managerDn = strtolower($current->managerDn);

            if (isset($visited[$managerDn])) {
                break; // Cycle detected
            }

            $visited[$managerDn] = true;

            $entry = User::query()->find($current->managerDn);

            if ($entry === null) {
                break;
            }

            $manager = LdapUserDto::fromLdap($entry, $this->config);
            $chain->push($manager);
            $current = $manager;
            $depth++;
        }

        return $chain;
    }

    /** @return Collection<int, LdapUserDto> */
    public function directReportsOf(LdapUserDto|string $user): Collection
    {
        if (is_string($user)) {
            $user = $this->byUsername($user);

            if ($user === null) {
                return collect();
            }
        }

        $seen = [];
        $results = collect();

        foreach ($this->ouResolver->all() as $baseDn) {
            // Fresh builder per OU
            $query = User::query()->in($baseDn)->whereEquals('manager', $user->dn);

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
