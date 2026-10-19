<?php
declare(strict_types=1);

require_once __DIR__ . '/auth.php';
$studentId = current_student_id();
$priorities = ['Low', 'Medium', 'High'];
$statuses = ['Pending', 'In Progress', 'Completed'];
$error = '';
$search = trim(form_value($_GET, 'q'));
$filterStatus = form_value($_GET, 'status');
$filterPriority = form_value($_GET, 'priority');
if ($filterStatus !== 'all' && !in_array($filterStatus, $statuses, true)) {
    $filterStatus = '';
}
if (!in_array($filterPriority, $priorities, true)) {
    $filterPriority = '';
}
$formTask = [
    'Task_Name' => '',
    'Subject' => '',
    'Description' => '',
    'Deadline' => '',
    'Deadline_Time' => '23:59',
    'Priority' => 'Medium',
    'Status' => 'Pending',
];
$action = array_key_exists('action', $_GET) ? form_value($_GET, 'action') : 'list';
$taskId = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    require_post();
    verify_csrf();
    $postAction = form_value($_POST, 'action');
    $postedTaskId = filter_var($_POST['task_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

    if ($postAction === 'delete') {
        if ($postedTaskId === false) {
            http_response_code(400);
            exit('Invalid task identifier.');
        }

        try {
            $deleteStatement = $pdo->prepare(
                'DELETE FROM TASK WHERE Task_ID = :task_id AND Student_ID = :student_id'
            );
            $deleteStatement->execute(['task_id' => $postedTaskId, 'student_id' => $studentId]);
            if ($deleteStatement->rowCount() !== 1) {
                http_response_code(404);
                exit('Task not found.');
            }
            set_flash('Task deleted.');
            header('Location: tasks.php');
            exit;
        } catch (PDOException $exception) {
            error_log('Student Task Planner task deletion failed: ' . $exception->getMessage());
            set_flash('Task could not be deleted right now. Please try again.', 'error');
            header('Location: tasks.php');
            exit;
        }
    }

    if (!in_array($postAction, ['create', 'update'], true)) {
        http_response_code(400);
        exit('Invalid task action.');
    }
    if ($postAction === 'update' && $postedTaskId === false) {
        http_response_code(400);
        exit('Invalid task identifier.');
    }

    $action = $postAction === 'create' ? 'add' : 'edit';
    $taskId = $postedTaskId === false ? null : $postedTaskId;
    $formTask = [
        'Task_Name' => trim(form_value($_POST, 'task_name')),
        'Subject' => trim(form_value($_POST, 'subject')),
        'Description' => trim(form_value($_POST, 'description')),
        'Deadline' => trim(form_value($_POST, 'deadline')),
        'Deadline_Time' => form_value($_POST, 'deadline_time'),
        'Priority' => form_value($_POST, 'priority'),
        'Status' => form_value($_POST, 'status'),
    ];

    $date = DateTimeImmutable::createFromFormat('!Y-m-d', $formTask['Deadline']);
    $dateErrors = DateTimeImmutable::getLastErrors();
    $validDate = $date !== false
        && ($dateErrors === false || ($dateErrors['warning_count'] === 0 && $dateErrors['error_count'] === 0))
        && $date->format('Y-m-d') === $formTask['Deadline'];
    $validTime = preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', $formTask['Deadline_Time']) === 1;

    if ($formTask['Task_Name'] === '' || text_length($formTask['Task_Name']) > 255) {
        $error = 'Task name is required and must be no longer than 255 characters.';
    } elseif ($formTask['Subject'] === '' || text_length($formTask['Subject']) > 150) {
        $error = 'Subject is required and must be no longer than 150 characters.';
    } elseif (strlen($formTask['Description']) > 65535) {
        $error = 'Description is too long.';
    } elseif (!$validDate) {
        $error = 'Enter a valid deadline in YYYY-MM-DD format.';
    } elseif (!$validTime) {
        $error = 'Enter a valid deadline time in HH:MM format.';
    } elseif (!in_array($formTask['Priority'], $priorities, true)) {
        $error = 'Choose a valid priority.';
    } elseif (!in_array($formTask['Status'], $statuses, true)) {
        $error = 'Choose a valid task status.';
    } else {
        try {
            if ($postAction === 'create') {
                $saveStatement = $pdo->prepare(
                    'INSERT INTO TASK (Student_ID, Task_Name, Subject, Description, Deadline, Deadline_Time, Priority, Status)
                     VALUES (:student_id, :task_name, :subject, :description, :deadline, :deadline_time, :priority,
                             CASE WHEN TIMESTAMP(:deadline_for_status, :deadline_time_for_status) <= CURRENT_TIMESTAMP
                                  THEN \'Completed\' ELSE :status END)'
                );
                $saveStatement->execute([
                    'student_id' => $studentId,
                    'task_name' => $formTask['Task_Name'],
                    'subject' => $formTask['Subject'],
                    'description' => $formTask['Description'] === '' ? null : $formTask['Description'],
                    'deadline' => $formTask['Deadline'],
                    'deadline_time' => $formTask['Deadline_Time'] . ':00',
                    'deadline_for_status' => $formTask['Deadline'],
                    'deadline_time_for_status' => $formTask['Deadline_Time'] . ':00',
                    'priority' => $formTask['Priority'],
                    'status' => $formTask['Status'],
                ]);
                set_flash('Task created.');
            } else {
                $ownershipStatement = $pdo->prepare(
                    'SELECT Task_ID FROM TASK WHERE Task_ID = :task_id AND Student_ID = :student_id'
                );
                $ownershipStatement->execute(['task_id' => $taskId, 'student_id' => $studentId]);
                if (!$ownershipStatement->fetch()) {
                    http_response_code(404);
                    exit('Task not found.');
                }

                $saveStatement = $pdo->prepare(
                    "UPDATE TASK
                     SET Task_Name = :task_name, Subject = :subject, Description = :description,
                         Deadline = :deadline, Deadline_Time = :deadline_time,
                         Priority = :priority,
                         Status = CASE
                             WHEN TIMESTAMP(:deadline_for_status, :deadline_time_for_status) <= CURRENT_TIMESTAMP
                             THEN 'Completed' ELSE :status END
                     WHERE Task_ID = :task_id AND Student_ID = :student_id"
                );
                $saveStatement->execute([
                    'task_name' => $formTask['Task_Name'],
                    'subject' => $formTask['Subject'],
                    'description' => $formTask['Description'] === '' ? null : $formTask['Description'],
                    'deadline' => $formTask['Deadline'],
                    'deadline_time' => $formTask['Deadline_Time'] . ':00',
                    'deadline_for_status' => $formTask['Deadline'],
                    'deadline_time_for_status' => $formTask['Deadline_Time'] . ':00',
                    'priority' => $formTask['Priority'],
                    'status' => $formTask['Status'],
                    'task_id' => $taskId,
                    'student_id' => $studentId,
                ]);
                set_flash('Task updated.');
            }
            header('Location: tasks.php');
            exit;
        } catch (PDOException $exception) {
            error_log('Student Task Planner task save failed: ' . $exception->getMessage());
            $error = 'Task could not be saved right now. Please try again.';
        }
    }
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST' && $action === 'edit') {
    if ($taskId === false) {
        http_response_code(400);
        exit('Invalid task identifier.');
    }
    try {
        $loadStatement = $pdo->prepare(
            'SELECT Task_Name, Subject, Description, Deadline, Deadline_Time, Priority, Status
             FROM TASK WHERE Task_ID = :task_id AND Student_ID = :student_id'
        );
        $loadStatement->execute(['task_id' => $taskId, 'student_id' => $studentId]);
        $loadedTask = $loadStatement->fetch();
    } catch (PDOException $exception) {
        error_log('Student Task Planner task lookup failed: ' . $exception->getMessage());
        http_response_code(500);
        exit('Task could not be loaded. Please try again later.');
    }
    if (!$loadedTask) {
        http_response_code(404);
        exit('Task not found.');
    }
    $formTask = $loadedTask;
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST' && !in_array($action, ['list', 'add', 'edit'], true)) {
    http_response_code(404);
    exit('Page not found.');
}

render_header($action === 'add' ? 'Add task' : ($action === 'edit' ? 'Edit task' : 'Your tasks'));
render_flash(take_flash());
if ($error !== ''):
    ?><div class="notice notice--error" role="alert"><span class="notice-icon" aria-hidden="true">!</span><p><?= h($error) ?></p></div><?php
endif;

if ($action === 'add' || $action === 'edit'):
    $formAction = $action === 'edit'
        ? app_url('tasks.php?action=edit&id=' . (int) $taskId)
        : app_url('tasks.php?action=add');
    ?>
    <section class="content-panel form-panel">
        <div class="section-heading"><div><span class="eyebrow"><?= $action === 'edit' ? 'UPDATE YOUR PLANNER' : 'MAKE A PLAN' ?></span><h2><?= $action === 'edit' ? 'Task details' : 'New task details' ?></h2></div></div>
        <form class="task-form" method="post" action="<?= h($formAction) ?>">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="<?= $action === 'edit' ? 'update' : 'create' ?>">
            <?php if ($action === 'edit'): ?>
                <input type="hidden" name="task_id" value="<?= (int) $taskId ?>">
            <?php endif; ?>

            <div class="form-grid">
                <div class="field field--wide">
                    <label for="task_name">Task name <span class="required-mark" aria-hidden="true">*</span></label>
                    <input class="form-control" id="task_name" name="task_name" type="text" maxlength="255" required value="<?= h($formTask['Task_Name']) ?>" placeholder="e.g. Database normalization report">
                </div>
                <div class="field">
                    <label for="subject">Subject <span class="required-mark" aria-hidden="true">*</span></label>
                    <input class="form-control" id="subject" name="subject" type="text" maxlength="150" required value="<?= h($formTask['Subject']) ?>" placeholder="e.g. Database Systems">
                </div>
                <div class="field">
                    <label for="deadline">Deadline date <span class="required-mark" aria-hidden="true">*</span></label>
                    <input class="form-control" id="deadline" name="deadline" type="date" required value="<?= h($formTask['Deadline']) ?>">
                </div>
                <div class="field">
                    <label for="deadline_time">Deadline time <span class="required-mark" aria-hidden="true">*</span></label>
                    <input class="form-control" id="deadline_time" name="deadline_time" type="time" required value="<?= h(substr($formTask['Deadline_Time'], 0, 5)) ?>">
                    <span class="field-hint">Tasks become completed after the deadline date passes.</span>
                </div>
                <div class="field">
                    <label for="priority">Priority</label>
                    <select class="form-control" id="priority" name="priority" required>
                        <?php foreach ($priorities as $priority): ?>
                            <option value="<?= h($priority) ?>"<?= $formTask['Priority'] === $priority ? ' selected' : '' ?>><?= h($priority) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="field">
                    <label for="status">Status</label>
                    <select class="form-control" id="status" name="status" required>
                        <?php foreach ($statuses as $status): ?>
                            <option value="<?= h($status) ?>"<?= $formTask['Status'] === $status ? ' selected' : '' ?>><?= h($status) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="field field--wide">
                    <label for="description">Description <span class="label-hint">Optional</span></label>
                    <textarea class="form-control" id="description" name="description" rows="4" maxlength="65535" placeholder="Add a few details to help you get started..."><?= h((string) ($formTask['Description'] ?? '')) ?></textarea>
                </div>
            </div>
            <div class="form-actions">
                <a class="button button--quiet" href="<?= h(app_url('tasks.php')) ?>">Cancel</a>
                <button class="button button--primary" type="submit"><?= $action === 'edit' ? 'Save changes' : 'Create task' ?></button>
            </div>
        </form>
    </section>
<?php else:
    try {
        $conditions = ['Student_ID = :student_id'];
        $parameters = ['student_id' => $studentId];
        if ($filterStatus === '') {
            $conditions[] = "Status IN ('Pending', 'In Progress')";
        }
        if ($search !== '') {
            $conditions[] = '(Task_Name LIKE :search_name OR Subject LIKE :search_subject OR Description LIKE :search_description)';
            $term = '%' . $search . '%';
            $parameters['search_name'] = $term;
            $parameters['search_subject'] = $term;
            $parameters['search_description'] = $term;
        }
        if ($filterStatus !== '' && $filterStatus !== 'all') {
            $conditions[] = 'Status = :filter_status';
            $parameters['filter_status'] = $filterStatus;
        }
        if ($filterPriority !== '') {
            $conditions[] = 'Priority = :filter_priority';
            $parameters['filter_priority'] = $filterPriority;
        }
        $listStatement = $pdo->prepare(
            'SELECT Task_ID, Task_Name, Subject, Description, Deadline, Deadline_Time, Priority, Status
             FROM TASK WHERE ' . implode(' AND ', $conditions) . '
             ORDER BY Deadline ASC, Deadline_Time ASC, FIELD(Priority, \'High\', \'Medium\', \'Low\'), Task_ID DESC'
        );
        $listStatement->execute($parameters);
        $tasks = $listStatement->fetchAll();
    } catch (PDOException $exception) {
        error_log('Student Task Planner task list query failed: ' . $exception->getMessage());
        http_response_code(500);
        exit('Tasks could not be loaded. Please try again later.');
    }
    ?>
    <section class="content-panel task-list-panel">
        <div class="section-heading">
            <div><span class="eyebrow">YOUR ACADEMIC WORK</span><h2>All tasks</h2><p class="section-subtitle"><?= count($tasks) ?> task<?= count($tasks) === 1 ? '' : 's' ?><?= ($search !== '' || $filterStatus !== '' || $filterPriority !== '') ? ' matching your filters' : ' in your planner' ?></p></div>
            <a class="button button--primary" href="<?= h(app_url('tasks.php?action=add')) ?>">＋ <span>Add a task</span></a>
        </div>
        <form class="filter-bar" method="get" action="<?= h(app_url('tasks.php')) ?>" role="search">
            <div class="search-field">
                <label class="visually-hidden" for="task-search">Search tasks</label>
                <span class="search-icon" aria-hidden="true">⌕</span>
                <input class="form-control" id="task-search" name="q" type="search" maxlength="150" value="<?= h($search) ?>" placeholder="Search task, subject, or details">
            </div>
            <div class="filter-select">
                <label class="visually-hidden" for="status-filter">Filter by status</label>
                <select class="form-control" id="status-filter" name="status" onchange="this.form.submit()">
                    <option value=""<?= $filterStatus === '' ? ' selected' : '' ?>>Active tasks</option>
                    <option value="all"<?= $filterStatus === 'all' ? ' selected' : '' ?>>All statuses</option>
                    <?php foreach ($statuses as $status): ?>
                        <option value="<?= h($status) ?>"<?= $filterStatus === $status ? ' selected' : '' ?>><?= h($status) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="filter-select">
                <label class="visually-hidden" for="priority-filter">Filter by priority</label>
                <select class="form-control" id="priority-filter" name="priority" onchange="this.form.submit()">
                    <option value="">All priorities</option>
                    <?php foreach ($priorities as $priority): ?>
                        <option value="<?= h($priority) ?>"<?= $filterPriority === $priority ? ' selected' : '' ?>><?= h($priority) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php if ($search !== '' || $filterStatus !== '' || $filterPriority !== ''): ?>
                <a class="clear-filters" href="<?= h(app_url('tasks.php')) ?>">Clear</a>
            <?php endif; ?>
        </form>
    <?php if ($tasks === []): ?>
        <div class="empty-state">
            <span class="empty-icon" aria-hidden="true"><?= ($search !== '' || $filterStatus !== '' || $filterPriority !== '') ? '⌕' : '+' ?></span>
            <?php if ($search !== '' || $filterStatus !== '' || $filterPriority !== ''): ?>
                <h3>No tasks match these filters</h3><p>Try another search term or clear one of the selected filters.</p>
                <a href="<?= h(app_url('tasks.php')) ?>">Clear filters</a>
            <?php else: ?>
                <h3>Your planner is ready</h3><p>Add your first academic task and keep its deadline, priority, and progress together.</p>
                <a class="button button--primary" href="<?= h(app_url('tasks.php?action=add')) ?>">Create your first task</a>
            <?php endif; ?>
        </div>
    <?php else: ?>
        <div class="table-wrap"><table class="task-table">
            <thead><tr><th>Task</th><th>Subject</th><th>Deadline</th><th>Priority</th><th>Status</th><th><span class="visually-hidden">Actions</span></th></tr></thead>
            <tbody>
            <?php foreach ($tasks as $task): ?>
                <tr>
                    <td><div class="task-cell"><strong><?= h($task['Task_Name']) ?></strong><?php if ($task['Description'] !== null && $task['Description'] !== ''): ?><span><?= h($task['Description']) ?></span><?php endif; ?></div></td>
                    <td><?= h($task['Subject']) ?></td>
                    <td><span class="deadline"><?= h(date('M j, Y', strtotime($task['Deadline']))) ?></span><small class="deadline-time"><?= h(date('g:i A', strtotime($task['Deadline_Time']))) ?></small></td>
                    <td><span class="badge badge--priority-<?= h(strtolower($task['Priority'])) ?>"><?= h($task['Priority']) ?></span></td>
                    <td><span class="badge badge--status-<?= h(strtolower(str_replace(' ', '-', $task['Status']))) ?>"><?= h($task['Status']) ?></span></td>
                    <td>
                        <div class="table-actions">
                            <a class="text-action" href="<?= h(app_url('tasks.php?action=edit&id=' . (int) $task['Task_ID'])) ?>">Edit</a>
                            <form method="post" action="<?= h(app_url('tasks.php')) ?>" onsubmit="return confirm('Delete this task? This cannot be undone.');">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="task_id" value="<?= (int) $task['Task_ID'] ?>">
                                <button class="text-action text-action--danger" type="submit">Delete</button>
                            </form>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table></div>
    <?php endif; ?>
    </section>
<?php endif; ?>
<?php render_footer(); ?>
