<?php

/**
 * Default task board columns — seeded into task_board_statuses per organization.
 * Customize columns per org in the task_board_statuses table.
 */
return [
    'board_statuses' => [
        [
            'key' => 'todo',
            'label' => 'To do',
            'header_bg' => '#f8fbff',
            'badge_bg' => '#f1f5f9',
            'badge_text' => '#475569',
            'is_closed' => false,
        ],
        [
            'key' => 'in_progress',
            'label' => 'In progress',
            'header_bg' => '#eff6ff',
            'badge_bg' => '#dbeafe',
            'badge_text' => '#1d4ed8',
            'is_closed' => false,
        ],
        [
            'key' => 'done',
            'label' => 'Done',
            'header_bg' => '#ecfdf3',
            'badge_bg' => '#d1fadf',
            'badge_text' => '#067647',
            'is_closed' => true,
        ],
        [
            'key' => 'reopen',
            'label' => 'Reopen',
            'header_bg' => '#fff7ed',
            'badge_bg' => '#ffedd5',
            'badge_text' => '#c2410c',
            'is_closed' => false,
        ],
    ],

    /** Statuses that count as closed for completed_at tracking. */
    'closed_statuses' => ['done'],
];
