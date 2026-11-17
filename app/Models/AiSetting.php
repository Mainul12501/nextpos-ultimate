<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;

/** Single-row AI Assistant configuration. The API key is stored encrypted. */
class AiSetting extends Model
{
    use \App\Models\Concerns\Auditable;

    public const MODELS = ['claude-opus-5-5', 'claude-sonnet-5-5', 'claude-haiku-5-5'];

    public const EFFORTS = ['low', 'medium', 'high'];

    protected $fillable = ['enabled', 'model', 'effort', 'insights_enabled', 'monthly_token_limit'];

    protected $hidden = ['api_key'];

    protected $casts = [
        'enabled' => 'boolean',
        'insights_enabled' => 'boolean',
        'monthly_token_limit' => 'integer',
    ];

    public static function current(): self
    {
        return static::query()->first() ?? new static([
            'enabled' => false,
            'model' => 'claude-opus-5-5',
            'effort' => 'medium',
            'insights_enabled' => true,
        ]);
    }

    /** Stored key, falling back to the ANTHROPIC_API_KEY environment variable. */
    public function apiKey(): ?string
    {
        if ($this->api_key) {
            try {
                return Crypt::decryptString($this->api_key);
            } catch (\Throwable $e) {
                return null; // APP_KEY changed — the key must be entered again
            }
        }

        return config('services.anthropic.key') ?: null;
    }

    public function setApiKey(?string $plain): void
    {
        $this->api_key = $plain ? Crypt::encryptString($plain) : null;
    }

    /** "sk-ant-…a1b2" for display; null when no key is set. */
    public function maskedKey(): ?string
    {
        $key = $this->apiKey();

        return $key ? substr($key, 0, 7).'…'.substr($key, -4) : null;
    }

    public function isReady(): bool
    {
        return $this->enabled && (bool) $this->apiKey();
    }
}
