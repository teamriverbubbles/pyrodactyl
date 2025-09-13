<?php

namespace Pterodactyl\Services\Subdomain\Features;

use Pterodactyl\Models\Server;
use Pterodactyl\Models\Domain;
use Pterodactyl\Contracts\Subdomain\SubdomainFeatureInterface;

class TeamSpeakSubdomainFeature implements SubdomainFeatureInterface
{
    /**
     * Get the feature name.
     */
    public function getFeatureName(): string
    {
        return 'subdomain_teamspeak';
    }

    /**
     * Get the DNS records that need to be created for TeamSpeak.
     */
    public function getDnsRecords(Server $server, string $subdomain, Domain $domain): array
    {
        // Get a valid IP address for DNS records (validates IP alias if used)
        $ip = $this->getIpForDnsRecord($server->allocation, $domain->use_ip_alias);
        $port = $server->allocation->port;
        $fullDomain = $subdomain . '.' . $domain->name;

        $records = [];

        // A record pointing to the server IP (for fallback and TSDNS)
        $records[] = [
            'name' => $subdomain,
            'type' => 'A',
            'content' => $ip,
            'ttl' => 300,
        ];

        // Primary TeamSpeak 3 SRV record (_ts3._udp)
        $records[] = [
            'name' => '_ts3._udp.' . $subdomain,
            'type' => 'SRV',
            'content' => [
                'service' => '_ts3',
                'proto' => '_udp',
                'name' => '_ts3._udp.' . $subdomain,
                'priority' => 0,
                'weight' => 5,
                'port' => $port,
                'target' => $fullDomain,
                'content' => "SRV 0 5 {$port} {$fullDomain}",
            ],
            'ttl' => 300,
        ];

        // Optional TSDNS SRV record if running on a common TSDNS port
        // This allows for TSDNS redirection if needed
        if ($this->shouldCreateTsdnsRecord($port)) {
            $records[] = [
                'name' => '_tsdns._tcp.' . $subdomain,
                'type' => 'SRV',
                'content' => [
                    'service' => '_tsdns',
                    'proto' => '_tcp',
                    'name' => '_tsdns._tcp.' . $subdomain,
                    'priority' => 0,
                    'weight' => 5,
                    'port' => $port,
                    'target' => $fullDomain,
                    'content' => "SRV 0 5 {$port} {$fullDomain}",
                ],
                'ttl' => 300,
            ];
        }

        return $records;
    }

    /**
     * Determine if we should create a TSDNS SRV record.
     * This is typically for common TSDNS ports or when configured.
     */
    private function shouldCreateTsdnsRecord(int $port): bool
    {
        // Common TSDNS ports
        $tsdnsPorts = [41144, 41145, 41146, 41147, 41148];
        
        // Create TSDNS record for common TSDNS ports or if the port looks like it might be TSDNS
        return in_array($port, $tsdnsPorts) || ($port >= 41144 && $port <= 41200);
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