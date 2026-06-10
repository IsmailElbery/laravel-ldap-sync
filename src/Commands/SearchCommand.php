<?php

declare(strict_types=1);

namespace IsmailElbery\LdapSync\Commands;

use Illuminate\Console\Command;
use IsmailElbery\LdapSync\Dto\LdapUserDto;
use IsmailElbery\LdapSync\Services\LdapUserSearchService;

final class SearchCommand extends Command
{
    protected $signature = 'ldap:search
        {term? : Smart search term (email, username, or name)}
        {--email= : Search by exact email}
        {--username= : Search by exact username}
        {--department= : Search by department (partial match)}';

    protected $description = 'Search Active Directory users';

    public function handle(LdapUserSearchService $service): int
    {
        $results = collect();

        if ($email = $this->option('email')) {
            $user = $service->byEmail((string) $email);
            $results = $user !== null ? collect([$user]) : collect();
        } elseif ($username = $this->option('username')) {
            $user = $service->byUsername((string) $username);
            $results = $user !== null ? collect([$user]) : collect();
        } elseif ($dept = $this->option('department')) {
            $results = $service->byDepartment((string) $dept);
        } elseif ($term = $this->argument('term')) {
            $results = $service->search((string) $term);
        } else {
            $this->components->error('Provide a search term or one of --email, --username, --department.');

            return self::FAILURE;
        }

        if ($results->isEmpty()) {
            $this->components->warn('No users found.');

            return self::SUCCESS;
        }

        $this->table(
            ['Username', 'Name', 'Email', 'Department', 'Title'],
            $results->map(fn (LdapUserDto $u) => [
                $u->username,
                $u->name,
                $u->email ?? '',
                $u->department ?? '',
                $u->title ?? '',
            ])->all(),
        );

        return self::SUCCESS;
    }
}
