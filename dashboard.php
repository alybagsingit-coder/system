<?php
declare(strict_types=1);

require_once __DIR__ . '/auth.php';
$studentId = current_student_id();

try {
    $countsStatement = $pdo->prepare(
        "SELECT COUNT(*) AS total,
                COALESCE(SUM(Status = 'Pending'), 0) AS pending,
                COALESCE(SUM(Status = 'In Progress'), 0) AS in_progress,
                COALESCE(SUM(Status = 'Completed'), 0) AS completed
         FROM TASK
         WHERE Student_ID = :student_id"
    );
    $countsStatement->execute(['student_id' => $studentId]);
    $counts = $countsStatement->fetch();

    $tasksStatement = $pdo->prepare(
        'SELECT Task_ID, Task_Name, Subject, Deadline, Deadline_Time, Priority, Status
         FROM TASK
         WHERE Student_ID = :student_id
           AND TIMESTAMP(Deadline, Deadline_Time) > CURRENT_TIMESTAMP
         ORDER BY Deadline ASC, Deadline_Time ASC, FIELD(Priority, \'High\', \'Medium\', \'Low\')
         LIMIT 5'
    );
    $tasksStatement->execute(['student_id' => $studentId]);
    $upcomingTasks = $tasksStatement->fetchAll();
} catch (PDOException $exception) {
    error_log('Student Task Planner dashboard query failed: ' . $exception->getMessage());
    http_response_code(500);
    exit('Dashboard data could not be loaded. Please try again later.');
}

render_header('Dashboard');
render_flash(take_flash());
?>
<section class="stats-grid" aria-label="Task summary">
    <a class="stat-card stat-card--total" href="<?= h(app_url('tasks.php?status=all')) ?>" aria-label="View all tasks">
        <div class="stat-topline"><span>Total tasks</span><span class="stat-icon" aria-hidden="true">T</span></div>
        <p class="stat-value"><?= (int) $counts['total'] ?></p>
        <p class="stat-caption">Across your planner</p>
    </a>
    <a class="stat-card stat-card--pending" href="<?= h(app_url('tasks.php?status=' . rawurlencode('Pending'))) ?>" aria-label="View pending tasks">
        <div class="stat-topline"><span>Pending</span><span class="stat-icon" aria-hidden="true">P</span></div>
        <p class="stat-value"><?= (int) $counts['pending'] ?></p>
        <p class="stat-caption">Waiting to get started</p>
    </a>
    <a class="stat-card stat-card--progress" href="<?= h(app_url('tasks.php?status=' . rawurlencode('In Progress'))) ?>" aria-label="View tasks in progress">
        <div class="stat-topline"><span>In progress</span><span class="stat-icon" aria-hidden="true">↗</span></div>
        <p class="stat-value"><?= (int) $counts['in_progress'] ?></p>
        <p class="stat-caption">Currently underway</p>
    </a>
    <a class="stat-card stat-card--complete" href="<?= h(app_url('tasks.php?status=Completed')) ?>" aria-label="View completed tasks">
        <div class="stat-topline"><span>Completed</span><span class="stat-icon" aria-hidden="true">✓</span></div>
        <p class="stat-value"><?= (int) $counts['completed'] ?></p>
        <p class="stat-caption">Work you have finished</p>
    </a>
</section>
<section class="content-panel">
    <div class="section-heading"><div><span class="eyebrow">STAY AHEAD</span><h2>Upcoming deadlines</h2></div><a class="button button--primary" href="<?= h(app_url('tasks.php?action=add')) ?>">＋ <span>Add a task</span></a></div>
<?php if ($upcomingTasks === []): ?>
    <div class="empty-state"><span class="empty-icon" aria-hidden="true">✓</span><h3>All clear for now</h3><p>You have no upcoming deadlines. Add a task whenever something new comes up.</p><a href="<?= h(app_url('tasks.php?action=add')) ?>">Create your first task</a></div>
<?php else: ?>
    <div class="table-wrap"><table class="task-table">
        <thead><tr><th>Task</th><th>Subject</th><th>Deadline</th><th>Priority</th><th>Status</th></tr></thead>
        <tbody>
        <?php foreach ($upcomingTasks as $task): ?>
            <tr>
                <td><a class="task-name" href="<?= h(app_url('tasks.php?action=edit&id=' . (int) $task['Task_ID'])) ?>"><?= h($task['Task_Name']) ?></a></td>
                <td><?= h($task['Subject']) ?></td>
                <td><span class="deadline"><?= h(date('M j, Y', strtotime($task['Deadline']))) ?></span><small class="deadline-time"><?= h(date('g:i A', strtotime($task['Deadline_Time']))) ?></small></td>
                <td><span class="badge badge--priority-<?= h(strtolower($task['Priority'])) ?>"><?= h($task['Priority']) ?></span></td>
                <td><span class="badge badge--status-<?= h(strtolower(str_replace(' ', '-', $task['Status']))) ?>"><?= h($task['Status']) ?></span></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table></div>
<?php endif; ?>
</section>
<?php render_footer(); ?>
