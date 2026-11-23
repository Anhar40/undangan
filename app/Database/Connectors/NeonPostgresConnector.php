<?php

namespace App\Database\Connectors;

use Illuminate\Database\Connectors\PostgresConnector;

class NeonPostgresConnector extends PostgresConnector
{
    /**
     * Add Neon endpoint option when connecting through Neon pooler/SNI.
     */
    protected function addServerOptions($dsn, array $config)
    {
        $endpoint = $config['endpoint'] ?? $this->detectNeonEndpoint($config);

        if (is_string($endpoint) && $endpoint !== '' && ! str_contains($dsn, 'endpoint=')) {
            $dsn .= ";options='endpoint=".str_replace("'", "\\'", $endpoint)."'";
        }

        $serverOptions = $config['server_options'] ?? null;

        if (! empty($serverOptions) && is_array($serverOptions)) {
            $options = [];

            foreach ($serverOptions as $name => $value) {
                $options[] = '-c '.$name.'='.str_replace(
                    ['\\', ' '],
                    ['\\\\', '\\ '],
                    (string) $value
                );
            }

            $options = str_replace(
                ['\\', "'"],
                ['\\\\', "\\'"],
                implode(' ', $options)
            );

            if (str_contains($dsn, ";options='")) {
                $dsn = preg_replace(
                    "/;options='([^']*)'/",
                    ";options='$1 {$options}'",
                    $dsn,
                    1
                );
            } else {
                $dsn .= ";options='{$options}'";
            }
        }

        return $dsn;
    }

    /**
     * Normalize string "options" from URL parsing (Neon endpoint param)
     * to avoid array_diff_key TypeError in base Connector.
     */
    public function getOptions(array $config)
    {
        if (isset($config['options']) && is_string($config['options'])) {
            $config['options'] = [];
        }

        return parent::getOptions($config);
    }

    private function detectNeonEndpoint(array $config): ?string
    {
        $host = (string) ($config['host'] ?? '');
        if ($host === '') {
            $url = (string) ($config['url'] ?? '');
            $host = (string) (parse_url($url, PHP_URL_HOST) ?: '');
        }

        if ($host !== '' && str_contains($host, '.neon.tech')) {
            $first = explode('.', $host)[0] ?? '';
            $endpoint = preg_replace('/-pooler$/', '', $first);

            return $endpoint !== '' ? $endpoint : null;
        }

        return null;
    }
}
