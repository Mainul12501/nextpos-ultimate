<?php

namespace App\Http\Controllers\AccountingV2;

use App\Http\Controllers\Controller;
use App\Models\Account;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * NEW FEATURE - SAFE ADDITION
 */
class ReportsController extends Controller
{
    public function trialBalance(Request $request)
    {
        $this->authorizeForUser($request->user('api'), 'trial_balance', Account::class);

        if (! Schema::hasTable('acc_journal_entry_lines')) {
            return response()->json(['data' => [], 'totalRows' => 0]);
        }
        $from = $request->get('from');
        $to = $request->get('to');
        $search = trim((string) $request->get('search', ''));
        $page = max(1, (int) $request->get('page', 1));
        $limit = max(1, (int) $request->get('limit', 10));
        $sortField = $request->get('SortField', 'code');
        $sortType = strtolower($request->get('SortType', 'asc')) === 'desc' ? 'desc' : 'asc';
        $allowedSort = ['code' => 'c.code', 'name' => 'c.name', 'type' => 'c.type', 'debit' => 'debit', 'credit' => 'credit'];
        $orderBy = $allowedSort[$sortField] ?? 'c.code';

        $base = DB::table('acc_journal_entry_lines as l')
            ->join('acc_journal_entries as j', 'j.id', '=', 'l.journal_entry_id')
            ->leftJoin('acc_chart_of_accounts as c', 'c.id', '=', 'l.coa_id')
            ->where('j.status', 'posted');
        if ($from) {
            $base->where('j.date', '>=', $from);
        }
        if ($to) {
            $base->where('j.date', '<=', $to);
        }
        if ($request->filled('type')) {
            $base->where('c.type', $request->get('type'));
        }
        if ($search !== '') {
            $base->where(function ($q) use ($search) {
                $q->where('c.code', 'like', "%$search%")->orWhere('c.name', 'like', "%$search%");
            });
        }

        $grouped = $base->select('c.id as coa_id', 'c.code', 'c.name', 'c.type', DB::raw('SUM(l.debit) as debit'), DB::raw('SUM(l.credit) as credit'))
            ->groupBy('c.id', 'c.code', 'c.name', 'c.type');

        // total groups
        $totalRows = (clone $grouped)->get()->count();

        // pagination + sorting
        $rows = (clone $grouped)
            ->orderBy($orderBy, $sortType)
            ->skip(($page - 1) * $limit)
            ->take($limit)
            ->get();

        return response()->json([
            'data' => $rows,
            'totalRows' => $totalRows,
        ]);
    }

    public function profitAndLoss(Request $request)
    {
        $this->authorizeForUser($request->user('api'), 'accounting_profit_loss', Account::class);

        if (! Schema::hasTable('acc_journal_entry_lines')) {
            return response()->json(['data' => [], 'totalRows' => 0, 'summary' => ['income' => 0, 'expense' => 0, 'net_profit' => 0]]);
        }
        $from = $request->get('from');
        $to = $request->get('to');
        $search = trim((string) $request->get('search', ''));
        $typeFilter = $request->get('type'); // income|expense optional
        $page = max(1, (int) $request->get('page', 1));
        $limit = max(1, (int) $request->get('limit', 10));
        $sortField = $request->get('SortField', 'code');
        $sortType = strtolower($request->get('SortType', 'asc')) === 'desc' ? 'desc' : 'asc';
        $allowedSort = ['code' => 'c.code', 'name' => 'c.name', 'type' => 'c.type', 'amount' => 'amount'];
        $orderBy = $allowedSort[$sortField] ?? 'c.code';

        // Period-only base: date range + posted status. The summary cards are
        // computed from this so they always reflect the whole period, even when
        // the table is narrowed by the type/search filters below.
        $periodBase = DB::table('acc_journal_entry_lines as l')
            ->join('acc_journal_entries as j', 'j.id', '=', 'l.journal_entry_id')
            ->leftJoin('acc_chart_of_accounts as c', 'c.id', '=', 'l.coa_id')
            ->where('j.status', 'posted')
            ->whereIn('c.type', ['income', 'expense']);
        if ($from) {
            $periodBase->where('j.date', '>=', $from);
        }
        if ($to) {
            $periodBase->where('j.date', '<=', $to);
        }

        $base = clone $periodBase;
        if ($typeFilter) {
            $base->where('c.type', $typeFilter);
        }
        if ($search !== '') {
            $base->where(function ($q) use ($search) {
                $q->where('c.code', 'like', "%$search%")->orWhere('c.name', 'like', "%$search%");
            });
        }

        $amountExpr = "SUM(CASE WHEN c.type='income' THEN (l.credit - l.debit) ELSE (l.debit - l.credit) END)";
        $grouped = $base->select('c.id as coa_id', 'c.code', 'c.name', 'c.type', DB::raw("$amountExpr as amount"))
            ->groupBy('c.id', 'c.code', 'c.name', 'c.type');

        // Total rows (excluding zero-amount rows)
        $totalRows = (clone $grouped)->havingRaw("$amountExpr <> 0")
            ->get()
            ->count();

        // Page rows
        $rows = (clone $grouped)
            ->havingRaw("$amountExpr <> 0")
            ->orderBy($orderBy, $sortType)
            ->skip(($page - 1) * $limit)
            ->take($limit)
            ->get();

        // Summary
        $income = (float) ((clone $periodBase)->where('c.type', 'income')->select(DB::raw('SUM(l.credit - l.debit) as amount'))->value('amount') ?? 0);
        $expense = (float) ((clone $periodBase)->where('c.type', 'expense')->select(DB::raw('SUM(l.debit - l.credit) as amount'))->value('amount') ?? 0);
        $summary = ['income' => $income, 'expense' => $expense, 'net_profit' => $income - $expense];

        return response()->json([
            'data' => $rows,
            'totalRows' => $totalRows,
            'summary' => $summary,
        ]);
    }

