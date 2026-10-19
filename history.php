<?php
declare(strict_types=1);

require_once __DIR__ . '/auth.php';
$studentId = current_student_id();

try {
    $historyStatement = $pdo->prepare(
        "SELECT Task_ID, Task_Name, Subject, Description, Deadline, Deadline_Time, Priority, Status
         FROM TASK
         WHERE Student_ID = :student_id
           AND (
               Status = 'Completed'
               OR TIMESTAMP(Deadline, Deadline_Time) <= CURRENT_TIMESTAMP
           )
         ORDER BY Deadline DESC, Deadline_Time DESC, Task_ID DESC"
    );
    $historyStatement->execute(['student_id' => $studentId]);
    $historyTasks = $historyStatement->fetchAll();
} catch (PDOException $exception) {
    error_log('Student Task Planner task history query failed: ' . $exception->getMessage());
    http_response_code(500);
    exit('Task history could not be loaded. Please try again later.');
}

render_header('Task history');
render_flash(take_flash());
?>
<section class="content-panel task-list-panel">
    <div class="section-heading">
        <div>
            <span class="eyebrow">YOUR ACADEMIC RECORD</span>
            <h2>History tasks</h2>
            <p class="section-subtitle">Completed tasks and tasks whose deadlines have passed.</p>
        </div>
        <a class="button button--quiet" href="<?= h(app_url('tasks.php')) ?>">View all tasks</a>
    </div>
    <?php if ($historyTasks === []): ?>
        <div class="empty-state">
            <span class="empty-icon" aria-hidden="true">H</span>
            <h3>No history yet</h3>
            <p>Completed tasks and tasks past their deadlines will appear here.</p>
            <a href="<?= h(app_url('tasks.php')) ?>">View your tasks</a>
        </div>
    <?php else: ?>
        <p class="section-subtitle"><?= count($historyTasks) ?> history task<?= count($historyTasks) === 1 ? '' : 's' ?></p>
        <div class="table-wrap">
            <table class="task-table">
                <thead><tr><th>Task</th><th>Subject</th><th>Deadline</th><th>Priority</th><th>Status</th><th><span class="visually-hidden">Actions</span></th></tr></thead>
                <tbody>
                <?php foreach ($historyTasks as $task): ?>
                    <tr>
                        <td>
                            <div class="task-cell">
                                <strong><?= h($task['Task_Name']) ?></strong>
                                <?php if ($task['Description'] !== null && $task['Description'] !== ''): ?>
                                    <span><?= h($task['Description']) ?></span>
                                <?php endif; ?>
                            </div>
                        </td>
                        <td><?= h($task['Subject']) ?></td>
                        <td><span class="deadline"><?= h(date('M j, Y', strtotime($task['Deadline']))) ?></span><small class="deadline-time"><?= h(date('g:i A', strtotime($task['Deadline_Time']))) ?></small></td>
                        <td><span class="badge badge--priority-<?= h(strtolower($task['Priority'])) ?>"><?= h($task['Priority']) ?></span></td>
                        <td><span class="badge badge--status-<?= h(strtolower(str_replace(' ', '-', $task['Status']))) ?>"><?= h($task['Status']) ?></span></td>
                        <td><a class="text-action" href="<?= h(app_url('tasks.php?action=edit&id=' . (int) $task['Task_ID'])) ?>">View</a></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>
<?php render_footer(); ?>
