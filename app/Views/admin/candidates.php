<?php
/** @var \App\Models\Candidate[] $candidates */

// Group the candidates by election, then by position.
$grouped = [];
foreach ($candidates as $candidate) {
    $grouped[$candidate->electionTitle()][$candidate->positionTitle()][] = $candidate;
}
?>
<h1>Candidates</h1>

<p class="lead">Only you, the administrator, can put someone on a ballot. Add each candidate with their photo and manifesto before voting starts.</p>
<p><a class="btn" href="<?= e(url('/admin/candidates/new')) ?>">Add a candidate</a></p>

<?php if ($candidates === []): ?>
    <div class="notice">There are no candidates yet. Click <strong>Add a candidate</strong> to put the first person on a ballot.</div>
<?php endif; ?>

<?php foreach ($grouped as $electionTitle => $positions): ?>
    <section class="card election">
        <h2><?= e($electionTitle) ?></h2>

        <?php foreach ($positions as $positionTitle => $list): ?>
            <h3><?= e($positionTitle) ?> <span class="muted">(<?= count($list) ?>)</span></h3>
            <div class="table-wrap">
                <table class="table">
                    <thead><tr>
                        <th scope="col">Photo</th><th scope="col">Name</th><th scope="col">Party</th>
                        <th scope="col" class="num">Age</th><th scope="col">Fee</th><th scope="col">Action</th>
                    </tr></thead>
                    <tbody>
                    <?php foreach ($list as $c): $editable = $c->election()?->canEditSetup() ?? false; ?>
                        <tr>
                            <td>
                                <?php if ($c->hasPhoto()): ?>
                                    <img class="photo-preview" src="<?= e($c->photoUrl()) ?>" alt="Photo of <?= e($c->displayName()) ?>" width="48" height="60">
                                <?php endif; ?>
                            </td>
                            <td><?= e($c->displayName()) ?><?= $c->email() !== '' ? '<br><span class="hint">' . e($c->email()) . '</span>' : '' ?></td>
                            <td><?= $c->party() !== '' ? e($c->party()) : '<span class="muted">Independent</span>' ?></td>
                            <td class="num"><?= $c->age() ?></td>
                            <td><?= status_badge($c->feeStatus()) ?></td>
                            <td>
                                <?php if ($editable): ?>
                                    <div class="row-actions">
                                        <a class="btn btn-secondary btn-small" href="<?= e(url('/admin/candidates/edit?id=' . $c->id())) ?>">Edit</a>
                                        <form class="inline-form" method="post" action="<?= e(url('/admin/candidates/delete')) ?>">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="candidate_id" value="<?= $c->id() ?>">
                                            <button type="submit" class="btn btn-danger btn-small" data-confirm="Remove <?= e($c->displayName()) ?> from the ballot?">Remove</button>
                                        </form>
                                    </div>
                                <?php else: ?>
                                    <span class="hint">Locked: voting has started</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endforeach; ?>
    </section>
<?php endforeach; ?>