    public function balanceSheet(Request $request)
    {
        $this->authorizeForUser($request->user('api'), 'balance_sheet', Account::class);

        // An empty `to` arrives as '' from the page's date input, which would
        // otherwise beat the default and cut the sheet off at nothing.
        $to = $request->filled('to') ? $request->get('to') : Carbon::now()->toDateString();

        if (! Schema::hasTable('acc_journal_entry_lines')) {
            return response()->json([
                'data' => [],
                'totalRows' => 0,
                'date' => $to,
                'assets' => 0,
                'liabilities' => 0,
                'equity' => 0,
                'balance' => 0,
            ]);
        }
        $search = trim((string) $request->get('search', ''));
        $typeFilter = $request->get('type'); // asset|liability|equity optional
        $page = max(1, (int) $request->get('page', 1));
        $limit = max(1, (int) $request->get('limit', 10));
        $sortField = $request->get('SortField', 'code');
        $sortType = strtolower($request->get('SortType', 'asc')) === 'desc' ? 'desc' : 'asc';
        $allowedSort = ['code' => 'c.code', 'name' => 'c.name', 'type' => 'c.type', 'amount' => 'amount'];
        $orderBy = $allowedSort[$sortField] ?? 'c.code';

        // As-of base: everything posted up to the cut-off date. The three
        // statement totals are computed from this so they always cover the
        // whole sheet, even when the detail table below is narrowed by the
        // type/search filters.
        $asOfBase = DB::table('acc_journal_entry_lines as l')
            ->join('acc_journal_entries as j', 'j.id', '=', 'l.journal_entry_id')
            ->leftJoin('acc_chart_of_accounts as c', 'c.id', '=', 'l.coa_id')
            ->where('j.status', 'posted')
            ->where('j.date', '<=', $to);

        $assets = (clone $asOfBase)->where('c.type', 'asset')->select(DB::raw('SUM(l.debit - l.credit) as amount'))->value('amount') ?? 0;
        $liabilities = (clone $asOfBase)->where('c.type', 'liability')->select(DB::raw('SUM(l.credit - l.debit) as amount'))->value('amount') ?? 0;
        $equity = (clone $asOfBase)->where('c.type', 'equity')->select(DB::raw('SUM(l.credit - l.debit) as amount'))->value('amount') ?? 0;

        // Income and expense are never closed into an equity account until the
        // year-end close, so the profit to date sits outside the three sections
        // and the sheet can't balance. Carry it into equity as its own line.
        $earnings = (float) ((clone $asOfBase)->whereIn('c.type', ['income', 'expense'])
            ->select(DB::raw('SUM(l.credit - l.debit) as amount'))->value('amount') ?? 0);
        $equity = (float) $equity + $earnings;

        // Per-account detail lines, same shape as the profit & loss report.
        $base = (clone $asOfBase)->whereIn('c.type', ['asset', 'liability', 'equity']);
        if ($typeFilter) {
            $base->where('c.type', $typeFilter);
        }
        if ($search !== '') {
            $base->where(function ($q) use ($search) {
                $q->where('c.code', 'like', "%$search%")->orWhere('c.name', 'like', "%$search%");
            });
        }

        // Assets carry a debit balance, liabilities and equity a credit one, so
        // each side is signed to read positive on a healthy sheet.
        $amountExpr = "SUM(CASE WHEN c.type='asset' THEN (l.debit - l.credit) ELSE (l.credit - l.debit) END)";
        $grouped = $base->select('c.id as coa_id', 'c.code', 'c.name', 'c.type', DB::raw("$amountExpr as amount"))
            ->groupBy('c.id', 'c.code', 'c.name', 'c.type');

        // Total rows (excluding zero-amount rows)
        $totalRows = (clone $grouped)->havingRaw("$amountExpr <> 0")
            ->get()
            ->count();

        // Page rows
        $rows = (clone $grouped)
            ->havingRaw("$amountExpr <> 0")
            ->orderBy($orderBy, $sortType)
            ->skip(($page - 1) * $limit)
            ->take($limit)
            ->get();

        // The earnings line follows the real accounts, on the last page.
        $showEarnings = abs($earnings) >= 0.005 && in_array($typeFilter, [null, '', 'equity'], true)
            && ($search === '' || stripos('current year earnings', $search) !== false);
        if ($showEarnings) {
            $totalRows++;
            if ($page >= (int) ceil($totalRows / $limit)) {
                $rows->push((object) [
                    'coa_id' => 'current_earnings',
                    'code' => '-',
                    'name' => 'Current Year Earnings',
                    'type' => 'equity',
                    'amount' => $earnings,
                ]);
            }
        }

        return response()->json([
            'data' => $rows,
            'totalRows' => $totalRows,
            'date' => $to,
            'assets' => (float) $assets,
            'liabilities' => (float) $liabilities,
            'equity' => (float) $equity,
            'balance' => (float) $assets - ((float) $liabilities + (float) $equity),
        ]);
    }

