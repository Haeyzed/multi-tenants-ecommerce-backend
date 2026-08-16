<?php

declare(strict_types=1);

namespace App\Services\Tenant\Pos;

use App\Enums\Tenant\Pos\PosCashMovementType;
use App\Enums\Tenant\Pos\PosSessionStatus;
use App\Enums\Tenant\Pos\PosTerminalStatus;
use App\Events\POSSessionClosed;
use App\Events\POSSessionOpened;
use App\Models\Tenant\PosCashMovement;
use App\Models\Tenant\PosSession;
use App\Models\Tenant\PosTerminal;
use App\Models\Tenant\User;
use App\Support\Money;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * POS cashier session open/close and cash drawer movements.
 */
class PosSessionService
{
    /**
     * @param  array{
     *     status?: string|null,
     *     pos_terminal_id?: int|null,
     *     user_id?: int|null,
     *     sort?: string|null,
     *     per_page?: int|null
     * }  $params
     * @return LengthAwarePaginator<int, PosSession>
     */
    public function list(array $params = []): LengthAwarePaginator
    {
        return PosSession::query()
            ->with(['terminal', 'user'])
            ->filter($params)
            ->applySort($params['sort'] ?? null)
            ->paginate(max(1, min((int) ($params['per_page'] ?? 15), 100)));
    }

    public function show(PosSession $session): PosSession
    {
        return $session->loadMissing(['terminal.warehouse', 'user', 'cashMovements']);
    }

    /**
     * Open a cashier session on a terminal (one open session per terminal).
     */
    public function open(PosTerminal $terminal, User $user, string $openingCash, ?string $notes = null): PosSession
    {
        if ($terminal->status !== PosTerminalStatus::Active) {
            throw ValidationException::withMessages([
                'pos_terminal_id' => 'Terminal must be active to open a session.',
            ]);
        }

        if (bccomp($openingCash, '0', 2) < 0) {
            throw ValidationException::withMessages([
                'opening_cash' => 'Opening cash cannot be negative.',
            ]);
        }

        return DB::transaction(function () use ($terminal, $user, $openingCash, $notes): PosSession {
            /** @var PosTerminal $lockedTerminal */
            $lockedTerminal = PosTerminal::query()->whereKey($terminal->getKey())->lockForUpdate()->firstOrFail();

            $existingOpen = PosSession::query()
                ->where('pos_terminal_id', $lockedTerminal->id)
                ->where('status', PosSessionStatus::Open)
                ->lockForUpdate()
                ->exists();

            if ($existingOpen) {
                throw ValidationException::withMessages([
                    'pos_terminal_id' => 'This terminal already has an open session.',
                ]);
            }

            $session = PosSession::query()->create([
                'pos_terminal_id' => $lockedTerminal->id,
                'user_id' => $user->id,
                'status' => PosSessionStatus::Open,
                'opened_at' => now(),
                'opening_cash' => Money::add($openingCash, '0'),
                'notes' => $notes,
            ]);

            PosCashMovement::query()->create([
                'pos_session_id' => $session->id,
                'type' => PosCashMovementType::Opening,
                'amount' => Money::add($openingCash, '0'),
                'reason' => 'Session opening float',
                'user_id' => $user->id,
            ]);

            event(new POSSessionOpened($session));

            return $session->load(['terminal', 'user']);
        });
    }

    /**
     * Close a session, computing expected cash and variance.
     */
    public function close(PosSession $session, string $actualCash, ?string $notes = null): PosSession
    {
        return DB::transaction(function () use ($session, $actualCash, $notes): PosSession {
            /** @var PosSession $locked */
            $locked = PosSession::query()->whereKey($session->getKey())->lockForUpdate()->firstOrFail();

            if ($locked->status !== PosSessionStatus::Open) {
                throw ValidationException::withMessages([
                    'session' => 'Only open sessions can be closed.',
                ]);
            }

            if (bccomp($actualCash, '0', 2) < 0) {
                throw ValidationException::withMessages([
                    'actual_cash' => 'Actual cash cannot be negative.',
                ]);
            }

            $expected = $this->expectedCash($locked);
            $actual = Money::add($actualCash, '0');
            $difference = Money::sub($actual, $expected);

            $locked->status = PosSessionStatus::Closed;
            $locked->closed_at = now();
            $locked->closing_cash = $actual;
            $locked->expected_cash = $expected;
            $locked->actual_cash = $actual;
            $locked->cash_difference = $difference;
            if ($notes !== null) {
                $locked->notes = $notes;
            }
            $locked->save();

            PosCashMovement::query()->create([
                'pos_session_id' => $locked->id,
                'type' => PosCashMovementType::Closing,
                'amount' => $actual,
                'reason' => 'Session closing count',
                'user_id' => $locked->user_id,
            ]);

            event(new POSSessionClosed($locked));

            return $locked->fresh(['terminal', 'user', 'cashMovements']) ?? $locked;
        });
    }

    /**
     * Record a cash-in movement on an open session.
     */
    public function cashIn(PosSession $session, User $user, string $amount, ?string $reason = null): PosCashMovement
    {
        return $this->recordMovement($session, $user, PosCashMovementType::CashIn, $amount, $reason);
    }

    /**
     * Record a cash-out movement on an open session.
     */
    public function cashOut(PosSession $session, User $user, string $amount, ?string $reason = null): PosCashMovement
    {
        return $this->recordMovement($session, $user, PosCashMovementType::CashOut, $amount, $reason);
    }

    /**
     * Expected drawer cash: opening + cash_in + sale_cash − cash_out − refund_cash.
     */
    public function expectedCash(PosSession $session): string
    {
        $sums = PosCashMovement::query()
            ->where('pos_session_id', $session->id)
            ->selectRaw('type, COALESCE(SUM(amount), 0) as total')
            ->groupBy('type')
            ->pluck('total', 'type');

        $opening = Money::add((string) ($sums[PosCashMovementType::Opening->value] ?? $session->opening_cash), '0');
        $cashIn = Money::add((string) ($sums[PosCashMovementType::CashIn->value] ?? '0'), '0');
        $saleCash = Money::add((string) ($sums[PosCashMovementType::SaleCash->value] ?? '0'), '0');
        $cashOut = Money::add((string) ($sums[PosCashMovementType::CashOut->value] ?? '0'), '0');
        $refundCash = Money::add((string) ($sums[PosCashMovementType::RefundCash->value] ?? '0'), '0');

        return Money::sub(
            Money::add(Money::add($opening, $cashIn), $saleCash),
            Money::add($cashOut, $refundCash),
        );
    }

    protected function recordMovement(
        PosSession $session,
        User $user,
        PosCashMovementType $type,
        string $amount,
        ?string $reason,
    ): PosCashMovement {
        if (! $session->isOpen()) {
            throw ValidationException::withMessages([
                'session' => 'Cash movements require an open session.',
            ]);
        }

        if (bccomp($amount, '0', 2) <= 0) {
            throw ValidationException::withMessages([
                'amount' => 'Amount must be greater than zero.',
            ]);
        }

        return PosCashMovement::query()->create([
            'pos_session_id' => $session->id,
            'type' => $type,
            'amount' => Money::add($amount, '0'),
            'reason' => $reason,
            'user_id' => $user->id,
        ]);
    }
}
