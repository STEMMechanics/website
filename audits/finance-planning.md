# Finance planning: testing guide

Branch: `feature/finance-allocation`, based on main at `72e40d5d` (PR #200).

## Start testing

Deploy this branch to the test environment using the normal deployment process, including:

```sh
php artisan migrate --force
npm ci
npm run build
php artisan optimize:clear
```

Open **Administration → Finance planning** (`/admin/finance`). The migration adds finance tables and a starting pricing version; it does not reprice invoices, categorise past expenses, create budgets, submit a BAS or send money. No environment settings are added.

1. **Pricing & categories:** inspect the initial 2026–27 rates. Create a differently dated version with lower historical costs. New versions preserve the old version. Add categories and set their funding priority (lowest first).
2. **Allocations:** select a small workshop date range and cost model. Override participant count/duration/venue/travel if needed, or enter invoice numbers to allocate those invoices individually. Generate the preview. Confirm that ticket invoices are grouped by workshop and fixed costs appear once. Change target amounts and watch shortfall/unallocated totals update. Apply selected rows.
3. Repeat the preview: existing allocations should be skipped. A batch can be reversed while it has no linked expenses or support transfers. Its original snapshot stays in batch history. An hourly task will not recreate reversed workshops silently.
4. **Suppliers & expenses:** add a 100% default or percentage split. Explicit expense allocations take precedence. Choose a single-category rule to require confirmation before exceptions. Expense splits must exactly equal the recorded expense excluding its GST; changing an expense total invalidates stale explicit splits until reviewed.
5. **Setup:** choose a start date and enter the reconciled bank balance immediately before that date, outstanding GST, category reserves and business buffer. Enter the first day of a pay fortnight. Confirm the balances and cash GST basis. Drawings are unavailable until this is done.
6. **My time:** record delivery, preparation, pack down, travel, administration or development. Entries retain their hourly target. Review fortnightly totals; correct an entry by expanding it. Own time is a remuneration target, not payroll or an expense.
7. **Drawings:** prepare a partial amount. Pending drawings reserve cash; cancellations release it. After actually transferring money, record the bank reference and date. Only paid drawings reduce recorded cash. Other administrators cannot update your timesheets/drawings.
8. **GST:** select a month, check sales GST, eligible purchase GST and net GST. Record the GST component of a BAS payment (negative for a refund), including past settlements. The same monthly settlement cannot be entered twice. Recording a settlement does not lodge or amend a BAS.
9. **Funds:** review reserves, uncategorised expenses and income awaiting allocation. Record an explicit fund transfer to cover a shortfall. A linked workshop retains its original revenue shortfall and shows support separately.
10. **Suppliers & expenses → Upcoming commitments:** reserve a future obligation including GST. Mark it paid by linking an existing paid expense, or cancel it. A category protects the higher of its remaining reserve and open commitments, rather than counting both twice.

## Automation

Automation is off initially. Once enabled in Setup, the existing scheduler runs `finance:allocate` hourly. For a test run:

```sh
php artisan finance:allocate
```

It creates eligible workshop budgets from the opening date through the next year, choosing the version effective on the workshop date. It refreshes participant/duration targets on automatic budgets and records revisions. Explicit assumptions or target overrides are preserved. Linked new ticket receipts appear when reports load; payment amounts and refunds are always recalculated from source transactions.

A maximum of 200 candidate workshops is processed per run. Shared/missing invoice links require review. For organisation invoices with no participant tickets, supply the participant count explicitly in the preview; do not rely on the ticket-derived default. Review travel separately: it cannot be inferred from an invoice description.

## Calculation boundaries

- All calculations use integer cents at allocation boundaries. Cumulative rounding conserves cents across invoice shares and supplier splits.
- Funding order is configurable globally. Changing priorities redistributes funding within existing targets; targets themselves retain their version/overrides.
- Initial internal targets use the supplied starting amounts: venue $50 + $40/additional hour, consumables $5/participant, equipment $7.50/workshop, insurance $17, operations $10, owner delivery $60/hour. Travel defaults to $19 vehicle + $15 owner time per billable 15 minutes after 30 free one-way minutes.
- These are editable internal funding targets, **not tax classifications**. In particular, review supplier costs against actual expense amounts excluding eligible GST. Allocating money to equipment does not itself create an expense or depreciation entry.
- The customer price schedule is stored separately, including GST. Preview suggestions use public or organisation pricing according to the venue-supplied assumption, rounded up to the next whole duration tier (1–4 hours). Longer durations have no suggested price. No existing workshop prices or invoice lines are overwritten; quoting remains a reviewed step.
- Invoice-only batches apply the supplied assumptions to **each** invoice. For a shared/multi-workshop invoice, select it once and use its combined targets. Automatic splitting of one invoice between workshops is deliberately blocked until its split is explicitly defined.
- GST uses the existing shared payment/invoice GST calculation, but finance planning excludes pending bank transfers, non-cash credit/account-terms entries and unsuccessful gateway payments. This may reveal differences from the older BAS report's unfiltered payment list. Verify eligible purchase credits before using the figures for a return. Cash GST accounting is the supported basis; accrual reporting is not implemented.
- Source receipt dates determine periods; opening balances must follow the same dates. GST is reserved before ordinary funds. A negative net GST balance does not increase cash until its refund is recorded.
- Historical allocations before the opening date are reports, not recreated reserves. Only later receipt/refund movements affect opening reserves. Prior BAS settlements and drawings must already be reflected in the opening balance.
- Cash is a reconstruction from records, not a bank feed. Income since the opening date that is not assigned to a budget remains protected from drawings. The business buffer is protected too. Opening balances need to cover unrecorded fees, outstanding customer credits and any other liabilities not yet entered.
- Owner time target and owner funding are different figures. Drawings are limited to the undrawn time target and available cash, after all protected obligations. Paid drawings also reduce the owner fund balance; a negative owner balance shows drawings supported by other available business cash. Unused target carries forward. Preparing/transferring drawings does not create an expense or affect the existing dashboard profit calculation.
- Once drawings exist, opening date/cash/GST cannot be changed through Setup. Do not enter a GST payment both as a finance settlement and an expense, or a drawing as an expense: that would double-count the bank movement.

## Validation

Run the standard suite and frontend checks:

```sh
php artisan qa --without-tty
node --test tests/Browser/*.test.cjs
npm run build
php artisan view:cache
```

`FinancePlanningTest` covers rendering/access control, preview/apply/reversal, shared ticket budgets, automation, historical opening boundaries, rounding, partial receipts/refunds, supplier rules, stale expense splits, commitments, protected cash, drawings and GST settlements. Browser tests cover AJAX rows-per-page preservation in the shared list controls.
