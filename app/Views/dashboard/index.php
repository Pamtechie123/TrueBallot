<?php
/** @var \App\Models\User $user */
/** @var array $elections */
/** @var int $openCount */
/** @var int $applyCount */
/** @var int $resultsCount */
?>
<h1>Welcome, <?= e($user->fullName()) ?></h1>

<p>Account status: <?= status_badge($user->status()) ?></p>

   <?php if ($user->isApproved()): ?>
       <?php $hasPhoto = \App\Services\PhotoStorage::exists($user->id()); ?>
       <section aria-labelledby="card-heading">
           <h2 id="card-heading">Your digital voter card</h2>

           <div class="voter-card <?= $hasPhoto ? 'has-photo' : '' ?>">
               <?php if ($hasPhoto): ?>
                   <img class="voter-photo" src="<?= e(url('/photo?v=' . \App\Services\PhotoStorage::version($user->id()))) ?>"
                        alt="Your photo" width="96" height="120">
               <?php endif; ?>
            <p class="voter-card-brand">TrueBallot</p>
            <p class="voter-card-title">Voter Card</p>

            <dl class="voter-card-details">
                <div>
                    <dt>Name</dt>
                    <dd><?= e($user->fullName()) ?></dd>
                </div>
                <div>
                    <dt>Voter number</dt>
                    <dd class="voter-number"><?= e($user->voterNumber()) ?></dd>
                </div>
                <div>
                    <dt>Status</dt>
                    <dd><?= e(label($user->status())) ?></dd>
                </div>
            </dl>

            <p class="voter-card-note">Demonstration card. Not an official identity document.</p>
        </div>

        <form class="photo-form inline-fields" method="post" action="<?= e(url('/profile/photo')) ?>" enctype="multipart/form-data">
            <?= csrf_field() ?>
            <div class="field">
                <label for="photo"><?= $hasPhoto ? 'Change your photo' : 'Add your photo' ?></label>
                <input type="file" id="photo" name="photo" accept="image/jpeg,image/png,image/webp" data-max-bytes="<?= \App\Services\PhotoStorage::MAX_BYTES ?>" required>
            </div>
            <button type="submit" class="btn btn-secondary">Upload</button>
        </form>
        <?php if ($hasPhoto): ?>
            <form class="inline-form" method="post" action="<?= e(url('/profile/photo/delete')) ?>">
                <?= csrf_field() ?>
                <button type="submit" class="link-button" data-confirm="Remove your photo?">Remove my photo</button>
            </form>
        <?php endif; ?>
    </section>

    <section aria-labelledby="elections-heading">
        <h2 id="elections-heading">Elections</h2>
        <div class="tiles">
            <a class="tile tile-link" href="<?= e(url('/vote')) ?>"><p class="tile-number"><?= (int) $openCount ?></p><p class="tile-label">Open for voting. Go and vote</p></a>
            <a class="tile tile-link" href="<?= e(url('/candidates/apply')) ?>"><p class="tile-number"><?= (int) $applyCount ?></p><p class="tile-label">Accepting candidate applications</p></a>
            <a class="tile tile-link" href="<?= e(url('/results')) ?>"><p class="tile-number"><?= (int) $resultsCount ?></p><p class="tile-label">With published results</p></a>
        </div>
        <p><a href="<?= e(url('/candidates/applications')) ?>">See my candidate applications</a></p>

        <h3>All elections</h3>
        <?php if ($elections === []): ?>
            <p class="muted">No elections have been created yet.</p>
        <?php endif; ?>
        <?php foreach ($elections as $item): $el = $item['election']; $phase = $item['phase']; ?>
            <article class="card election">
                <header class="election-head">
                    <h3><?= e($el->title()) ?></h3>
                    <?= phase_badge($phase) ?>
                </header>
                <?php if ($el->description() !== ''): ?><p><?= e($el->description()) ?></p><?php endif; ?>

                <ul class="timeline">
                    <li><strong>Applications:</strong> <?= e(format_datetime($el->applicationsOpenAt())) ?> to <?= e(format_datetime($el->applicationsCloseAt())) ?></li>
                    <li><strong>Voting:</strong> <?= e(format_datetime($el->startsAt())) ?> to <?= e(format_datetime($el->endsAt())) ?></li>
                </ul>
                <p>Positions:
                    <?= $item['positions'] === [] ? '<span class="muted">none yet</span>' : e(implode(', ', array_map(fn ($p) => $p->title(), $item['positions']))) ?>
                </p>

                <?php if ($phase === 'applications_open' && $item['positions'] !== []): ?>
                    <p><a class="btn btn-small" href="<?= e(url('/candidates/apply')) ?>">Apply for a position</a></p>
                    <p class="hint">Applications close on <?= e(format_datetime($el->applicationsCloseAt())) ?>.</p>
                <?php elseif ($phase === 'applications_open'): ?>
                    <p class="hint">Applications are open, but the administrator has not added positions yet.</p>
                <?php elseif ($phase === 'upcoming'): ?>
                    <p class="hint">Applications open on <?= e(format_datetime($el->applicationsOpenAt())) ?>.</p>
                <?php elseif ($phase === 'waiting'): ?>
                    <p class="hint">Applications are closed. Voting starts on <?= e(format_datetime($el->startsAt())) ?>.</p>
                <?php elseif ($phase === 'voting_open'): ?>
                    <p><a class="btn btn-small" href="<?= e(url('/vote')) ?>">Go to the ballot</a></p>
                    <p class="hint">Voting closes on <?= e(format_datetime($el->endsAt())) ?>.</p>
                <?php elseif ($phase === 'results_published'): ?>
                    <p><a class="btn btn-small" href="<?= e(url('/results')) ?>">See results</a></p>
                <?php else: ?>
                    <p class="hint">Voting has ended. Results will be published soon.</p>
                <?php endif; ?>
            </article>
        <?php endforeach; ?>
    </section>
<?php else: ?>
    <div class="notice">
        <strong>Your registration is awaiting approval.</strong>
        An administrator will review it soon. Your voter card and the elections will appear here once you are approved.
    </div>
<?php endif; ?>