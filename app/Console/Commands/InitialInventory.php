<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\InitialInventoryService;
use Illuminate\Console\Command;
use RuntimeException;

class InitialInventory extends Command
{
    protected $signature = 'paytakht:initial-inventory {--dry-run} {--apply} {--user= : Admin user ID required for apply}';

    protected $description = 'Validate the authoritative initial inventory without inventing historical costs';

    public function handle(InitialInventoryService $service): int
    {
        if ((bool) $this->option('dry-run') === (bool) $this->option('apply')) {
            $this->error('Choose exactly one of --dry-run or --apply.');

            return self::FAILURE;
        }
        try {
            if ($this->option('apply') && $service->isApplied()) {
                $this->info('ALREADY APPLIED: controlled no-op.');

                return self::SUCCESS;
            }
            $rows = $service->preview();
            $this->line(InitialInventoryService::BATCH);
            $this->line('Rows: 235; units: 396; SHA-256: '.InitialInventoryService::CHECKSUM);
            $this->table(['Page', 'Exact name', 'Quantity', 'Match', 'Product', 'Apply'], array_map('array_values', $rows));
            $counts = array_count_values(array_column($rows, 'status'));
            foreach (['CREATE', 'SAFE_MATCH', 'CONFLICT'] as $status) {
                $count = $counts[$status] ?? 0;
                $this->line("{$status}: {$count}");
            }
            if ($this->option('apply')) {
                $userId = filter_var($this->option('user'), FILTER_VALIDATE_INT);
                if (! $userId || ! User::query()->whereKey($userId)->where('role', 'ADMIN')->where('active', true)->exists()) {
                    throw new RuntimeException('--user must identify an active admin.');
                }
                $result = $service->apply($userId);
                $this->info($result['already_applied'] ? 'ALREADY APPLIED: controlled no-op.' : "APPLIED: created={$result['created']} matched={$result['matched']} quantity={$result['quantity']}");
            }

            return self::SUCCESS;
        } catch (RuntimeException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }
    }
}
