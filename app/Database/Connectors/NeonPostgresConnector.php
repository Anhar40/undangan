<?php

namespace App\Database\Connectors;

use Illuminate\Database\Connectors\PostgresConnector;

class NeonPostgresConnector extends PostgresConnector
{
    /**
     * Build PostgreSQL DSN without injecting conflicting Neon endpoint.
     */
    protected function addServerOptions($dsn, array $config)
    {
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
     * Normalize URL options.
     */
    public function getOptions(array $config)
    {
        if (isset($config['options']) && is_string($config['options'])) {
            $config['options'] = [];
        }

        return parent::getOptions($config);
    }
}