<?php

namespace App\Services;

use App\Models\TaskBoardStatus;

class TaskBoardStatusService
{
    /**
     * @return list<array{key: string, label: string, header_bg: string, badge_bg: string, badge_text: string}>
     */
    public function definitions(?int $orgId): array
    {
        if (! $orgId) {
            return $this->configDefinitions();
        }

        $this->ensureDefaults($orgId);

        return TaskBoardStatus::query()
            ->where('organization_id', $orgId)
            ->orderBy('sort_order')
            ->get()
            ->map(fn (TaskBoardStatus $status) => $this->serialize($status))
            ->values()
            ->all();
    }

    /**
     * @return list<string>
     */
    public function validKeys(?int $orgId): array
    {
        return collect($this->definitions($orgId))
            ->pluck('key')
            ->filter()
            ->values()
            ->all();
    }

    /**
     * @return list<string>
     */
    public function closedKeys(?int $orgId): array
    {
        if (! $orgId) {
            return config('tasks.closed_statuses', ['done']);
        }

        $this->ensureDefaults($orgId);

        $closed = TaskBoardStatus::query()
            ->where('organization_id', $orgId)
            ->where('is_closed', true)
            ->pluck('key')
            ->all();

        return $closed !== [] ? $closed : config('tasks.closed_statuses', ['done']);
    }

    public function label(?int $orgId, string $key): string
    {
        $match = collect($this->definitions($orgId))->firstWhere('key', $key);

        return $match['label'] ?? ucfirst(str_replace('_', ' ', $key));
    }

    public function isClosedStatus(?int $orgId, string $status): bool
    {
        return in_array($status, $this->closedKeys($orgId), true);
    }

    public function ensureDefaults(?int $orgId): void
    {
        if (! $orgId) {
            return;
        }

        if (TaskBoardStatus::query()->where('organization_id', $orgId)->exists()) {
            return;
        }

        foreach ($this->configDefinitions() as $index => $status) {
            TaskBoardStatus::create([
                'organization_id' => $orgId,
                'key' => $status['key'],
                'label' => $status['label'],
                'sort_order' => $index,
                'header_bg' => $status['header_bg'],
                'badge_bg' => $status['badge_bg'],
                'badge_text' => $status['badge_text'],
                'is_closed' => in_array($status['key'], config('tasks.closed_statuses', ['done']), true),
            ]);
        }
    }

    /**
     * @return list<array{id: int, key: string, label: string, is_closed: bool, sort_order: int}>
     */
    public function manageList(?int $orgId): array
    {
        if (! $orgId) {
            return [];
        }

        $this->ensureDefaults($orgId);

        return TaskBoardStatus::query()
            ->where('organization_id', $orgId)
            ->orderBy('sort_order')
            ->get(['id', 'key', 'label', 'is_closed', 'sort_order'])
            ->map(fn (TaskBoardStatus $status) => [
                'id' => $status->id,
                'key' => $status->key,
                'label' => $status->label,
                'is_closed' => $status->is_closed,
                'sort_order' => $status->sort_order,
            ])
            ->values()
            ->all();
    }

    public function store(?int $orgId, array $data): TaskBoardStatus
    {
        if (! $orgId) {
            throw new \InvalidArgumentException('Organization is required.');
        }

        $this->ensureDefaults($orgId);

        $maxOrder = (int) TaskBoardStatus::query()
            ->where('organization_id', $orgId)
            ->max('sort_order');

        return TaskBoardStatus::create([
            'organization_id' => $orgId,
            'key' => $data['key'],
            'label' => $data['label'],
            'sort_order' => $maxOrder + 1,
            'header_bg' => $data['header_bg'] ?? '#f8fbff',
            'badge_bg' => $data['badge_bg'] ?? '#f1f5f9',
            'badge_text' => $data['badge_text'] ?? '#475569',
            'is_closed' => (bool) ($data['is_closed'] ?? false),
        ]);
    }

    public function update(?int $orgId, int $statusId, array $data): TaskBoardStatus
    {
        $status = $this->findForOrg($orgId, $statusId);

        $status->update([
            'label' => $data['label'],
            'is_closed' => (bool) ($data['is_closed'] ?? false),
        ]);

        return $status->fresh();
    }

    public function delete(?int $orgId, int $statusId): void
    {
        $status = $this->findForOrg($orgId, $statusId);

        $inUse = \App\Models\Task::query()
            ->where('organization_id', $orgId)
            ->where('status', $status->key)
            ->exists();

        if ($inUse) {
            throw new \InvalidArgumentException('Cannot delete a status that still has tasks assigned.');
        }

        if (TaskBoardStatus::query()->where('organization_id', $orgId)->count() <= 1) {
            throw new \InvalidArgumentException('At least one board status is required.');
        }

        $status->delete();
    }

    private function findForOrg(?int $orgId, int $statusId): TaskBoardStatus
    {
        if (! $orgId) {
            throw new \InvalidArgumentException('Organization is required.');
        }

        return TaskBoardStatus::query()
            ->where('organization_id', $orgId)
            ->whereKey($statusId)
            ->firstOrFail();
    }

    /**
     * @return list<array{key: string, label: string, header_bg: string, badge_bg: string, badge_text: string, is_closed: bool}>
     */
    private function configDefinitions(): array
    {
        $closed = config('tasks.closed_statuses', ['done']);

        return collect(config('tasks.board_statuses', []))
            ->map(function (array $status) use ($closed) {
                return array_merge($status, [
                    'is_closed' => (bool) ($status['is_closed'] ?? in_array($status['key'], $closed, true)),
                ]);
            })
            ->values()
            ->all();
    }

    /**
     * @return array{key: string, label: string, header_bg: string, badge_bg: string, badge_text: string, is_closed: bool, id?: int}
     */
    private function serialize(TaskBoardStatus $status): array
    {
        return [
            'id' => $status->id,
            'key' => $status->key,
            'label' => $status->label,
            'header_bg' => $status->header_bg,
            'badge_bg' => $status->badge_bg,
            'badge_text' => $status->badge_text,
            'is_closed' => (bool) $status->is_closed,
        ];
    }

    /**
     * @param  list<int>  $orderedIds
     */
    public function reorder(?int $orgId, array $orderedIds): void
    {
        if (! $orgId) {
            throw new \InvalidArgumentException('Organization is required.');
        }

        $this->ensureDefaults($orgId);

        $orderedIds = array_values(array_unique(array_map('intval', $orderedIds)));

        $statuses = TaskBoardStatus::query()
            ->where('organization_id', $orgId)
            ->whereIn('id', $orderedIds)
            ->get()
            ->keyBy('id');

        $total = TaskBoardStatus::query()->where('organization_id', $orgId)->count();

        if ($statuses->count() !== count($orderedIds) || count($orderedIds) !== $total) {
            throw new \InvalidArgumentException('Invalid status order.');
        }

        foreach ($orderedIds as $index => $id) {
            $statuses[$id]->update(['sort_order' => $index]);
        }
    }
}
