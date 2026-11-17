<?php

namespace App\Services\Reorder;

use App\Mail\CustomEmail;
use App\Models\User;
use App\Models\UserWarehouse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Smart reordering phase 3: the notification-bell count and the daily
 * "products need reordering" email.
 *
 * The digest goes out once a day, at or after settings.reorder_settings
 * digest_hour, to the chosen staff. Each recipient only sees the warehouses
 * they have access to. It is triggered by `reorder:digest` (scheduled hourly)
 * and, for installs without cron, after the response of the bell's
 * /pending-work poll — whichever comes first; a cache lock + digest_last_sent
 * make it run once. A day with nothing to reorder sends nothing.
 */
final class ReorderDigest
{
    private const BELL_TTL = 600;

    // ------------------------------------------------------------ bell

    /** Products needing an order in these warehouses (cached ~10 min). */
    public static function count(array $warehouseIds): int
    {
        sort($warehouseIds);
        $key = 'reorder:bell:'.self::version().':'.md5(implode(',', $warehouseIds));

        return (int) Cache::remember($key, self::BELL_TTL,
            fn () => ReorderEngine::suggestions($warehouseIds)['summary']['total']);
    }

    /** Forget cached counts (after creating orders / changing settings). */
    public static function bust(): void
    {
        Cache::forever('reorder:bell:version', self::version() + 1);
    }

    private static function version(): int
    {
        return (int) Cache::get('reorder:bell:version', 1);
    }

    // ------------------------------------------------------------ digest

    /** Cheap check for the web trigger: enabled, past the hour, not sent today. */
    public static function due(?array $settings = null): bool
    {
        $s = $settings ?? ReorderSettings::defaults();

        return $s['digest_enabled']
            && $s['digest_user_ids'] !== []
            && (int) now()->format('G') >= $s['digest_hour']
            && $s['digest_last_sent'] !== now()->toDateString();
    }

    /**
     * Send today's digest if due (or now, with $force). Returns the number of
     * emails sent, 0 when not due / nothing to reorder.
     */
    public static function sendIfDue(bool $force = false): int
    {
        $settings = ReorderSettings::defaults();
        if (! $force && ! self::due($settings)) {
            return 0;
        }
        if ($settings['digest_user_ids'] === []) {
            return 0;
        }

        $today = now()->toDateString();
        // One run at a time, across the scheduler and web requests.
        $lock = Cache::lock('reorder:digest:'.$today, 300);
        if (! $lock->get()) {
            return 0;
        }

        try {
            if (! $force && ReorderSettings::defaults()['digest_last_sent'] === $today) {
                return 0;
            }

            // A failure still counts as today's run: otherwise every bell poll
            // (the no-cron trigger) would rebuild and retry it all day long.
            try {
                $sent = self::send($settings['digest_user_ids']);
            } catch (\Throwable $e) {
                Log::warning('Reorder digest failed: '.$e->getMessage());
                $sent = 0;
            }
            ReorderSettings::markDigestSent($today);

            return $sent;
        } finally {
            $lock->release();
        }
    }

    /** @param  int[]  $userIds */
    private static function send(array $userIds): int
    {
        $users = User::whereIn('id', $userIds)->whereNull('deleted_at')->where('statut', 1)->get();
        $allWarehouses = DB::table('warehouses')->whereNull('deleted_at')->pluck('id')->map(fn ($id) => (int) $id)->all();

        // Recipients with the same warehouse access share one computation.
        $groups = [];
        foreach ($users as $user) {
            if (! $user->email) {
                continue;
            }
            $ids = $user->is_all_warehouses
                ? $allWarehouses
                : array_values(array_intersect($allWarehouses, UserWarehouse::where('user_id', $user->id)->pluck('warehouse_id')->map(fn ($id) => (int) $id)->all()));
            sort($ids);
            $groups[implode(',', $ids)][] = $user;
        }

        $sent = 0;
        $configured = false;
        foreach ($groups as $warehouseList => $recipients) {
            $result = ReorderEngine::suggestions($warehouseList === '' ? [] : array_map('intval', explode(',', $warehouseList)));
            if ($result['rows'] === []) {
                continue;
            }

            [$subject, $html] = self::render($result);
            if (! $configured) {
                (new \App\Http\Controllers\BaseController)->Set_config_mail();
                $configured = true;
            }

            foreach ($recipients as $user) {
                try {
                    Mail::to($user->email)->send(new CustomEmail([
                        'subject' => $subject,
                        'body' => $html,
                        'company_name' => DB::table('settings')->whereNull('deleted_at')->value('CompanyName'),
                    ]));
                    $sent++;
                } catch (\Throwable $e) {
                    Log::warning('Reorder digest email failed: '.$e->getMessage());
                }
            }
        }

        return $sent;
    }

    /** @return array{0: string, 1: string} subject, HTML body */
    public static function render(array $result): array
    {
        $s = $result['summary'];
        $rows = $result['rows'];
        $base = rtrim(url('/next'), '/');
        $money = fn ($v) => number_format((float) $v, 2);
        $num = fn ($v) => rtrim(rtrim(number_format((float) $v, 2, '.', ''), '0'), '.');

        $subject = trans_choice('{1} :count product needs reordering|[2,*] :count products need reordering', $s['total'], ['count' => $s['total']]);

        $parts = [];
        if ($s['out']) {
            $parts[] = __(':count out of stock', ['count' => $s['out']]);
        }
        if ($s['critical']) {
            $parts[] = __(':count will run out before a new order arrives', ['count' => $s['critical']]);
        }
        if ($s['low']) {
            $parts[] = __(':count running low', ['count' => $s['low']]);
        }

        $html = '<p>'.e($subject).($parts ? ': '.e(implode(', ', $parts)) : '').'.</p>';
        $html .= '<p>'.e(__('Estimated cost to reorder: :amount', ['amount' => $money($s['value'])])).'</p>';
        $html .= '<table cellpadding="6" cellspacing="0" style="border-collapse:collapse;width:100%;font-size:13px">'
            .'<tr style="background:#f4f4f5;text-align:left"><th>'.e(__('Product')).'</th><th>'.e(__('Warehouse')).'</th><th>'.e(__('Days left')).'</th><th>'.e(__('Order')).'</th><th>'.e(__('Supplier')).'</th></tr>';
        foreach (array_slice($rows, 0, 25) as $r) {
            $days = $r['status'] === 'out' ? __('Out of stock') : ($r['days_left'] === null ? '—' : $num($r['days_left']));
            $html .= '<tr style="border-top:1px solid #e4e4e7"><td>'.e($r['name']).($r['manufactured'] ? ' <em>('.e(__('manufactured')).')</em>' : '').'</td>'
                .'<td>'.e($r['warehouse']).'</td><td>'.e($days).'</td>'
                .'<td>'.e($num($r['suggested_qty']).' '.$r['purchase_unit']).'</td><td>'.e($r['provider'] ?? '—').'</td></tr>';
        }
        $html .= '</table>';
        if (count($rows) > 25) {
            $html .= '<p>'.e(__('…and :count more.', ['count' => count($rows) - 25])).'</p>';
        }
        $html .= '<p><a href="'.e($base.'/purchases/reorder').'">'.e(__('Open Reorder suggestions to create the purchase orders')).'</a></p>';

        return [$subject, $html];
    }
}