    /**
     * Every posted line hitting one chart account, with an opening balance
     * carried in from before `from` and a running balance per line. Assets and
     * expenses run on the debit side, everything else on the credit side, so
     * the balance reads positive for a normal account.
     */
    public function accountLedger(Request $request)
    {
        $this->authorizeForUser($request->user('api'), 'chart_of_accounts', Account::class);

        $coaId = (int) $request->get('coa_id');
        $from = $request->filled('from') ? $request->get('from') : null;
        $to = $request->filled('to') ? $request->get('to') : null;
        $page = max(1, (int) $request->get('page', 1));
        $limit = max(1, (int) $request->get('limit', 10));

        $account = Schema::hasTable('acc_chart_of_accounts')
            ? DB::table('acc_chart_of_accounts')->where('id', $coaId)->first(['id', 'code', 'name', 'type'])
            : null;
        if (! $account || ! Schema::hasTable('acc_journal_entry_lines')) {
            return response()->json([
                'account' => $account, 'data' => [], 'totalRows' => 0,
                'opening' => 0, 'debit' => 0, 'credit' => 0, 'closing' => 0,
            ]);
        }

        $sign = in_array($account->type, ['asset', 'expense'], true) ? 1 : -1;

        $posted = DB::table('acc_journal_entry_lines as l')
            ->join('acc_journal_entries as j', 'j.id', '=', 'l.journal_entry_id')
            ->where('j.status', 'posted')
            ->where('l.coa_id', $coaId);

        $opening = $from
            ? (float) ((clone $posted)->where('j.date', '<', $from)->select(DB::raw("SUM(l.debit - l.credit) * $sign as amount"))->value('amount') ?? 0)
            : 0.0;

        $period = (clone $posted)
            ->when($from, fn ($q) => $q->where('j.date', '>=', $from))
            ->when($to, fn ($q) => $q->where('j.date', '<=', $to));

        $totals = (clone $period)->select(DB::raw('SUM(l.debit) as debit'), DB::raw('SUM(l.credit) as credit'), DB::raw('COUNT(*) as n'))->first();
        $debit = (float) ($totals->debit ?? 0);
        $credit = (float) ($totals->credit ?? 0);

        $ordered = (clone $period)->orderBy('j.date')->orderBy('j.id')->orderBy('l.id');
        $offset = ($page - 1) * $limit;

        // Balance brought forward to the first line of this page.
        $running = $opening;
        if ($offset > 0) {
            foreach ((clone $ordered)->take($offset)->get(['l.debit', 'l.credit']) as $b) {
                $running += ((float) $b->debit - (float) $b->credit) * $sign;
            }
        }

        $rows = (clone $ordered)->skip($offset)->take($limit)->get([
            'l.id', 'j.id as journal_id', 'j.date', 'j.description', 'j.reference_type', 'j.reference_id',
            'l.memo', 'l.debit', 'l.credit',
        ])->map(function ($r) use (&$running, $sign) {
            $running += ((float) $r->debit - (float) $r->credit) * $sign;
            $r->debit = (float) $r->debit;
            $r->credit = (float) $r->credit;
            $r->balance = round($running, 2);

            return $r;
        });

        return response()->json([
            'account' => $account,
            'data' => $rows,
            'totalRows' => (int) ($totals->n ?? 0),
            'opening' => $opening,
            'debit' => $debit,
            'credit' => $credit,
            'closing' => $opening + ($debit - $credit) * $sign,
        ]);
    }

