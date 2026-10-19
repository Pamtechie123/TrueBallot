<?php /** @var \App\Models\CandidateApplication[] $applications */ ?>
<h1>My applications</h1>

<p><a class="btn" href="<?= e(url('/candidates/apply')) ?>">New application</a></p>

<?php if ($applications === []): ?>
    <p>You have not applied for any position yet.</p>
<?php else: ?>
    <div class="table-wrap">
        <table class="table">
            <thead>
            <tr>
                <th scope="col">Position</th>
                <th scope="col">Election</th>
                <th scope="col">Submitted</th>
                <th scope="col">Fee</th>
                <th scope="col">Decision</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($applications as $app): ?>
                <tr>
                    <td><?= e($app->positionTitle()) ?></td>
                    <td><?= e($app->electionTitle()) ?></td>
                    <td><?= e(format_datetime($app->createdAt())) ?></td>
                    <td><?= e(naira($app->feeAmount())) ?> <?= status_badge($app->feeStatus()) ?></td>
                    <td><?= status_badge($app->status()) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>
