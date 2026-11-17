<?php

namespace App\Services\Ai;

use Anthropic\Beta\Messages\BetaMessage;
use Anthropic\Client;
use Anthropic\Core\Exceptions\APIConnectionException;
use Anthropic\Core\Exceptions\APIStatusException;
use Anthropic\Core\Exceptions\AuthenticationException;
use Anthropic\Core\Exceptions\PermissionDeniedException;
use Anthropic\Core\Exceptions\RateLimitException;
use App\Models\AiSetting;
use Illuminate\Support\Facades\DB;

/**
 * Thin wrapper over the official Anthropic PHP SDK with the store's
 * settings (key, model, effort) and the cross-cutting rules:
 *  - server-side refusal fallback ("default" routing) where the model has one;
 *  - top-level prompt caching (tools + system + history are a stable prefix);
 *  - the optional monthly token cap.
 */
class AiClient
{
    public function __construct(private AiSetting $settings)
    {
    }

    public static function make(): self
    {
        return new self(AiSetting::current());
    }

    public function settings(): AiSetting
    {
        return $this->settings;
    }

    /**
     * POST /v1/messages (beta namespace, for the fallbacks parameter).
     * $args are the SDK's camelCase named arguments.
     *
     * @throws AiException with a message safe to show the user
     */
    public function create(array $args): BetaMessage
    {
        if (! $this->settings->apiKey()) {
            throw new AiException(__('messages.ai_not_configured'));
        }
        $this->guardBudget();

        $model = $this->settings->model ?: 'claude-opus-5-5';
        $args += [
            'model' => $model,
            'maxTokens' => 16000,
            'cacheControl' => ['type' => 'ephemeral'],
        ];
        // Effort: merge into any outputConfig the caller set (e.g. a JSON format).
        $args['outputConfig'] = ($args['outputConfig'] ?? []) + ['effort' => $this->settings->effort ?: 'medium'];

        // Refusal fallback: Haiku 5.5 has no server-side fallback.
        if ($model !== 'claude-haiku-5-5') {
            $args['fallbacks'] = 'default';
            $args['betas'] = array_values(array_unique(array_merge($args['betas'] ?? [], ['server-side-fallback-2026-07-01'])));
        }

        try {
            $client = new Client(apiKey: $this->settings->apiKey());

            return $client->beta->messages->create(...$args);
        } catch (AuthenticationException|PermissionDeniedException $e) {
            throw new AiException(__('messages.ai_invalid_key'), previous: $e);
        } catch (RateLimitException $e) {
            throw new AiException(__('messages.ai_rate_limited'), previous: $e);
        } catch (APIConnectionException $e) {
            throw new AiException(__('messages.ai_unreachable'), previous: $e);
        } catch (APIStatusException $e) {
            report($e);
            throw new AiException(__('messages.ai_request_failed', ['code' => $e->status ?: 'API']), previous: $e);
        } catch (\Anthropic\Core\Exceptions\AnthropicException|\JsonException $e) {
            // Anything else from the SDK (unexpected response shape, …).
            report($e);
            throw new AiException(__('messages.ai_request_failed', ['code' => 'SDK']), previous: $e);
        }
    }

    /** Tokens used this calendar month across chat and insights. */
    public static function monthTokens(): int
    {
        $since = now()->startOfMonth();

        $total = 0;
        foreach (['ai_messages', 'ai_insights', 'ai_usage'] as $table) {
            if (\Illuminate\Support\Facades\Schema::hasTable($table)) {
                $total += (int) DB::table($table)->where('created_at', '>=', $since)->sum(DB::raw('input_tokens + output_tokens'));
            }
        }

        return $total;
    }

    /** Record a one-off feature's usage (counts toward the monthly limit). */
    public static function logUsage(string $feature, BetaMessage $message, ?int $userId = null): void
    {
        $u = self::usage($message);
        DB::table('ai_usage')->insert([
            'user_id' => $userId,
            'feature' => $feature,
            'input_tokens' => $u['input'],
            'output_tokens' => $u['output'],
            'created_at' => now(),
        ]);
    }

    private function guardBudget(): void
    {
        $limit = $this->settings->monthly_token_limit;
        if ($limit && self::monthTokens() >= $limit) {
            throw new AiException(__('messages.ai_budget_reached'));
        }
    }

    /** Total tokens billed for one response (uncached + cache writes + cache reads + output). */
    public static function usage(BetaMessage $message): array
    {
        $u = $message->usage;

        return [
            'input' => (int) ($u->inputTokens ?? 0) + (int) ($u->cacheCreationInputTokens ?? 0) + (int) ($u->cacheReadInputTokens ?? 0),
            'output' => (int) ($u->outputTokens ?? 0),
        ];
    }
}
