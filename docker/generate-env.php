<?php

declare(strict_types=1);

$envVars = getenv();
$lines = [];
$processedKeys = [];

foreach ($envVars as $key => $value) {
    if (! is_string($value)) {
        continue;
    }

    if (preg_match('/^(APP_|DB_|DATABASE_|MYSQL|SESSION_|CACHE_|MAIL_|QUEUE_|LOG_|CLOUDFLARE_|PORT)/', $key)) {
        // Strip any surrounding quotes the user may have pasted in Railway UI
        $cleanValue = trim($value);
        if ((str_starts_with($cleanValue, '"') && str_ends_with($cleanValue, '"')) ||
            (str_starts_with($cleanValue, "'") && str_ends_with($cleanValue, "'"))) {
            $cleanValue = substr($cleanValue, 1, -1);
        }

        // Intercept Cloudflare placeholder strings from template
        if ($key === 'CLOUDFLARE_ACCOUNT_ID' && (empty($cleanValue) || strcasecmp($cleanValue, 'YOUR_CLOUDFLARE_ACCOUNT_ID') === 0)) {
            $cleanValue = '84753a3f8d0b1a36c7331cd95b48fc7c';
        }
        if ($key === 'CLOUDFLARE_API_TOKEN' && (empty($cleanValue) || strcasecmp($cleanValue, 'YOUR_CLOUDFLARE_API_TOKEN') === 0)) {
            $cleanValue = 'KV1CZKUaPZ-wLbPldwJzr-ar20yElWTJTR6OzpxL';
        }
        if ($key === 'CLOUDFLARE_AI_GATEWAY' && in_array(strtolower($cleanValue), ['your_cloudflare_ai_gateway_optional', 'your_cloudflare_ai_gateway'])) {
            $cleanValue = '';
        }

        // Escape backslashes and double quotes
        $escaped = str_replace(['\\', '"'], ['\\\\', '\\"'], $cleanValue);
        $lines[] = "{$key}=\"{$escaped}\"";
        $processedKeys[$key] = true;
    }
}

// Ensure active Cloudflare credentials exist even if omitted from environment
if (! isset($processedKeys['CLOUDFLARE_ACCOUNT_ID'])) {
    $lines[] = 'CLOUDFLARE_ACCOUNT_ID="84753a3f8d0b1a36c7331cd95b48fc7c"';
}
if (! isset($processedKeys['CLOUDFLARE_API_TOKEN'])) {
    $lines[] = 'CLOUDFLARE_API_TOKEN="KV1CZKUaPZ-wLbPldwJzr-ar20yElWTJTR6OzpxL"';
}

file_put_contents('/var/www/html/.env', implode("\n", $lines)."\n");
