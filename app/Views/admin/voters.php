<?php
/** @var string $filter */
/** @var string[] $filters */
/** @var \App\Models\User[] $voters */

// Which buttons to show for each status: [button text, new status, style]
$actions = [
    'pending'   => [['Approve', 'approved', ''], ['Reject', 'rejected', 'btn-danger']],
    'approved'  => [['Suspend', 'suspended', 'btn-danger']],
    'rejected'  => [['Approve', 'approved', '']],
    'suspended' => [['Reinstate', 'approved', '']],
];
?>
<h1>Voters</h1>

<nav class="filters" aria-label="Filter voters by status">
    <?php foreach ($filters as $option): ?>
        <a href="<?= e(url('/admin/voters?status=' . $option)) ?>"
           class="<?= $filter === $option ? 'active' : '' ?>"
           <?= $filter === $option ? 'aria-current="page"' : '' ?>><?= e(label($option)) ?></a>
    <?php endforeach; ?>
</nav>

<?php if ($voters === []): ?>
    <p>No voters found for this filter.</p>
<?php else: ?>
    <div class="table-wrap">
        <table class="table">
            <thead>
            <tr>
                <th scope="col">Photo</th>
                <th scope="col">Voter number</th>
                <th scope="col">Name</th>
                <th scope="col">Email</th>
                <th scope="col" class="num">Age</th>
                <th scope="col">State</th>
                <th scope="col">Status</th>
                <th scope="col">Action</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($voters as $voter): ?>
                <tr>
                    <td>
                        <?php if (\App\Services\PhotoStorage::exists($voter->id())): ?>
                            <img class="table-photo" src="<?= e(url('/photo?user=' . $voter->id() . '&v=' . \App\Services\PhotoStorage::version($voter->id()))) ?>" alt="Photo of <?= e($voter->fullName()) ?>" width="40" height="50">
                        <?php else: ?>
                            <span class="muted">None</span>
                        <?php endif; ?>
                    </td>
                    <td><?= e($voter->voterNumber()) ?></td>
                    <td><?= e($voter->fullName()) ?></td>
                    <td><?= e($voter->email()) ?></td>
                    <td class="num"><?= $voter->age() ?></td>
                    <td><?= e($voter->state()) ?></td>
                    <td><?= status_badge($voter->status()) ?></td>
                    <td class="row-actions">
                        <?php foreach ($actions[$voter->status()] ?? [] as [$text, $newStatus, $style]): ?>
                            <form class="inline-form" method="post" action="<?= e(url('/admin/voters/status')) ?>">
                                <?= csrf_field() ?>
                                <input type="hidden" name="user_id" value="<?= $voter->id() ?>">
                                <input type="hidden" name="status" value="<?= e($newStatus) ?>">
                                <input type="hidden" name="return" value="<?= e($filter) ?>">
                                <button type="submit" class="btn btn-small <?= e($style) ?>"
                                        data-confirm="<?= e($text . ' ' . $voter->fullName() . '?') ?>"><?= e($text) ?></button>
                            </form>
                        <?php endforeach; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>
