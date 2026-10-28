<?php
/** @var array $stats */
/** @var array $totals */
/** @var array $ageBands */
/** @var array $byState */
/** @var array $bySex */
/** @var array $byDisability */
/** @var \App\Models\AuditLog[] $activity */
?>
<h1>Admin dashboard</h1>

<section aria-labelledby="stats-heading">
    <h2 id="stats-heading">Registered voters</h2>
    <div class="tiles">
        <div class="tile"><p class="tile-number"><?= (int) $stats['total'] ?></p><p class="tile-label">Total registered</p></div>
        <a class="tile tile-link" href="<?= e(url('/admin/voters?status=pending')) ?>"><p class="tile-number"><?= (int) $stats['pending'] ?></p><p class="tile-label">Waiting for approval</p></a>
        <div class="tile"><p class="tile-number"><?= (int) $stats['approved'] ?></p><p class="tile-label">Approved</p></div>
        <div class="tile"><p class="tile-number"><?= (int) ($stats['rejected'] + $stats['suspended']) ?></p><p class="tile-label">Rejected or suspended</p></div>
    </div>
    <p><a class="btn" href="<?= e(url('/admin/voters')) ?>">Manage voters</a></p>
</section>

<section aria-labelledby="elections-heading">
    <h2 id="elections-heading">Elections</h2>
    <div class="tiles">
        <a class="tile tile-link" href="<?= e(url('/admin/elections')) ?>"><p class="tile-number"><?= (int) $totals['elections'] ?></p><p class="tile-label">Elections</p></a>
        <a class="tile tile-link" href="<?= e(url('/admin/candidates')) ?>"><p class="tile-number"><?= (int) $totals['candidates'] ?></p><p class="tile-label">Candidates on ballots</p></a>
        <a class="tile tile-link" href="<?= e(url('/results')) ?>"><p class="tile-number"><?= (int) $totals['votes'] ?></p><p class="tile-label">Votes cast</p></a>
    </div>
    <p>
        <a class="btn" href="<?= e(url('/admin/elections')) ?>">Manage elections</a>
        <a class="btn btn-secondary" href="<?= e(url('/admin/candidates')) ?>">Manage candidates</a>
    </p>
</section>

<section aria-labelledby="demo-heading">
    <h2 id="demo-heading">Voters by group</h2>

    <?php if ($stats['total'] === 0): ?>
        <p>No voters have registered yet.</p>
    <?php else: ?>
        <div class="grid-2">
            <?php
            $groups = [
                'Age group'         => $ageBands,
                'Sex'               => $bySex,
                'Disability status' => $byDisability,
                'State'             => $byState,
            ];
            foreach ($groups as $title => $rows): ?>
                <table class="table">
                    <caption><?= e($title) ?></caption>
                    <thead><tr><th scope="col">Group</th><th scope="col" class="num">Voters</th></tr></thead>
                    <tbody>
                    <?php foreach ($rows as $row): ?>
                        <tr>
                            <td><?= e(label((string) $row['label'])) ?></td>
                            <td class="num"><?= (int) $row['total'] ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>

<section aria-labelledby="activity-heading">
    <h2 id="activity-heading">Recent activity</h2>

    <?php if ($activity === []): ?>
        <p>No administrator activity yet.</p>
    <?php else: ?>
        <div class="table-wrap">
            <table class="table">
                <thead><tr><th scope="col">When</th><th scope="col">Administrator</th><th scope="col">Action</th><th scope="col">Details</th></tr></thead>
                <tbody>
                <?php foreach ($activity as $log): ?>
                    <tr>
                        <td><?= e($log->createdAt()) ?></td>
                        <td><?= e($log->actorName()) ?></td>
                        <td><?= e(label($log->action())) ?></td>
                        <td><?= e($log->details()) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>