    public function taxSummary(Request $request)
    {
        $this->authorizeForUser($request->user('api'), 'accounting_tax_report', Account::class);

        // Compute from legacy sales/purchases including returns
        $from = $request->get('from');
        $to = $request->get('to');

        $sales = 0.0;
        $outputTax = 0.0;
        $saleReturns = 0.0;
        $saleReturnTax = 0.0;
        $purchases = 0.0;
        $inputTax = 0.0;
        $purchaseReturns = 0.0;
        $purchaseReturnTax = 0.0;

        // Calculate Sales (Output Tax)
        if (Schema::hasTable('sales')) {
            $q = DB::table('sales')
                ->whereNull('deleted_at')
                ->where('statut', '!=', 'pending'); // Exclude draft/pending sales
            if ($from) {
                $q->where('date', '>=', $from);
            }
            if ($to) {
                $q->where('date', '<=', $to);
            }
            $sales = (float) ($q->sum('GrandTotal') ?? 0);
            $outputTax = (float) ($q->sum('TaxNet') ?? 0);
        }

        // Calculate Sale Returns (Reduce Output Tax)
        if (Schema::hasTable('sale_returns')) {
            $q = DB::table('sale_returns')
                ->whereNull('deleted_at')
                ->where('statut', '!=', 'pending');
            if ($from) {
                $q->where('date', '>=', $from);
            }
            if ($to) {
                $q->where('date', '<=', $to);
            }
            $saleReturns = (float) ($q->sum('GrandTotal') ?? 0);
            $saleReturnTax = (float) ($q->sum('TaxNet') ?? 0);
        }

        // Calculate Purchases (Input Tax)
        if (Schema::hasTable('purchases')) {
            $q = DB::table('purchases')
                ->whereNull('deleted_at')
                ->where('statut', '!=', 'pending'); // Exclude draft/pending purchases
            if ($from) {
                $q->where('date', '>=', $from);
            }
            if ($to) {
                $q->where('date', '<=', $to);
            }
            $purchases = (float) ($q->sum('GrandTotal') ?? 0);
            $inputTax = (float) ($q->sum('TaxNet') ?? 0);
        }

        // Calculate Purchase Returns (Reduce Input Tax)
        if (Schema::hasTable('purchase_returns')) {
            $q = DB::table('purchase_returns')
                ->whereNull('deleted_at')
                ->where('statut', '!=', 'pending');
            if ($from) {
                $q->where('date', '>=', $from);
            }
            if ($to) {
                $q->where('date', '<=', $to);
            }
            $purchaseReturns = (float) ($q->sum('GrandTotal') ?? 0);
            $purchaseReturnTax = (float) ($q->sum('TaxNet') ?? 0);
        }

        // Net calculations (sales - returns)
        $netSales = $sales - $saleReturns;
        $netOutputTax = $outputTax - $saleReturnTax;
        $netPurchases = $purchases - $purchaseReturns;
        $netInputTax = $inputTax - $purchaseReturnTax;

        return response()->json([
            'period' => ['from' => $from, 'to' => $to],
            'sales' => $sales,
            'sale_returns' => $saleReturns,
            'taxable_sales' => $netSales,
            'output_tax' => $netOutputTax,
            'purchases' => $purchases,
            'purchase_returns' => $purchaseReturns,
            'taxable_purchases' => $netPurchases,
            'input_tax' => $netInputTax,
            'net_tax' => $netOutputTax - $netInputTax,
        ]);
    }
}
