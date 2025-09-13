<?php

namespace Pterodactyl\Services\Subdomain\Features;

use Pterodactyl\Models\Server;
use Pterodactyl\Models\Domain;
use Pterodactyl\Contracts\Subdomain\SubdomainFeatureInterface;

class RustSubdomainFeature implements SubdomainFeatureInterface
{
    /**
     * Get the feature name.
     */
    public function getFeatureName(): string
    {
        return 'subdomain_rust';
    }

    /**
     * Get the DNS records that need to be created for Rust.
    */
    public function getDnsRecords(Server $server, string $subdomain, Domain $domain): array
    {
        // Get a valid IP address for DNS records (validates IP alias if used)
        $ip = $this->getIpForDnsRecord($server->allocation, $domain->use_ip_alias);
        $port = $server->allocation->port;
        $fullDomain = $subdomain . '.' . $domain->name;

        $records = [];

        // A record pointing to the server IP
        $records[] = [
            'name' => $subdomain,
            'type' => 'A',
            'content' => $ip,
            'ttl' => 300,
        ];

        // SRV record for Rust (_rust._udp)
        $records[] = [
            'name' => '_rust._udp.' . $subdomain,
            'type' => 'SRV',
            'content' => [
                'service' => '_rust',
                'proto' => '_udp',
                'name' => '_rust._udp.' . $subdomain,
                'priority' => 0,
                'weight' => 5,
                'port' => $port,
                'target' => $fullDomain,
                'content' => "SRV 0 5 {$port} {$fullDomain}",
            ],
            'ttl' => 300,
        ];

        return $records;
    }

    /**
     * Get a valid IP address for DNS records.
     * Validates that ip_alias is a proper IP address if use_ip_alias is enabled.
     */
    protected function getIpForDnsRecord($allocation, bool $useIpAlias): string
    {
        if (!$useIpAlias) {
            return $allocation->ip;
        }

        // Check if ip_alias exists and is a valid IP address
        if ($allocation->ip_alias && filter_var($allocation->ip_alias, FILTER_VALIDATE_IP)) {
            return $allocation->ip_alias;
        }

        // Fall back to actual IP if alias is not a valid IP
        return $allocation->ip;
    }
}