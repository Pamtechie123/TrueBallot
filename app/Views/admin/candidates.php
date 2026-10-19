<?php
/** @var string $filter */
/** @var string[] $filters */
/** @var \App\Models\CandidateApplication[] $applications */
/** @var string[] $feeStatuses */
?>
<h1>Candidate applications</h1>

<nav class="filters" aria-label="Filter applications by status">
    <?php foreach ($filters as $option): ?>
        <a href="<?= e(url('/admin/candidates?status=' . $option)) ?>"
           class="<?= $filter === $option ? 'active' : '' ?>"
           <?= $filter === $option ? 'aria-current="page"' : '' ?>><?= e(label($option)) ?></a>
    <?php endforeach; ?>
</nav>

<?php if ($applications === []): ?>
    <p>No applications found for this filter.</p>
<?php endif; ?>

<?php foreach ($applications as $app): ?>
    <article class="card election">
        <header class="election-head">
            <h3><?= e($app->displayName()) ?></h3>
            <?= status_badge($app->status()) ?>
        </header>

        <p class="muted">
            Applying for <strong><?= e($app->positionTitle()) ?></strong> in <?= e($app->electionTitle()) ?>
            (<?= e(phase_label($app->electionPhase())) ?>)
        </p>
        <?php if (\App\Services\PhotoStorage::exists($app->userId())): ?>
            <img class="photo-preview" src="<?= e(url('/photo?user=' . $app->userId() . '&v=' . \App\Services\PhotoStorage::version($app->userId()))) ?>" alt="Photo of <?= e($app->applicantName()) ?>" width="80" height="100">
        <?php else: ?>
            <p class="hint">No photo uploaded.</p>
        <?php endif; ?>
        <p class="muted">
            Applicant: <?= e($app->applicantName()) ?>, <?= e($app->applicantEmail()) ?>,
            age <?= $app->applicantAge() ?>, <?= e($app->applicantState()) ?>.
            Minimum age for this position: <?= $app->minCandidateAge() ?>.
        </p>
        <blockquote class="manifesto"><?= nl2br(e($app->manifesto())) ?></blockquote>

        <p>Application fee: <strong><?= e(naira($app->feeAmount())) ?></strong> <?= status_badge($app->feeStatus()) ?>
            <span class="hint">(demonstration only, no real payment)</span></p>

        <div class="row-actions">
            <form class="inline-fields" method="post" action="<?= e(url('/admin/candidates/fee')) ?>">
                <?= csrf_field() ?>
                <input type="hidden" name="application_id" value="<?= $app->id() ?>">
                <input type="hidden" name="return" value="<?= e($filter) ?>">
                <div class="field">
                    <label for="fee-<?= $app->id() ?>">Fee status</label>
                    <select id="fee-<?= $app->id() ?>" name="fee_status">
                        <?php foreach ($feeStatuses as $fee): ?>
                            <option value="<?= e($fee) ?>" <?= $fee === $app->feeStatus() ? 'selected' : '' ?>><?= e(label($fee)) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <button type="submit" class="btn btn-secondary btn-small">Save fee</button>
            </form>

            <?php if ($app->status() === 'pending'): ?>
                <?php foreach ([['approve', 'Approve', ''], ['reject', 'Reject', 'btn-danger']] as [$decision, $text, $style]): ?>
                    <form class="inline-form" method="post" action="<?= e(url('/admin/candidates/review')) ?>">
                        <?= csrf_field() ?>
                        <input type="hidden" name="application_id" value="<?= $app->id() ?>">
                        <input type="hidden" name="decision" value="<?= e($decision) ?>">
                        <input type="hidden" name="return" value="<?= e($filter) ?>">
                        <button type="submit" class="btn btn-small <?= e($style) ?>"
                                data-confirm="<?= e($text . ' ' . $app->displayName() . '?') ?>"><?= e($text) ?></button>
                    </form>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </article>
<?php endforeach; ?>
